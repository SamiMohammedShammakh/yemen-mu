<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SearchController;
use App\Models\Export;
use App\Models\Indicator;
use App\Models\Plan;
use App\Services\Results;
use App\Support\Access;
use Illuminate\Http\Request;

/**
 * واجهات JSON. كل مسار يطبق نفس فحص الصلاحية المستخدم في الصفحات (Access)، ويعيد 403 لخطة خارج النطاق.
 */
class ApiController extends Controller
{
    public function me(Request $r)
    {
        $u = $r->user();

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
            'positions' => Access::positions($u)->map->only(['id', 'code', 'name'])->values(),
            'global_view' => Access::hasGlobalView($u),
            'executive_member' => Access::isExecutive($u),
            'can_review' => Access::canReview($u),
            'can_approve' => Access::canApprove($u),
            'workspaces' => array_values(array_map(fn ($w) => ['key' => $w['key'], 'label' => $w['label']], Access::workspaces($u))),
        ];
    }

    public function plans(Request $r)
    {
        $q = Access::visiblePlans($r->user())->with(['position', 'year']);
        if ($year = $r->integer('year')) {
            $q->whereHas('year', fn ($y) => $y->where('year', $year));
        }

        return ['data' => $q->get()->map(fn (Plan $p) => $this->planSummary($p))->values()];
    }

    private function planSummary(Plan $p): array
    {
        return ['id' => $p->id, 'year' => $p->year->year, 'position' => ['id' => $p->position_id, 'name' => $p->position->name],
            'status' => $p->status, 'status_label' => $p->statusLabel(), 'version' => $p->current_version, 'updated_at' => $p->updated_at?->toIso8601String()];
    }

    public function plan(Plan $plan)
    {
        $this->canView($plan);
        $plan->load(['objectives.indicators.targets', 'projects.tasks', 'position', 'year']);

        return $this->planSummary($plan) + [
            'scope_description' => $plan->scope_description, 'overall_outcome' => $plan->overall_outcome,
            'risks' => $plan->risks, 'resources' => $plan->resources,
            'objectives' => $plan->objectives->map(fn ($o) => [
                'id' => $o->id, 'title' => $o->title, 'weight' => $o->weight,
                'indicators' => $o->indicators->map(fn ($i) => $i->only(['id', 'name', 'unit', 'kind', 'direction', 'aggregation', 'baseline', 'annual_target', 'weight', 'frequency', 'data_source'])
                    + ['quarter_targets' => $i->targets->pluck('target', 'quarter')]),
            ]),
            'projects' => $plan->projects->map(fn ($p) => $p->only(['id', 'type', 'name']) + ['tasks' => $p->tasks->map->only(['id', 'title', 'quarter', 'status', 'due_on'])]),
        ];
    }

    public function results(Request $r, Plan $plan)
    {
        $this->canView($plan);
        $q = max(1, min(4, $r->integer('quarter', $this->ctx()->quarter)));

        return (new Results())->plan($plan, $q);
    }

    public function indicator(Request $r, Indicator $indicator)
    {
        $plan = $indicator->plan;
        $this->canView($plan);
        $res = new Results();

        return ['indicator' => $indicator->only(['id', 'name', 'unit', 'kind', 'annual_target']), 'quarters' => $res->indicatorQuarterRows($plan, $indicator),
            'updates' => $indicator->updates()->get(['id', 'quarter', 'actual_value', 'participants', 'status', 'created_at', 'reviewed_at'])];
    }

    public function search(Request $r)
    {
        $res = SearchController::run($r->user(), (string) $r->query('q', ''));

        return collect($res)->map(fn ($items) => $items->map(fn ($m) => ['id' => $m->id, 'title' => $m->title ?? $m->name, 'plan_id' => $m->plan_id, 'position' => $m->plan->position->name, 'year' => $m->plan->year->year]))->all();
    }

    public function exports(Request $r)
    {
        $u = $r->user();
        $q = Export::with('plan.position')->latest();
        if (! Access::hasGlobalView($u)) {
            $q->where('user_id', $u->id);
        }

        return ['data' => $q->limit(50)->get()->map(fn ($e) => ['uuid' => $e->uuid, 'scope' => $e->scope, 'format' => $e->format, 'file' => $e->file_name,
            'version' => $e->plan_version_no, 'plan_status' => $e->plan_status, 'created_at' => $e->created_at->toIso8601String(), 'download' => route('exports.download', $e)])];
    }
}
