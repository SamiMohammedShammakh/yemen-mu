<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Objective;
use App\Models\Plan;
use App\Models\ProgressUpdate;
use App\Models\StatusRule;
use App\Support\Fmt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * حساب الإنجاز وفق نوع المؤشر وتعريفه المعتمد.
 *
 * القواعد:
 * - لا تدخل إلا التحديثات «المعتمدة» في الأرقام الرسمية. التحديثات بانتظار التحقق تُعرض منفصلة.
 * - تراكمي/دوري: قيمة التحديث = ما تحقق خلال الفترة، وقيمة الربع = مجموع التحديثات المعتمدة فيه.
 * - نقطة زمنية: قيمة الربع = متوسط مرجح بعدد المشاركين للتحديثات المعتمدة فيه (أو آخر قيمة إن لم يُذكر العدد).
 * - تحديث التسوية (is_adjustment) المعتمد عبر طلب تغيير يحل محل قيمة الربع المغلق.
 * - الربع الذي لم يحن موعد قياسه يُعرض «لم يستحق بعد»، والمستحق بلا بيانات معتمدة «بيانات ناقصة» — لا يُعرض صفرًا.
 * - إنجاز الهدف = متوسط مرجح بأوزان مؤشراته المقاسة، وإنجاز الخطة = متوسط مرجح بأوزان أهدافها المقاسة.
 *   تُقصّ نسبة كل مؤشر عند 100% قبل التجميع حتى لا يغطي التجاوز في مؤشر على تأخر آخر.
 */
class Calculator
{
    public const CAP = 100.0;

    public const NOT_DUE = ['key' => 'not_due', 'label' => 'لم يستحق بعد'];

    private array $rules;

    public function __construct(?array $rules = null, private ?Carbon $today = null)
    {
        $this->rules = $rules ?? StatusRule::current()->toSnapshot();
        $this->today = $today ?? now()->startOfDay();
    }

    public function rules(): array { return $this->rules; }

    public static function compareLabel(string $kind, int $q): string
    {
        if ($kind === 'cumulative') {
            return ['', 'مستهدف الربع الأول', 'مستهدف النصف الأول', 'المستهدف التراكمي حتى نهاية الربع الثالث', 'المستهدف السنوي (نهاية العام)'][$q];
        }

        return 'مستهدف ' . ['', 'الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع'][$q];
    }

    public function statusFor(?float $achievement): array
    {
        if ($achievement === null) {
            return ['key' => 'no_data', 'label' => 'لا توجد بيانات معتمدة'];
        }
        $a = min($achievement, self::CAP);
        if ($a >= $this->rules['on_track_min']) {
            return ['key' => 'on_track', 'label' => 'يسير حسب الخطة'];
        }
        if ($a >= $this->rules['follow_up_min']) {
            return ['key' => 'follow_up', 'label' => 'يحتاج متابعة'];
        }

        return ['key' => 'late', 'label' => 'متأخر'];
    }

    /** نسبة الإنجاز حسب اتجاه التحسن */
    public static function ratio(Indicator $i, ?float $actual, ?float $target): ?float
    {
        if ($actual === null) {
            return null;
        }
        if ($i->direction === 'range') {
            $min = $i->range_min;
            $max = $i->range_max;
            if ($min === null || $max === null) {
                return null;
            }
            if ($actual >= $min && $actual <= $max) {
                return 100.0;
            }
            if ($actual < $min) {
                return $min > 0 ? max(0, $actual / $min * 100) : 0.0;
            }

            return $actual > 0 ? $max / $actual * 100 : 0.0;
        }
        if ($target === null) {
            return null;
        }
        if ($i->direction === 'lower') {
            if ($actual <= 0) {
                return 100.0;
            }

            return $target / $actual * 100;
        }
        if ($target == 0.0) {
            return $actual >= 0 ? 100.0 : 0.0;
        }

        return $actual / $target * 100;
    }

    public function quarterIsDue(Plan $plan, int $q): bool
    {
        $quarter = $plan->year->quarters->firstWhere('number', $q);
        if (! $quarter) {
            return false;
        }

        return $quarter->isClosed() || $quarter->ends_on->lt($this->today);
    }

    /** @return Collection<int, ProgressUpdate> */
    private function approvedFor(Indicator $i): Collection
    {
        return $i->relationLoaded('updates')
            ? $i->updates->where('status', 'approved')
            : $i->updates()->where('status', 'approved')->get();
    }

    private function quarterValue(Indicator $i, Collection $ups): ?array
    {
        if ($ups->isEmpty()) {
            return null;
        }
        $adj = $ups->where('is_adjustment', true)->sortByDesc('id')->first();
        if ($adj) {
            return ['value' => (float) $adj->actual_value, 'participants' => (int) $adj->participants, 'ids' => [$adj->id], 'adjusted' => true];
        }
        $ups = $ups->filter(fn ($u) => $u->actual_value !== null);
        if ($ups->isEmpty()) {
            return null;
        }
        if ($i->kind === 'point') {
            $withP = $ups->filter(fn ($u) => $u->participants > 0);
            if ($withP->isNotEmpty() && $i->aggregation === 'weighted_average') {
                $n = $withP->sum('participants');
                $v = $withP->sum(fn ($u) => $u->actual_value * $u->participants) / $n;

                return ['value' => $v, 'participants' => $n, 'ids' => $withP->pluck('id')->all(), 'adjusted' => false];
            }
            $last = $ups->sortByDesc('id')->first();

            return ['value' => (float) $last->actual_value, 'participants' => (int) $last->participants, 'ids' => [$last->id], 'adjusted' => false];
        }

        return ['value' => (float) $ups->sum('actual_value'), 'participants' => 0, 'ids' => $ups->pluck('id')->all(), 'adjusted' => false];
    }

    public function indicator(Indicator $i, int $upto): array
    {
        $plan = $i->plan;
        $approved = $this->approvedFor($i);
        $pendingAll = $i->relationLoaded('updates') ? $i->updates->where('status', 'pending') : $i->updates()->where('status', 'pending')->get();
        $unit = $i->unit ?: '';
        $quarters = [];
        $cumT = 0.0;
        $cumA = 0.0;
        $anyApprovedToDate = false;
        $measuredValues = [];

        for ($q = 1; $q <= 4; $q++) {
            $t = $i->targetFor($q);
            $cumT += $t ?? 0;
            $val = $this->quarterValue($i, $approved->where('quarter', $q));
            $pending = $pendingAll->where('quarter', $q);
            $due = $this->quarterIsDue($plan, $q) && $i->measuredInQuarter($q);
            if ($val) {
                $cumA += $val['value'];
                if ($q <= $upto) {
                    $anyApprovedToDate = true;
                    $measuredValues[$q] = $val;
                }
            }

            if ($val) {
                $state = 'measured';
            } elseif (! $due) {
                $state = 'not_due';
            } elseif ($pending->isNotEmpty()) {
                $state = 'pending';
            } else {
                $state = 'missing';
            }

            if ($i->kind === 'cumulative') {
                $cmpTarget = $cumT;
                $cmpActual = $val ? $cumA : null;
            } else {
                $cmpTarget = $t;
                $cmpActual = $val['value'] ?? null;
            }
            $ach = $state === 'measured' ? self::ratio($i, $cmpActual, $cmpTarget) : null;

            $quarters[$q] = [
                'quarter' => $q,
                'target' => $t,
                'cum_target' => $i->kind === 'cumulative' ? $cumT : null,
                'actual' => $val['value'] ?? null,
                'cum_actual' => $i->kind === 'cumulative' && $val ? $cumA : null,
                'participants' => $val['participants'] ?? null,
                'compare_label' => self::compareLabel($i->kind, $q),
                'compare_target' => $cmpTarget,
                'compare_actual' => $cmpActual,
                'achievement' => $ach,
                'gap' => ($cmpActual !== null && $cmpTarget !== null) ? $cmpActual - $cmpTarget : null,
                'gap_text' => $this->gapText($i, $cmpActual, $cmpTarget, $unit),
                'state' => $state,
                'state_label' => self::stateLabel($state),
                'pending_count' => $pending->count(),
                'pending_value' => $pending->sum('actual_value'),
                'source_ids' => $val['ids'] ?? [],
                'adjusted' => $val['adjusted'] ?? false,
                'status' => $state === 'not_due' ? self::NOT_DUE : $this->statusFor($ach),
            ];
        }

        // الحساب السنوي حتى الربع المختار
        $annualTarget = $i->annual_target;
        $annualActual = null;
        $annualExplain = '';
        if ($anyApprovedToDate) {
            switch (true) {
                case $i->kind === 'cumulative':
                case $i->kind === 'periodic' && $i->aggregation === 'sum':
                    $annualActual = array_sum(array_map(fn ($v) => $v['value'], $measuredValues));
                    $annualExplain = 'مجموع القيم المعتمدة حتى نهاية ' . \App\Support\Workspace::quarterName($upto);
                    break;
                case $i->aggregation === 'average':
                    $annualActual = array_sum(array_map(fn ($v) => $v['value'], $measuredValues)) / count($measuredValues);
                    $annualExplain = 'متوسط قيم الأرباع المقاسة (' . count($measuredValues) . ')';
                    break;
                case $i->aggregation === 'weighted_average':
                    $n = array_sum(array_map(fn ($v) => $v['participants'], $measuredValues));
                    if ($n > 0) {
                        $annualActual = array_sum(array_map(fn ($v) => $v['value'] * $v['participants'], $measuredValues)) / $n;
                        $annualExplain = 'متوسط مرجح بعدد المشاركين (' . Fmt::num($n, 0) . ' مشاركًا) — لا تُجمع نسب الأرباع';
                    } else {
                        $annualActual = end($measuredValues)['value'];
                        $annualExplain = 'آخر قيمة مقاسة (لم يُسجَّل عدد المشاركين)';
                    }
                    break;
                default: // last
                    $annualActual = end($measuredValues)['value'];
                    $annualExplain = 'آخر قيمة مقاسة';
            }
        }
        $annualAch = self::ratio($i, $annualActual, $annualTarget);
        $sel = $quarters[$upto];

        $annualState = $anyApprovedToDate ? 'measured' : (collect(range(1, $upto))->contains(fn ($q) => $quarters[$q]['state'] !== 'not_due') ? ($pendingAll->where('quarter', '<=', $upto)->isNotEmpty() ? 'pending' : 'missing') : 'not_due');

        return [
            'id' => $i->id,
            'name' => $i->name,
            'unit' => $unit,
            'kind' => $i->kind,
            'kind_label' => $i->kindLabel(),
            'direction' => $i->direction,
            'aggregation' => $i->aggregation,
            'baseline' => $i->baseline,
            'weight' => $i->weight,
            'upto' => $upto,
            'quarters' => $quarters,
            'period' => $sel,
            'annual' => [
                'target' => $annualTarget,
                'actual' => $annualActual,
                'achievement' => $annualAch,
                'gap' => $annualActual !== null && $annualTarget !== null ? $annualActual - $annualTarget : null,
                'gap_text' => $this->gapText($i, $annualActual, $annualTarget, $unit),
                'state' => $annualState,
                'state_label' => self::stateLabel($annualState),
                'explain' => $annualExplain,
            ],
            'status' => $sel['state'] === 'not_due' ? self::NOT_DUE : $this->statusFor($sel['achievement']),
            'explain' => $this->explain($i, $sel, $annualActual, $annualTarget, $annualAch, $annualExplain),
        ];
    }

    public static function stateLabel(string $state): string
    {
        return ['measured' => 'مقاس', 'not_due' => 'لم يستحق بعد', 'missing' => 'بيانات ناقصة', 'pending' => 'بانتظار التحقق'][$state] ?? $state;
    }

    private function gapText(Indicator $i, ?float $actual, ?float $target, string $unit): string
    {
        if ($actual === null || $target === null) {
            return '—';
        }
        $d = $actual - $target;
        if (abs($d) < 0.005) {
            return 'مطابق للمستهدف';
        }
        $n = Fmt::num(abs($d)) . ($unit ? ' ' . $unit : '');
        $better = $i->direction === 'lower' ? $d < 0 : $d > 0;

        return ($d < 0 ? 'أقل من المستهدف بـ ' : 'أعلى من المستهدف بـ ') . $n . ($better ? ' (أفضل)' : '');
    }

    private function explain(Indicator $i, array $sel, $annualActual, $annualTarget, $annualAch, string $annualExplain): array
    {
        $lines = [];
        $q = $sel['quarter'];
        if ($sel['state'] !== 'measured') {
            $lines[] = self::compareLabel($i->kind, $q) . ': ' . $sel['state_label'] . ' — لا تُحتسب نسبة.';
        } else {
            if ($i->kind === 'cumulative') {
                $parts = [];
                for ($k = 1; $k <= $q; $k++) {
                    $parts[] = Fmt::num($i->targetFor($k) ?? 0);
                }
                $lines[] = self::compareLabel($i->kind, $q) . ' = ' . implode(' + ', $parts) . ' = ' . Fmt::num($sel['compare_target']);
                $lines[] = 'الفعلي المعتمد التراكمي = ' . Fmt::num($sel['compare_actual']);
            } else {
                $lines[] = self::compareLabel($i->kind, $q) . ' = ' . Fmt::num($sel['compare_target']);
                $lines[] = 'الفعلي المعتمد للربع = ' . Fmt::num($sel['compare_actual']) . ($i->kind === 'point' && $sel['participants'] ? ' (متوسط مرجح، ' . $sel['participants'] . ' مشاركًا)' : '');
            }
            if ($sel['achievement'] !== null) {
                $formula = $i->direction === 'lower' ? 'المستهدف ÷ الفعلي × 100' : ($i->direction === 'range' ? 'ضمن النطاق = 100% وإلا نسبة القرب من حد النطاق' : 'الفعلي ÷ المستهدف × 100');
                $lines[] = 'الإنجاز مقابل ' . self::compareLabel($i->kind, $q) . ' = ' . $formula . ' = ' . Fmt::pct($sel['achievement']);
            }
            if ($sel['source_ids']) {
                $lines[] = 'المصدر: التحديثات المعتمدة رقم ' . implode('، ', array_map(fn ($x) => '#' . $x, $sel['source_ids'])) . ($sel['adjusted'] ? ' (تسوية معتمدة بطلب تغيير)' : '');
            }
        }
        if ($annualActual !== null) {
            $lines[] = 'الإنجاز مقابل المستهدف السنوي (' . Fmt::num($annualTarget) . '): ' . $annualExplain . ' = ' . Fmt::num($annualActual) . ' ← ' . Fmt::pct($annualAch);
        }
        if ($sel['pending_count']) {
            $lines[] = 'غير محتسب: ' . $sel['pending_count'] . ' تحديث بانتظار التحقق.';
        }

        return $lines;
    }

    private static function weightedAvg(array $items, string $key): ?float
    {
        $sumW = 0.0;
        $sum = 0.0;
        foreach ($items as $it) {
            if ($it[$key] === null) {
                continue;
            }
            $sumW += $it['w'];
            $sum += $it['w'] * min($it[$key], self::CAP);
        }

        return $sumW > 0 ? $sum / $sumW : null;
    }

    public function objective(Objective $o, int $upto): array
    {
        $inds = $o->indicators;
        $hasWeights = $inds->contains(fn ($i) => $i->weight !== null && $i->weight > 0);
        $items = [];
        $rows = [];
        foreach ($inds as $i) {
            $r = $this->indicator($i, $upto);
            $w = $hasWeights ? (float) ($i->weight ?? 0) : ($inds->count() ? 100 / $inds->count() : 0);
            $r['effective_weight'] = $w;
            $rows[] = $r;
            $items[] = ['w' => $w, 'p' => $r['period']['achievement'], 'a' => $r['annual']['achievement']];
        }
        $p = self::weightedAvg($items, 'p');
        $a = self::weightedAvg($items, 'a');
        $totalW = array_sum(array_column($items, 'w'));
        $measuredW = array_sum(array_map(fn ($x) => $x['p'] === null ? 0 : $x['w'], $items));

        return [
            'id' => $o->id,
            'title' => $o->title,
            'weight' => $o->weight,
            'indicators' => $rows,
            'period_achievement' => $p,
            'annual_achievement' => $a,
            'coverage' => $totalW > 0 ? $measuredW / $totalW * 100 : 0,
            'status' => $p === null && collect($rows)->every(fn ($r) => $r['period']['state'] === 'not_due') ? self::NOT_DUE : $this->statusFor($p),
            'explain' => $hasWeights ? 'متوسط مرجح بأوزان المؤشرات المقاسة (كل نسبة مقصوصة عند 100%)' : 'أوزان متساوية للمؤشرات (لم تُحدد أوزان داخل الهدف)',
        ];
    }

    public function plan(Plan $plan, int $upto): array
    {
        $plan->loadMissing(['year.quarters', 'objectives.indicators.targets', 'objectives.indicators.updates', 'position']);
        foreach ($plan->objectives as $o) {
            foreach ($o->indicators as $i) {
                $i->setRelation('plan', $plan);
            }
        }
        $objs = [];
        $items = [];
        foreach ($plan->objectives as $o) {
            $r = $this->objective($o, $upto);
            $objs[] = $r;
            $items[] = ['w' => (float) ($o->weight ?? 0), 'p' => $r['period_achievement'], 'a' => $r['annual_achievement']];
        }
        $p = self::weightedAvg($items, 'p');
        $a = self::weightedAvg($items, 'a');
        $totalW = array_sum(array_column($items, 'w'));
        $measuredW = array_sum(array_map(fn ($x) => $x['p'] === null ? 0 : $x['w'], $items));

        return [
            'plan_id' => $plan->id,
            'position' => $plan->position->name,
            'year' => $plan->year->year,
            'quarter' => $upto,
            'version_no' => $plan->current_version,
            'plan_status' => $plan->status,
            'objectives' => $objs,
            'period_achievement' => $p,
            'annual_achievement' => $a,
            'coverage' => $totalW > 0 ? $measuredW / $totalW * 100 : 0,
            'status' => $p === null && $objs && collect($objs)->every(fn ($o) => $o['status']['key'] === 'not_due') ? self::NOT_DUE : $this->statusFor($p),
            'rules' => $this->rules,
            'tasks' => $this->taskStats($plan, $upto),
            'computed_at' => now()->toIso8601String(),
            'source' => 'live',
        ];
    }

    public function taskStats(Plan $plan, int $q): array
    {
        $tasks = $plan->tasks()->with('deferrals')->get();
        $inQ = $tasks->filter(fn ($t) => $t->quarter == $q || $t->deferrals->contains('from_quarter', $q));
        $planned = $inQ->count();
        $done = $inQ->filter(fn ($t) => $t->status === 'done' && $t->quarter == $q)->count();
        $deferred = $inQ->filter(fn ($t) => $t->deferrals->contains('from_quarter', $q))->count();
        $pending = $inQ->where('status', 'pending_verification')->count();
        $overdue = $inQ->filter(fn ($t) => $t->isOverdue() && $t->quarter == $q)->count();

        return compact('planned', 'done', 'deferred', 'pending', 'overdue');
    }
}
