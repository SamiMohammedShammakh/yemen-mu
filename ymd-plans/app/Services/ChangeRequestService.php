<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\Objective;
use App\Models\Plan;
use App\Models\PlanReview;
use App\Models\PlanVersion;
use App\Models\ProgressUpdate;
use App\Models\QuarterSnapshot;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * طلبات التغيير: الطريق الوحيد لتعديل خطة معتمدة أو نتيجة ربع مغلق.
 * كل طلب يحفظ القيم القديمة والجديدة وأثره على الأرباع، واعتماده ينشئ نسخة جديدة من الخطة
 * أو مراجعة جديدة للقطة الربع، مع إبقاء النسخ السابقة كما هي.
 */
class ChangeRequestService
{
    public const EDITABLE = [
        'objective' => ['title' => 'عنوان الهدف', 'weight' => 'وزن الهدف'],
        'indicator' => ['annual_target' => 'المستهدف السنوي', 'weight' => 'وزن المؤشر', 'baseline' => 'خط الأساس', 'data_source' => 'مصدر البيانات'],
        'target' => ['target' => 'مستهدف الربع'],
    ];

    public function canRequest(User $u, Plan $p): bool
    {
        return in_array($p->status, ['approved', 'active'], true) && (Access::isPlanOwner($u, $p) || Access::canReview($u));
    }

    /** يبني قائمة التغييرات مع القيم القديمة من قاعدة البيانات */
    public function buildChanges(Plan $plan, array $input): array
    {
        $plan->load(['objectives', 'indicators.targets', 'year.quarters']);
        $changes = [];
        foreach ($input['objectives'] ?? [] as $id => $fields) {
            $o = $plan->objectives->firstWhere('id', (int) $id);
            abort_unless($o, 404);
            foreach ($fields as $f => $new) {
                if (! isset(self::EDITABLE['objective'][$f]) || $new === null || $new === '') continue;
                if ((string) $o->$f !== (string) $new && ! (is_numeric($new) && (float) $o->$f == (float) $new)) {
                    $changes[] = ['entity' => 'objective', 'id' => $o->id, 'field' => $f, 'label' => self::EDITABLE['objective'][$f] . ' — ' . $o->title, 'old' => $o->$f, 'new' => $new];
                }
            }
        }
        foreach ($input['indicators'] ?? [] as $id => $fields) {
            $i = $plan->indicators->firstWhere('id', (int) $id);
            abort_unless($i, 404);
            foreach ($fields as $f => $new) {
                if ($new === null || $new === '') continue;
                if ($f === 'targets') {
                    foreach ($new as $q => $v) {
                        if ($v === null || $v === '') continue;
                        $old = $i->targetFor((int) $q);
                        if ($old === null || (float) $old != (float) $v) {
                            $changes[] = ['entity' => 'target', 'id' => $i->id, 'quarter' => (int) $q, 'field' => 'target', 'label' => 'مستهدف الربع ' . $q . ' — ' . $i->name, 'old' => $old, 'new' => (float) $v];
                        }
                    }
                    continue;
                }
                if (! isset(self::EDITABLE['indicator'][$f])) continue;
                if ((string) $i->$f !== (string) $new && ! (is_numeric($new) && $i->$f !== null && (float) $i->$f == (float) $new)) {
                    $changes[] = ['entity' => 'indicator', 'id' => $i->id, 'field' => $f, 'label' => self::EDITABLE['indicator'][$f] . ' — ' . $i->name, 'old' => $i->$f, 'new' => $new];
                }
            }
        }

        return $changes;
    }

    public function impact(Plan $plan, array $changes): array
    {
        $plan->loadMissing('year.quarters');
        $closed = $plan->year->quarters->where('status', 'closed')->pluck('number')->all();
        $affected = [];
        foreach ($changes as $c) {
            if ($c['entity'] === 'target') {
                $affected[] = $c['quarter'];
            } elseif (in_array($c['field'], ['annual_target', 'weight'], true)) {
                $affected = array_merge($affected, [1, 2, 3, 4]);
            }
        }
        $affected = array_values(array_unique($affected));
        sort($affected);
        $out = [];
        foreach ($affected as $q) {
            $out[] = [
                'quarter' => $q,
                'closed' => in_array($q, $closed, true),
                'note' => in_array($q, $closed, true)
                    ? 'ربع مغلق: تبقى نتائجه المحفوظة في لقطة الإقفال كما هي، ولا تتغير تقاريره السابقة.'
                    : 'ربع مفتوح: يُحتسب بالقيم الجديدة بعد الاعتماد.',
            ];
        }

        return $out;
    }

    private function applyChanges(array $changes): void
    {
        foreach ($changes as $c) {
            match ($c['entity']) {
                'objective' => Objective::whereKey($c['id'])->update([$c['field'] => $c['new']]),
                'indicator' => Indicator::whereKey($c['id'])->update([$c['field'] => $c['new']]),
                'target' => IndicatorTarget::updateOrCreate(['indicator_id' => $c['id'], 'quarter' => $c['quarter']], ['target' => $c['new']]),
            };
        }
    }

    /** يتحقق أن الخطة بعد التعديل ما زالت مكتملة (الأوزان 100%، المستهدفات...) دون حفظ */
    public function simulate(Plan $plan, array $changes): array
    {
        DB::beginTransaction();
        try {
            $this->applyChanges($changes);
            $errors = (new PlanValidator())->errors($plan->fresh());
        } finally {
            DB::rollBack();
        }

        return $errors;
    }

    public function requestAmendment(User $u, Plan $plan, string $reason, array $input): ChangeRequest
    {
        abort_unless($this->canRequest($u, $plan), 403);
        $changes = $this->buildChanges($plan, $input);
        if (! $changes) {
            throw ValidationException::withMessages(['changes' => 'لم تُغيّر أي قيمة.']);
        }
        if ($errors = $this->simulate($plan, $changes)) {
            throw ValidationException::withMessages(['changes' => array_column($errors, 'message')]);
        }
        $cr = ChangeRequest::create([
            'plan_id' => $plan->id, 'type' => 'plan_amendment', 'reason' => $reason, 'changes' => $changes,
            'impact' => $this->impact($plan, $changes), 'status' => 'pending', 'requested_by' => $u->id,
            'base_version_no' => $plan->current_version,
        ]);
        Audit::log('change_request.create', $cr, null, ['changes' => $changes, 'reason' => $reason]);
        app(AlertService::class)->changeRequestCreated($cr);

        return $cr;
    }

    public function requestClosedQuarterResult(User $u, Plan $plan, Indicator $ind, int $q, float $value, ?int $participants, string $reason): ChangeRequest
    {
        abort_unless($this->canRequest($u, $plan) && $ind->plan_id === $plan->id, 403);
        $quarter = $plan->year->quarters()->where('number', $q)->firstOrFail();
        if (! $quarter->isClosed()) {
            throw ValidationException::withMessages(['quarter' => 'هذا الربع مفتوح؛ أضف تحديثًا عاديًا بدل طلب التغيير.']);
        }
        $res = new Results();
        $row = $res->findIndicator($res->plan($plan, $q), $ind->id);
        $old = $row['period']['actual'] ?? null;
        $changes = [[
            'entity' => 'quarter_result', 'id' => $ind->id, 'quarter' => $q, 'field' => 'actual_value',
            'label' => 'القيمة الفعلية المعتمدة للربع ' . $q . ' — ' . $ind->name, 'old' => $old, 'new' => $value, 'participants' => $participants,
        ]];
        $cr = ChangeRequest::create([
            'plan_id' => $plan->id, 'type' => 'closed_quarter_result', 'reason' => $reason, 'changes' => $changes, 'quarter' => $q,
            'impact' => [['quarter' => $q, 'closed' => true, 'note' => 'تُحفظ مراجعة جديدة للقطة الربع وتبقى اللقطة الأصلية محفوظة. تُعاد لقطات الأرباع المغلقة اللاحقة لأن المؤشر قد يكون تراكميًا.']],
            'status' => 'pending', 'requested_by' => $u->id, 'base_version_no' => $plan->current_version,
        ]);
        Audit::log('change_request.create', $cr, ['actual' => $old], ['actual' => $value, 'reason' => $reason]);
        app(AlertService::class)->changeRequestCreated($cr);

        return $cr;
    }

    public function decide(User $u, ChangeRequest $cr, bool $approve, ?string $note): ChangeRequest
    {
        abort_unless(Access::canApprove($u) && Access::canViewPlan($u, $cr->plan), 403);
        if ($cr->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'تم البت في هذا الطلب مسبقًا.']);
        }
        if (! $approve && ! trim((string) $note)) {
            throw ValidationException::withMessages(['decision_note' => 'اكتب سبب الرفض.']);
        }

        return DB::transaction(function () use ($u, $cr, $approve, $note) {
            $plan = $cr->plan;
            if (! $approve) {
                $cr->update(['status' => 'rejected', 'decided_by' => $u->id, 'decided_at' => now(), 'decision_note' => $note]);
                Audit::log('change_request.reject', $cr, null, ['note' => $note]);
                app(AlertService::class)->changeRequestDecided($cr);

                return $cr;
            }
            if ($cr->type === 'plan_amendment') {
                if ($errors = $this->simulate($plan, $cr->changes)) {
                    throw ValidationException::withMessages(['changes' => array_column($errors, 'message')]);
                }
                $this->applyChanges($cr->changes);
                $plan->refresh();
                $plan->current_version = (int) $plan->versions()->max('version_no') + 1;
                $plan->save();
                PlanVersion::where('plan_id', $plan->id)->whereNull('effective_to')->update(['effective_to' => now()]);
                PlanVersion::create([
                    'plan_id' => $plan->id, 'version_no' => $plan->current_version, 'snapshot' => PlanSnapshot::make($plan),
                    'reason' => $cr->reason, 'change_request_id' => $cr->id, 'approved_by' => $u->id,
                    'delegation_id' => Access::isPresident($u) ? null : Access::activeDelegation($u)?->id, 'effective_from' => now(),
                ]);
                PlanReview::create(['plan_id' => $plan->id, 'action' => 'amend', 'from_status' => $plan->status, 'to_status' => $plan->status,
                    'note' => 'طلب تعديل #' . $cr->id . ': ' . $cr->reason, 'version_no' => $plan->current_version, 'user_id' => $u->id]);
                $cr->resulting_version_no = $plan->current_version;
            } else {
                $c = $cr->changes[0];
                $upd = ProgressUpdate::create([
                    'plan_id' => $plan->id, 'indicator_id' => $c['id'], 'quarter' => $c['quarter'], 'period_label' => 'تسوية ربع مغلق',
                    'actual_value' => $c['new'], 'participants' => $c['participants'] ?? null,
                    'achieved' => 'تسوية معتمدة بطلب التغيير #' . $cr->id . ' — القيمة السابقة: ' . Fmt::num($c['old']),
                    'status' => 'approved', 'reviewed_by' => $u->id, 'reviewed_at' => now(), 'review_note' => $note,
                    'is_adjustment' => true, 'change_request_id' => $cr->id, 'plan_version_no' => $plan->current_version, 'created_by' => $cr->requested_by,
                ]);
                $closed = $plan->year->quarters()->where('status', 'closed')->where('number', '>=', $c['quarter'])->pluck('number');
                foreach ($closed as $q) {
                    $prev = QuarterSnapshot::where('plan_id', $plan->id)->where('quarter', $q)->orderByDesc('revision')->first();
                    $calc = new Calculator($prev?->rules);
                    $plan->unsetRelation('objectives');
                    $data = $calc->plan($plan, $q);
                    $data['approvals'] = $prev->data['approvals'] ?? [];
                    $data['tasks_list'] = $prev->data['tasks_list'] ?? [];
                    QuarterSnapshot::create([
                        'plan_id' => $plan->id, 'quarter' => $q, 'revision' => ($prev?->revision ?? 0) + 1,
                        'plan_version_no' => $plan->current_version, 'data' => $data, 'rules' => $calc->rules(),
                        'change_request_id' => $cr->id, 'created_by' => $u->id,
                    ]);
                }
                Audit::log('update.adjustment', $upd, ['actual' => $c['old']], ['actual' => $c['new']], $plan->id);
            }
            $cr->fill(['status' => 'approved', 'decided_by' => $u->id, 'decided_at' => now(), 'decision_note' => $note])->save();
            Audit::log('change_request.approve', $cr, ['values' => array_column($cr->changes, 'old')], ['values' => array_column($cr->changes, 'new'), 'version' => $plan->current_version]);
            app(AlertService::class)->changeRequestDecided($cr);

            return $cr;
        });
    }
}
