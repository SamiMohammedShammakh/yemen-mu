<?php

namespace App\Services\Export;

use App\Models\Export;
use App\Models\Indicator;
use App\Models\Objective;
use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Fmt;
use App\Support\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء ملفات الخطط والنتائج وتسجيلها.
 * - تُفحص الصلاحية عند المعاينة وعند الإنشاء وعند كل تنزيل.
 * - يحمل كل ملف في رأسه: المنصب، السنة، الربع، رقم النسخة، تاريخ الإصدار، حالة الخطة (مسودة/معتمدة).
 * - الملفات نسخ منفصلة: تعديلها بعد التنزيل لا يغيّر شيئًا في النظام.
 */
class ExportService
{
    public const SCOPE_FORMATS = [
        'plan_full' => ['pdf', 'docx', 'xlsx', 'csv'],
        'plan_quarter' => ['pdf', 'docx', 'xlsx', 'csv'],
        'results_annual' => ['pdf', 'docx', 'xlsx', 'csv'],
        'results_quarter' => ['pdf', 'docx', 'xlsx', 'csv'],
        'objective' => ['pdf', 'docx', 'xlsx', 'csv'],
        'indicator' => ['pdf', 'docx', 'xlsx', 'csv'],
        'project' => ['pdf', 'docx', 'xlsx', 'csv'],
        'year_package' => ['zip'],
        'org_summary' => ['pdf', 'xlsx', 'csv'],
    ];

    public const NEEDS_QUARTER = ['plan_quarter', 'results_quarter', 'objective', 'indicator', 'results_annual'];

    public function __construct(private ReportBuilder $builder = new ReportBuilder()) {}

    /** يحلّل الطلب ويتحقق من الصلاحية. يعيد السياق الموحد. */
    public function resolve(User $u, array $in): array
    {
        $scope = $in['scope'] ?? '';
        $format = $in['format'] ?? '';
        if (! isset(self::SCOPE_FORMATS[$scope])) {
            throw ValidationException::withMessages(['scope' => 'اختر نوع التقرير.']);
        }
        if (! in_array($format, self::SCOPE_FORMATS[$scope], true)) {
            throw ValidationException::withMessages(['format' => 'الصيغة غير متاحة لهذا النوع من التقارير.']);
        }
        $plan = null;
        $subject = null;
        $subjectName = null;
        if ($scope === 'org_summary') {
            abort_unless(Access::hasGlobalView($u), 403, 'التقرير الشامل متاح للرئيس والإدارة التنفيذية المعتمدة ومسؤول التخطيط فقط.');
            $year = PlanningYear::findOrFail((int) ($in['planning_year_id'] ?? 0));
        } else {
            $plan = Plan::with(['position', 'year.quarters'])->findOrFail((int) ($in['plan_id'] ?? 0));
            abort_unless(Access::canViewPlan($u, $plan), 403, 'لا تملك صلاحية تنزيل ملفات هذه الخطة.');
            $year = $plan->year;
            if ($scope === 'objective') {
                $subject = Objective::where('plan_id', $plan->id)->findOrFail((int) ($in['subject_id'] ?? 0));
                $subjectName = $subject->title;
            } elseif ($scope === 'indicator') {
                $subject = Indicator::where('plan_id', $plan->id)->findOrFail((int) ($in['subject_id'] ?? 0));
                $subjectName = $subject->name;
            } elseif ($scope === 'project') {
                $subject = Project::where('plan_id', $plan->id)->findOrFail((int) ($in['subject_id'] ?? 0));
                $subjectName = $subject->name;
            }
        }
        $quarter = in_array($scope, self::NEEDS_QUARTER, true) || $scope === 'org_summary' ? max(1, min(4, (int) ($in['quarter'] ?? 1))) : null;

        return compact('scope', 'format', 'plan', 'year', 'quarter', 'subject', 'subjectName');
    }

    public function preview(User $u, array $in): array
    {
        $c = $this->resolve($u, $in);
        $h = $this->builder->header($c['scope'], $c['plan'], $c['year'], $c['quarter'], $c['subjectName']);
        $source = null;
        if ($c['plan'] && $c['quarter']) {
            $source = $this->builder->results()->plan($c['plan'], $c['quarter'])['source'] ?? null;
        }

        return $c + ['header' => $h, 'source' => $source, 'rules' => $this->builder->results()->calculator()->rules(), 'file_name' => $this->fileName($c)];
    }

    private function fileName(array $c): string
    {
        $pos = $c['plan'] ? $c['plan']->position->code : 'all';
        $parts = [$c['scope'], $pos, $c['year']->year];
        if ($c['quarter']) $parts[] = 'Q' . $c['quarter'];
        if ($c['subject']) $parts[] = $c['subject']->id;
        if ($c['plan']) $parts[] = $c['plan']->current_version > 0 ? 'v' . $c['plan']->current_version : 'draft';

        return implode('-', $parts) . '.' . $c['format'];
    }

    public function generate(User $u, array $in): Export
    {
        $c = $this->resolve($u, $in); // إعادة التحقق من الصلاحية عند الإنشاء
        $header = $this->builder->header($c['scope'], $c['plan'], $c['year'], $c['quarter'], $c['subjectName']);
        $uuid = (string) Str::uuid();
        $name = $this->fileName($c);
        $rel = 'exports/' . $c['year']->year . '/' . $uuid . '-' . $name;
        $abs = Storage::disk('local')->path($rel);
        @mkdir(dirname($abs), 0775, true);

        if ($c['format'] === 'zip') {
            $this->zip($c, $header, $abs);
        } else {
            $doc = $this->document($c, $header);
            match ($c['format']) {
                'pdf' => (new Renderers\PdfRenderer())->render($doc, $header, $abs),
                'docx' => (new Renderers\DocxRenderer())->render($doc, $header, $abs),
                'xlsx' => (new Renderers\XlsxRenderer())->render($doc, $header, $abs, $this->csvData($c)),
                'csv' => (new Renderers\CsvRenderer())->render($this->csvData($c), $header, $abs),
            };
        }

        $export = Export::create([
            'uuid' => $uuid, 'user_id' => $u->id, 'plan_id' => $c['plan']?->id, 'planning_year_id' => $c['year']->id,
            'quarter' => $c['quarter'], 'scope' => $c['scope'], 'subject_id' => $c['subject']?->id, 'format' => $c['format'],
            'file_path' => $rel, 'file_name' => $name, 'size' => filesize($abs), 'plan_version_no' => $c['plan']?->current_version,
            'plan_status' => $c['plan']?->status, 'data_as_of' => $header['data_as_of_raw'] ?? now(),
            'rules' => $this->builder->results()->calculator()->rules(),
        ]);
        Audit::log('export.create', $export, null, ['scope' => $c['scope'], 'format' => $c['format'], 'file' => $name], $c['plan']?->id);

        return $export;
    }

    /** إعادة التحقق عند التنزيل: صلاحية الخطة كما هي الآن، لا كما كانت عند الإنشاء */
    public function authorizeDownload(User $u, Export $e): void
    {
        if ($e->plan_id) {
            abort_unless($e->plan && Access::canViewPlan($u, $e->plan), 403, 'لا تملك صلاحية تنزيل هذا الملف.');
        } else {
            abort_unless(Access::hasGlobalView($u), 403, 'لا تملك صلاحية تنزيل هذا الملف.');
        }
    }

    // ------------------------------------------------------------------ المحتوى

    private function kvHeader(array $h): array
    {
        return ['kv', [
            ['المنصب', $h['position']], ['السنة', (string) $h['year']], ['الربع', $h['quarter']], ['رقم نسخة الخطة', $h['version']],
            ['حالة الخطة', $h['status'] . ($h['status_detail'] ? ' — ' . $h['status_detail'] : '')], ['تاريخ البيانات', $h['data_as_of']], ['تاريخ إصدار الملف', $h['issued_at']],
        ]];
    }

    public function document(array $c, array $h): array
    {
        $b = [['h1', $h['title']], $this->kvHeader($h)];
        $plan = $c['plan'];
        if ($plan && ! $plan->isOfficial()) {
            $b[] = ['warn', 'هذه نسخة مسودة غير معتمدة؛ لا تُعد نتائجها رسمية.'];
        }

        switch ($c['scope']) {
            case 'plan_full':
            case 'plan_quarter':
                return array_merge($b, $this->planBlocks($plan, $c['scope'] === 'plan_quarter' ? $c['quarter'] : null));
            case 'results_quarter':
                return array_merge($b, $this->quarterResultBlocks($plan, $c['quarter']));
            case 'results_annual':
                return array_merge($b, $this->annualBlocks($plan, $c['quarter']));
            case 'objective':
                $res = $this->builder->quarterResults($plan, $c['quarter']);
                $o = $this->builder->results()->findObjective($res, $c['subject']->id);

                return array_merge($b, $this->objectiveBlocks($o, $res));
            case 'indicator':
                return array_merge($b, $this->indicatorBlocks($plan, $c['subject'], $c['quarter']));
            case 'project':
                return array_merge($b, $this->projectBlocks($c['subject']));
            case 'org_summary':
                return array_merge($b, $this->orgBlocks($c['year'], $c['quarter']));
        }

        return $b;
    }

    private function planBlocks(Plan $plan, ?int $q): array
    {
        $s = $this->builder->planStructure($plan, $q);
        $b = [
            ['h2', 'بيانات الخطة'],
            ['kv', [['مالك الخطة', $s['owner'] ?? '—'], ['نطاق العمل', $s['scope_description'] ?? '—'], ['النتيجة العامة', $s['overall_outcome'] ?? '—']]],
            ['h2', 'الأهداف والمؤشرات'],
        ];
        $qs = $q ? [$q] : [1, 2, 3, 4];
        foreach ($s['objectives'] as $n => $o) {
            $b[] = ['h3', ($o['ref'] ? $o['ref'] . ' ' : ($n + 1) . '. ') . $o['title'] . ' — الوزن ' . Fmt::num($o['weight']) . '%'];
            $b[] = ['p', 'الهدف الاستراتيجي: ' . ($o['strategic_goal'] ?? 'غير مرتبط')];
            if ($o['description']) $b[] = ['p', $o['description']];
            $b[] = ['table', ['المؤشر', 'التعريف', 'الوحدة', 'النوع', 'اتجاه التحسن', 'طريقة التجميع', 'الوزن داخل الهدف', 'مصدر البيانات', 'طريقة التحقق', 'الدورية', 'المالك'],
                array_map(fn ($i) => [$i['name'], $i['definition'], $i['unit'], $i['kind'], $i['direction'], $i['aggregation'], $i['weight'] !== null ? Fmt::num($i['weight']) . '%' : 'متساوٍ', $i['data_source'], $i['verification'], $i['frequency'], $i['owner']], $o['indicators']), 'wide'];
            $head = ['المؤشر', 'خط الأساس', 'المستهدف السنوي'];
            foreach ($qs as $k) {
                $head[] = 'مستهدف ر' . $k;
                $head[] = 'التراكمي بنهاية ر' . $k;
            }
            $head[] = 'الأدلة المطلوبة';
            $b[] = ['table', $head, array_map(function ($i) use ($qs) {
                $r = [$i['name'], Fmt::num($i['baseline']), Fmt::num($i['annual_target'])];
                foreach ($qs as $k) {
                    $r[] = Fmt::num($i['quarters'][$k]['target']);
                    $r[] = $i['quarters'][$k]['cum'] !== null ? Fmt::num($i['quarters'][$k]['cum']) : 'لا يُجمع';
                }
                $r[] = $i['evidence'];

                return $r;
            }, $o['indicators']), 'wide'];
        }
        $b[] = ['h2', $q ? 'المبادرات والمشاريع ومهام ' . Workspace::quarterName($q) : 'المبادرات والمشاريع والمهام'];
        $rows = [];
        foreach ($s['projects'] as $p) {
            foreach ($p['tasks'] ?: [['title' => '—', 'quarter' => null, 'original_quarter' => null, 'responsible' => null, 'due_on' => '—', 'evidence' => null, 'status' => '—']] as $t) {
                $rows[] = [trim(($p['ref'] ?? '') . ' ' . $p['type'] . ': ' . $p['name']) . (! empty($p['parent_ref']) ? ' (ضمن ' . $p['parent_ref'] . ')' : '') . (! empty($p['owner']) ? ' — المسؤول: ' . $p['owner'] : ''), $p['starts_on'] . ' ← ' . $p['ends_on'], $t['title'],
                    $t['quarter'] ? ('ر' . $t['quarter'] . ($t['original_quarter'] != $t['quarter'] ? ' (أصلًا ر' . $t['original_quarter'] . ')' : '')) : '—',
                    $t['responsible'] ?? $p['responsible'], $t['due_on'], $t['evidence'], $t['status']];
            }
        }
        $b[] = ['table', ['المشروع/المبادرة', 'المدة', 'المهمة', 'الربع', 'المسؤول', 'الموعد', 'الدليل المطلوب', 'الحالة'], $rows, 'wide'];
        $b[] = ['h2', 'المخاطر والموارد'];
        $b[] = ['kv', [['المخاطر', $s['risks'] ?? '—'], ['الموارد', $s['resources'] ?? '—']]];
        $b[] = ['h2', 'سجل الاعتماد'];
        $b[] = $s['approvals']
            ? ['table', ['الإجراء', 'بواسطة', 'التاريخ', 'النسخة', 'ملاحظة'], array_map(fn ($a) => [$a['action'], $a['by'], $a['at'], $a['version'] ? 'v' . $a['version'] : '—', $a['note']], $s['approvals'])]
            : ['p', 'لم تُعتمد الخطة بعد.'];

        return $b;
    }

    private function summaryKv(array $res, string $which = 'period'): array
    {
        $src = $res['source'] === 'snapshot' ? 'لقطة إقفال الربع (مراجعة ' . ($res['snapshot_revision'] ?? 1) . ')' : 'حساب مباشر من التحديثات المعتمدة';

        return ['kv', [
            [$which === 'period' ? 'إنجاز الخطة مقابل المستهدف المرحلي' : 'إنجاز الخطة مقابل المستهدف السنوي', Fmt::pct($which === 'period' ? $res['period_achievement'] : $res['annual_achievement'])],
            ['الحالة', $res['status']['label']],
            ['تغطية القياس (وزن المؤشرات المقاسة)', Fmt::pct($res['coverage'])],
            ['مصدر الأرقام', $src],
            ['قواعد الحالات المستخدمة', 'النسخة ' . $res['rules']['version'] . ': يسير حسب الخطة ≥ ' . Fmt::num($res['rules']['on_track_min']) . '%، يحتاج متابعة ≥ ' . Fmt::num($res['rules']['follow_up_min']) . '%، وما دون ذلك متأخر'],
        ]];
    }

    private function objectiveTable(array $o): array
    {
        return ['table', ['المؤشر', 'أساس المقارنة', 'المستهدف', 'الفعلي المعتمد', 'الفجوة', 'الإنجاز', 'حالة البيانات', 'الحالة'],
            array_map(fn ($i) => [
                $i['name'] . ($i['unit'] ? ' (' . $i['unit'] . ')' : ''), $i['period']['compare_label'], Fmt::num($i['period']['compare_target']),
                $i['period']['state'] === 'measured' ? Fmt::num($i['period']['compare_actual']) : $i['period']['state_label'],
                $i['period']['gap_text'], $i['period']['achievement'] !== null ? 'الإنجاز مقابل ' . $i['period']['compare_label'] . ': ' . Fmt::pct($i['period']['achievement']) : '—',
                $i['period']['state_label'] . ($i['period']['pending_count'] ? ' (+' . $i['period']['pending_count'] . ' بانتظار التحقق، غير محتسب)' : ''), $i['status']['label'],
            ], $o['indicators']), 'wide'];
    }

    private function objectiveBlocks(array $o, array $res): array
    {
        $b = [['h2', $o['title'] . ' — الوزن ' . Fmt::num($o['weight']) . '%'],
            ['kv', [['إنجاز الهدف مقابل المستهدف المرحلي', Fmt::pct($o['period_achievement'])], ['إنجاز الهدف مقابل المستهدف السنوي', Fmt::pct($o['annual_achievement'])],
                ['الحالة', $o['status']['label']], ['طريقة الحساب', $o['explain']]]],
            $this->objectiveTable($o)];
        foreach ($o['indicators'] as $i) {
            $b[] = ['h3', 'تفاصيل حساب: ' . $i['name']];
            $b[] = ['list', $i['explain']];
        }

        return $b;
    }

    private function quarterResultBlocks(Plan $plan, int $q): array
    {
        $res = $this->builder->quarterResults($plan, $q);
        $b = [['h2', 'ملخص نتائج ' . Workspace::quarterName($q)], $this->summaryKv($res),
            ['table', ['الهدف', 'الوزن', 'الإنجاز مقابل المستهدف المرحلي', 'الإنجاز مقابل المستهدف السنوي', 'الحالة'],
                array_map(fn ($o) => [$o['title'], Fmt::num($o['weight']) . '%', Fmt::pct($o['period_achievement']), Fmt::pct($o['annual_achievement']), $o['status']['label']], $res['objectives'])]];
        foreach ($res['objectives'] as $o) {
            $b = array_merge($b, $this->objectiveBlocks($o, $res));
        }
        $b[] = ['h2', 'مهام ' . Workspace::quarterName($q)];
        $t = $res['tasks'];
        $b[] = ['p', "المخطط: {$t['planned']} — المنجز المعتمد: {$t['done']} — المنقول للربع التالي: {$t['deferred']} — بانتظار التحقق: {$t['pending']} — متأخر: {$t['overdue']}"];
        $b[] = ['table', ['المهمة', 'المشروع', 'المسؤول', 'الموعد', 'الحالة', 'سبب النقل', 'الموعد الجديد'],
            array_map(fn ($x) => [$x['title'], $x['project'], $x['responsible'], $x['due_on'], $x['status_label'] . (($x['overdue'] ?? false) ? ' (متأخرة)' : ''), $x['deferral_reason'], $x['new_due_on']], $res['tasks_list'])];
        $b[] = ['note', 'لا تدخل التحديثات بانتظار التحقق في الأرقام أعلاه. المؤشر الذي لم يحن موعد قياسه يظهر «لم يستحق بعد» ولا يُحتسب صفرًا.'];

        return $b;
    }

    private function annualBlocks(Plan $plan, int $upto): array
    {
        $res = $this->builder->quarterResults($plan, $upto);
        $b = [['h2', 'النتائج السنوية حتى نهاية ' . Workspace::quarterName($upto)], $this->summaryKv($res, 'annual')];
        $qrows = [];
        for ($q = 1; $q <= 4; $q++) {
            $r = $this->builder->results()->plan($plan, $q);
            $qrows[] = [Workspace::quarterName($q), Fmt::pct($r['period_achievement']), $r['status']['label'], $r['source'] === 'snapshot' ? 'مغلق — لقطة محفوظة' : 'مفتوح — حساب مباشر'];
        }
        $b[] = ['h3', 'إنجاز الخطة في كل ربع'];
        $b[] = ['table', ['الربع', 'الإنجاز مقابل المستهدف المرحلي', 'الحالة', 'المصدر'], $qrows];
        $b[] = ['h3', 'المؤشرات مقابل المستهدف السنوي'];
        $rows = [];
        foreach ($res['objectives'] as $o) {
            foreach ($o['indicators'] as $i) {
                $row = [$o['title'], $i['name'], $i['kind_label'], Fmt::num($i['annual']['target'])];
                for ($q = 1; $q <= 4; $q++) {
                    $row[] = $i['quarters'][$q]['state'] === 'measured' ? Fmt::num($i['quarters'][$q]['actual']) : $i['quarters'][$q]['state_label'];
                }
                $row[] = $i['annual']['actual'] !== null ? Fmt::num($i['annual']['actual']) : $i['annual']['state_label'];
                $row[] = $i['annual']['achievement'] !== null ? 'مقابل المستهدف السنوي: ' . Fmt::pct($i['annual']['achievement']) : '—';
                $row[] = $i['annual']['explain'];
                $rows[] = $row;
            }
        }
        $b[] = ['table', ['الهدف', 'المؤشر', 'النوع', 'المستهدف السنوي', 'فعلي ر1', 'فعلي ر2', 'فعلي ر3', 'فعلي ر4', 'الفعلي حتى تاريخه', 'الإنجاز', 'طريقة التجميع'], $rows, 'wide'];
        $b[] = ['note', 'لا تُجمع نسب الأرباع للمؤشرات التي تقاس في نقطة زمنية؛ تُستخدم طريقة التجميع المعتمدة لكل مؤشر. لا تُدمج نتائج سنوات مختلفة.'];

        return $b;
    }

    private function indicatorBlocks(Plan $plan, Indicator $ind, int $q): array
    {
        $ind->load(['targets', 'objective', 'owner']);
        $rows = $this->builder->results()->indicatorQuarterRows($plan, $ind);
        $b = [['h2', $ind->name],
            ['kv', [['الهدف', $ind->objective->title], ['التعريف', $ind->definition], ['الوحدة', $ind->unit], ['النوع', $ind->kindLabel()], ['اتجاه التحسن', $ind->directionLabel()],
                ['خط الأساس', Fmt::num($ind->baseline)], ['المستهدف السنوي', Fmt::num($ind->annual_target)], ['مصدر البيانات', $ind->data_source], ['طريقة التحقق', $ind->verification_method],
                ['الدورية', Indicator::FREQUENCIES[$ind->frequency] ?? '—'], ['المالك', $ind->owner?->name]]],
            ['table', ['الربع', 'مستهدف الربع', 'أساس المقارنة', 'المستهدف المرحلي', 'الفعلي المعتمد', 'الفجوة', 'الإنجاز', 'حالة البيانات', 'المصدر'],
                array_map(fn ($r) => [Workspace::quarterName($r['quarter']), Fmt::num($r['target']), $r['compare_label'], Fmt::num($r['compare_target']),
                    $r['state'] === 'measured' ? Fmt::num($r['compare_actual']) : $r['state_label'], $r['gap_text'], Fmt::pct($r['achievement']), $r['state_label'],
                    $r['source'] === 'snapshot' ? 'لقطة إقفال' : 'مباشر'], array_values($rows)), 'wide']];
        $res = $this->builder->results()->plan($plan, $q);
        $i = $this->builder->results()->findIndicator($res, $ind->id);
        $b[] = ['h3', 'تفاصيل الحساب حتى ' . Workspace::quarterName($q)];
        $b[] = ['list', $i['explain']];
        $b[] = ['h3', 'سجل التحديثات'];
        $ups = $this->builder->updatesRows($plan, $ind->id);
        $b[] = ['table', ['#', 'الربع', 'القيمة', 'المشاركون', 'ما تم إنجازه', 'حالة التحقق', 'محتسب', 'التاريخ'],
            array_map(fn ($u) => [$u['id'], $u['quarter'], Fmt::num($u['actual']), $u['participants'], $u['achieved'], $u['status'], $u['counted'], $u['at']], $ups)];

        return $b;
    }

    private function projectBlocks(Project $p): array
    {
        $p->load(['tasks.deferrals', 'objective', 'parent']);

        return [['h2', $p->ref . ' ' . $p->typeLabel() . ': ' . $p->name . ($p->parent ? ' (ضمن ' . $p->parent->ref . ')' : '')],
            ['kv', [['الهدف المرتبط', $p->objective?->title ?? '—'], ['الوصف', $p->description], ['المسؤول', $p->responsible], ['المدة', Fmt::date($p->starts_on) . ' ← ' . Fmt::date($p->ends_on)], ['الموارد', $p->resources]]],
            ['table', ['الرقم', 'المهمة', 'الربع الأصلي', 'الربع الحالي', 'المسؤول', 'الموعد', 'الحالة', 'سجل النقل'],
                $p->tasks->map(fn ($t) => [$t->ref, $t->title, 'ر' . $t->original_quarter, 'ر' . $t->quarter, $t->responsible, Fmt::date($t->due_on), $t->statusLabel(),
                    $t->deferrals->map(fn ($d) => 'ر' . $d->from_quarter . '←ر' . $d->to_quarter . ': ' . $d->reason)->implode(' | ')])->all()]];
    }

    private function orgBlocks(PlanningYear $year, int $q): array
    {
        $rows = [];
        foreach (Plan::where('planning_year_id', $year->id)->with('position')->get()->sortBy('position.sort') as $p) {
            $r = $p->isLocked() && $p->objectives()->exists() ? $this->builder->results()->plan($p, $q) : null;
            $rows[] = [$p->position->name, $p->statusLabel(), $p->current_version ? 'v' . $p->current_version : '—',
                $r ? Fmt::pct($r['period_achievement']) : 'غير معتمدة', $r ? Fmt::pct($r['annual_achievement']) : '—', $r['status']['label'] ?? '—'];
        }

        return [['h2', 'مقارنة المناصب — ' . Workspace::quarterName($q) . ' ' . $year->year],
            ['table', ['المنصب', 'حالة الخطة', 'النسخة', 'الإنجاز مقابل المستهدف المرحلي', 'الإنجاز مقابل المستهدف السنوي', 'الحالة'], $rows],
            ['note', 'تُحتسب نتائج الخطط المعتمدة فقط. كل خطة تقاس بمستهدفاتها؛ لا تُدمج نتائج سنوات مختلفة.']];
    }

    public function csvData(array $c): array
    {
        $plan = $c['plan'];
        if ($c['scope'] === 'org_summary') {
            $doc = $this->orgBlocks($c['year'], $c['quarter']);

            return ['columns' => $doc[1][1], 'rows' => $doc[1][2]];
        }
        if ($c['scope'] === 'project') {
            $doc = $this->projectBlocks($c['subject']);

            return ['columns' => $doc[2][1], 'rows' => $doc[2][2]];
        }
        if (in_array($c['scope'], ['plan_full', 'plan_quarter'], true)) {
            $s = $this->builder->planStructure($plan, $c['quarter']);
            $cols = ['الهدف', 'وزن الهدف', 'المؤشر', 'التعريف', 'الوحدة', 'النوع', 'الاتجاه', 'خط الأساس', 'المستهدف السنوي', 'ر1', 'ر2', 'ر3', 'ر4', 'مصدر البيانات', 'الدورية', 'المالك'];
            $rows = [];
            foreach ($s['objectives'] as $o) {
                foreach ($o['indicators'] as $i) {
                    $rows[] = [$o['title'], $o['weight'], $i['name'], $i['definition'], $i['unit'], $i['kind'], $i['direction'], $i['baseline'], $i['annual_target'],
                        $i['quarters'][1]['target'], $i['quarters'][2]['target'], $i['quarters'][3]['target'], $i['quarters'][4]['target'], $i['data_source'], $i['frequency'], $i['owner']];
                }
            }

            return ['columns' => $cols, 'rows' => $rows];
        }
        $res = $this->builder->quarterResults($plan, $c['quarter']);
        $rows = $this->builder->indicatorRows($res, $c['scope'] === 'indicator' ? $c['subject']->id : null, $c['scope'] === 'objective' ? $c['subject']->id : null);
        $out = ['columns' => array_values(ReportBuilder::INDICATOR_COLUMNS), 'rows' => array_map('array_values', $rows)];
        $out['second'] = [
            'title' => 'سجل التحديثات',
            'columns' => array_values(ReportBuilder::UPDATE_COLUMNS),
            'rows' => array_map('array_values', $this->builder->updatesRows($plan, $c['scope'] === 'indicator' ? $c['subject']->id : null, $c['scope'] === 'results_quarter' ? $c['quarter'] : null)),
        ];

        return $out;
    }

    /** حزمة السنة: الخطة + تقارير الأرباع + النتائج السنوية + البيانات + فهرس */
    private function zip(array $c, array $h, string $abs): void
    {
        $plan = $c['plan'];
        $tmp = storage_path('app/private/tmp/' . Str::uuid());
        @mkdir($tmp, 0775, true);
        $files = [];
        $make = function (string $scope, string $format, ?int $q, string $name, string $title) use ($plan, $tmp, &$files) {
            $cc = ['scope' => $scope, 'format' => $format, 'plan' => $plan, 'year' => $plan->year, 'quarter' => $q, 'subject' => null, 'subjectName' => null];
            $hh = $this->builder->header($scope, $plan, $plan->year, $q);
            $path = $tmp . '/' . $name;
            match ($format) {
                'pdf' => (new Renderers\PdfRenderer())->render($this->document($cc, $hh), $hh, $path),
                'docx' => (new Renderers\DocxRenderer())->render($this->document($cc, $hh), $hh, $path),
                'xlsx' => (new Renderers\XlsxRenderer())->render($this->document($cc, $hh), $hh, $path, $this->csvData($cc)),
                'csv' => (new Renderers\CsvRenderer())->render($this->csvData($cc), $hh, $path),
            };
            $files[] = [$name, $title, $format];
        };
        $code = $plan->position->code;
        $y = $plan->year->year;
        $make('plan_full', 'pdf', null, "01-plan-$code-$y.pdf", 'الخطة كاملة (PDF للطباعة)');
        $make('plan_full', 'docx', null, "02-plan-$code-$y.docx", 'الخطة كاملة (DOCX قابل للتحرير)');
        for ($q = 1; $q <= 4; $q++) {
            $make('results_quarter', 'pdf', $q, "1{$q}-results-Q{$q}-$code-$y.pdf", 'نتائج ' . Workspace::quarterName($q));
        }
        $make('results_annual', 'xlsx', 4, "20-results-annual-$code-$y.xlsx", 'جداول النتائج السنوية والأرباع والمؤشرات');
        $make('results_annual', 'csv', 4, "21-indicators-updates-$code-$y.csv", 'بيانات المؤشرات والتحديثات (CSV)');

        $index = "\xEF\xBB\xBF" . "فهرس حزمة السنة\n" . self::headerText($h) . "\n\nالملف,المحتوى,الصيغة\n";
        foreach ($files as [$n, $t, $f]) {
            $index .= "$n,$t," . strtoupper($f) . "\n";
        }
        file_put_contents($tmp . '/00-index.csv', $index);
        $z = new \ZipArchive();
        $z->open($abs, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFile($tmp . '/00-index.csv', '00-index.csv');
        foreach ($files as [$n]) {
            $z->addFile($tmp . '/' . $n, $n);
        }
        $z->setArchiveComment(strip_tags(self::headerText($h)));
        $z->close();
        array_map('unlink', glob($tmp . '/*'));
        @rmdir($tmp);
    }

    public static function headerText(array $h): string
    {
        return "{$h['org']}\nالمنصب: {$h['position']}\nالسنة: {$h['year']}\nالربع: {$h['quarter']}\nرقم النسخة: {$h['version']}\nحالة الخطة: {$h['status']}\nتاريخ البيانات: {$h['data_as_of']}\nتاريخ الإصدار: {$h['issued_at']}";
    }
}
