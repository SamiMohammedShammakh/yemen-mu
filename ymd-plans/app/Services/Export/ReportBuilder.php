<?php

namespace App\Services\Export;

use App\Models\Indicator;
use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\ProgressUpdate;
use App\Services\QuarterService;
use App\Services\Results;
use App\Support\Fmt;
use App\Support\Workspace;

/**
 * يبني محتوى التقرير (بيانات مجردة) من نفس مصدر النتائج الذي تعرضه الشاشات،
 * ثم تحوّله العارضات إلى PDF / DOCX / XLSX / CSV.
 */
class ReportBuilder
{
    public const ORG = 'جمعية المهندسين اليمنيين في تركيا';

    public function __construct(private Results $results = new Results()) {}

    public function results(): Results { return $this->results; }

    /** بيانات رأس الملف المشتركة */
    public function header(string $scope, ?Plan $plan, PlanningYear $year, ?int $quarter, ?string $subject = null): array
    {
        $official = $plan?->isOfficial();
        $asOf = $plan ? (ProgressUpdate::where('plan_id', $plan->id)->where('status', 'approved')->max('reviewed_at') ?? $plan->updated_at) : now();

        return [
            'org' => self::ORG,
            'title' => \App\Models\Export::SCOPES[$scope] . ($subject ? ' — ' . $subject : ''),
            'position' => $plan?->position->name ?? 'جميع المناصب',
            'year' => $year->year,
            'quarter' => $quarter ? Workspace::quarterName($quarter) : 'السنة كاملة',
            'quarter_no' => $quarter,
            'version' => $plan ? ($plan->current_version > 0 ? 'v' . $plan->current_version : 'مسودة غير معتمدة (v0)') : '—',
            'version_no' => $plan?->current_version,
            'status' => $plan ? ($official ? 'معتمدة' : 'مسودة') : 'تقرير شامل',
            'status_detail' => $plan?->statusLabel(),
            'issued_at' => now()->format('Y-m-d H:i'),
            'data_as_of' => Fmt::dt($asOf),
            'data_as_of_raw' => $asOf,
        ];
    }

    public function planStructure(Plan $plan, ?int $onlyQuarter = null): array
    {
        $plan->load(['objectives.indicators.targets', 'objectives.indicators.owner', 'projects.tasks.deferrals', 'owner', 'reviews.user']);
        $objectives = [];
        foreach ($plan->objectives as $o) {
            $inds = [];
            foreach ($o->indicators as $i) {
                $cum = 0;
                $qs = [];
                for ($q = 1; $q <= 4; $q++) {
                    $t = $i->targetFor($q);
                    $cum += $t ?? 0;
                    $qs[$q] = ['target' => $t, 'cum' => $i->kind === 'cumulative' ? $cum : null];
                }
                $inds[] = [
                    'name' => $i->name, 'definition' => $i->definition, 'unit' => $i->unit, 'kind' => $i->kindLabel(),
                    'direction' => $i->directionLabel() . ($i->direction === 'range' ? ' (' . Fmt::num($i->range_min) . ' – ' . Fmt::num($i->range_max) . ')' : ''),
                    'aggregation' => Indicator::AGGREGATIONS[$i->aggregation] ?? $i->aggregation,
                    'baseline' => $i->baseline, 'annual_target' => $i->annual_target, 'quarters' => $qs, 'weight' => $i->weight,
                    'data_source' => $i->data_source, 'verification' => $i->verification_method,
                    'frequency' => Indicator::FREQUENCIES[$i->frequency] ?? '—', 'owner' => $i->owner?->name, 'evidence' => $i->required_evidence,
                ];
            }
            $objectives[] = ['title' => $o->title, 'description' => $o->description, 'weight' => $o->weight, 'indicators' => $inds];
        }
        $projects = $plan->projects->map(fn (Project $p) => [
            'type' => $p->typeLabel(), 'name' => $p->name, 'responsible' => $p->responsible, 'starts_on' => Fmt::date($p->starts_on), 'ends_on' => Fmt::date($p->ends_on),
            'tasks' => $p->tasks->filter(fn ($t) => ! $onlyQuarter || $t->quarter == $onlyQuarter || $t->deferrals->contains('from_quarter', $onlyQuarter))
                ->map(fn ($t) => ['title' => $t->title, 'quarter' => $t->quarter, 'original_quarter' => $t->original_quarter, 'responsible' => $t->responsible,
                    'due_on' => Fmt::date($t->due_on), 'evidence' => $t->required_evidence, 'status' => $t->statusLabel()])->values()->all(),
        ])->all();

        return [
            'scope_description' => $plan->scope_description, 'overall_outcome' => $plan->overall_outcome, 'risks' => $plan->risks, 'resources' => $plan->resources,
            'owner' => $plan->owner?->name, 'objectives' => $objectives, 'projects' => $projects,
            'approvals' => $plan->reviews->whereIn('action', ['submit', 'return', 'recommend', 'approve', 'activate', 'amend', 'close'])->sortBy('id')
                ->map(fn ($r) => ['action' => $r->actionLabel(), 'by' => $r->user->name, 'at' => Fmt::dt($r->created_at), 'version' => $r->version_no, 'note' => $r->note])->values()->all(),
        ];
    }

    /** نتائج ربع: كما تُعرض في النظام (لقطة الإقفال للربع المغلق) */
    public function quarterResults(Plan $plan, int $q): array
    {
        $r = $this->results->plan($plan, $q);
        $r['tasks_list'] = ($r['source'] === 'snapshot' && isset($r['tasks_list'])) ? $r['tasks_list'] : app(QuarterService::class)->tasksForQuarter($plan, $q);

        return $r;
    }

    public function updatesRows(Plan $plan, ?int $indicatorId = null, ?int $quarter = null): array
    {
        return ProgressUpdate::where('plan_id', $plan->id)
            ->when($indicatorId, fn ($q) => $q->where('indicator_id', $indicatorId))
            ->when($quarter, fn ($q) => $q->where('quarter', $quarter))
            ->with(['indicator', 'task', 'creator', 'reviewer'])->orderBy('id')->get()
            ->map(fn ($u) => [
                'id' => $u->id, 'quarter' => $u->quarter, 'item' => $u->indicator?->name ?? $u->task?->title, 'type' => $u->indicator_id ? 'مؤشر' : 'مهمة',
                'actual' => $u->actual_value, 'participants' => $u->participants, 'achieved' => $u->achieved, 'not_achieved' => $u->not_achieved,
                'delay_reason' => $u->delay_reason, 'obstacles' => $u->obstacles, 'support' => $u->support_needed,
                'status' => $u->statusLabel() . ($u->is_adjustment ? ' (تسوية)' : ''), 'counted' => $u->status === 'approved' ? 'نعم' : 'لا',
                'by' => $u->creator?->name, 'at' => Fmt::dt($u->created_at), 'reviewer' => $u->reviewer?->name, 'reviewed_at' => Fmt::dt($u->reviewed_at), 'review_note' => $u->review_note,
            ])->all();
    }

    /** صفوف CSV/XLSX لمؤشرات الخطة ونتائجها في ربع */
    public function indicatorRows(array $res, ?int $onlyIndicator = null, ?int $onlyObjective = null): array
    {
        $rows = [];
        foreach ($res['objectives'] as $o) {
            if ($onlyObjective && $o['id'] !== $onlyObjective) continue;
            foreach ($o['indicators'] as $i) {
                if ($onlyIndicator && $i['id'] !== $onlyIndicator) continue;
                $p = $i['period'];
                $rows[] = [
                    'objective' => $o['title'], 'objective_weight' => $o['weight'], 'indicator' => $i['name'], 'unit' => $i['unit'], 'kind' => $i['kind_label'],
                    'weight' => round($i['effective_weight'] ?? 0, 2), 'baseline' => $i['baseline'], 'annual_target' => $i['annual']['target'],
                    'quarter_target' => $p['target'], 'compare_label' => $p['compare_label'], 'compare_target' => $p['compare_target'],
                    'actual' => $p['compare_actual'], 'gap' => $p['gap'] !== null ? round($p['gap'], 2) : null, 'achievement' => $p['achievement'] !== null ? round($p['achievement'], 1) : null,
                    'state' => $p['state_label'], 'status' => $i['status']['label'],
                    'annual_actual' => $i['annual']['actual'] !== null ? round($i['annual']['actual'], 2) : null,
                    'annual_achievement' => $i['annual']['achievement'] !== null ? round($i['annual']['achievement'], 1) : null,
                    'pending' => $p['pending_count'],
                ];
            }
        }

        return $rows;
    }

    public const INDICATOR_COLUMNS = [
        'objective' => 'الهدف', 'objective_weight' => 'وزن الهدف %', 'indicator' => 'المؤشر', 'unit' => 'الوحدة', 'kind' => 'النوع', 'weight' => 'وزن المؤشر %',
        'baseline' => 'خط الأساس', 'annual_target' => 'المستهدف السنوي', 'quarter_target' => 'مستهدف الربع (الفترة)', 'compare_label' => 'أساس المقارنة',
        'compare_target' => 'المستهدف المرحلي', 'actual' => 'الفعلي المعتمد', 'gap' => 'الفجوة (الفعلي - المستهدف)', 'achievement' => 'الإنجاز مقابل المستهدف المرحلي %',
        'state' => 'حالة البيانات', 'status' => 'الحالة', 'annual_actual' => 'الفعلي حتى تاريخه (سنوي)', 'annual_achievement' => 'الإنجاز مقابل المستهدف السنوي %', 'pending' => 'تحديثات بانتظار التحقق (غير محتسبة)',
    ];

    public const UPDATE_COLUMNS = [
        'id' => 'رقم التحديث', 'quarter' => 'الربع', 'type' => 'النوع', 'item' => 'المؤشر/المهمة', 'actual' => 'القيمة الفعلية', 'participants' => 'عدد المشاركين',
        'achieved' => 'ما تم إنجازه', 'not_achieved' => 'ما لم يتم', 'delay_reason' => 'سبب التأخير', 'obstacles' => 'العوائق', 'support' => 'الدعم المطلوب',
        'status' => 'حالة التحقق', 'counted' => 'محتسب في الإنجاز الرسمي', 'by' => 'أدخله', 'at' => 'تاريخ الإدخال', 'reviewer' => 'المراجع', 'reviewed_at' => 'تاريخ المراجعة', 'review_note' => 'ملاحظة المراجعة',
    ];
}
