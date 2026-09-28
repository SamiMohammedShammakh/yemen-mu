<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\CorrectiveAction;
use App\Models\Export;
use App\Models\Plan;
use App\Models\Position;
use App\Models\ProgressUpdate;
use App\Services\QuarterService;
use App\Services\Results;
use App\Support\Access;

class DashboardController extends Controller
{
    public function home()
    {
        return match ($this->ctx()->current['type'] ?? null) {
            'position' => redirect()->route('workspace'),
            'planning' => redirect()->route('planning.center'),
            'executive' => redirect()->route('executive'),
            'admin' => redirect()->route('admin.users.index'),
            default => view('dashboards.empty'),
        };
    }

    /** مساحة عمل المنصب */
    public function workspace()
    {
        $ctx = $this->ctx();
        $position = $ctx->position();
        if (! $position) {
            return redirect()->route('home');
        }
        $u = auth()->user();
        abort_unless(Access::holdsPosition($u, $position->id), 403);
        $q = $ctx->quarter;
        $plan = $ctx->year ? Plan::where('planning_year_id', $ctx->year->id)->where('position_id', $position->id)->with(['position', 'year.quarters', 'owner'])->first() : null;

        $data = compact('position', 'plan', 'q');
        if (! $plan) {
            $data['previousPlans'] = Plan::where('position_id', $position->id)->whereHas('year', fn ($y) => $y->where('year', '<', $ctx->year?->year ?? 0))->with('year')->get();

            return view('dashboards.workspace', $data);
        }
        $results = new Results();
        $data['results'] = $plan->objectives()->exists() ? $results->plan($plan, $q) : null;
        $data['quarterSummary'] = [];
        if ($data['results'] && $plan->isLocked()) {
            for ($k = 1; $k <= 4; $k++) {
                $r = $results->plan($plan, $k);
                $data['quarterSummary'][$k] = ['ach' => $r['period_achievement'], 'status' => $r['status'], 'source' => $r['source']];
            }
        }
        $data['tasks'] = app(QuarterService::class)->tasksForQuarter($plan, $q);
        $data['projects'] = $plan->projects()->withCount('tasks')->get();
        $data['returned'] = $plan->updates()->where('status', 'returned')->with(['indicator', 'task'])->latest()->limit(5)->get();
        $data['pending'] = $plan->updates()->where('status', 'pending')->with(['indicator', 'task'])->get();
        $data['required'] = collect($data['results']['objectives'] ?? [])->flatMap(fn ($o) => $o['indicators'])
            ->filter(fn ($i) => in_array($i['period']['state'], ['missing'], true))->values();
        $data['actions'] = $plan->correctiveActions()->where('status', 'open')->with('owner')->get();
        $data['notes'] = $plan->notes()->with(['author', 'replies.author'])->limit(5)->get();
        $data['attachments'] = $plan->attachments()->with('uploader')->limit(6)->get();
        $data['exports'] = Export::where('plan_id', $plan->id)->where('user_id', $u->id)->latest()->limit(5)->get();

        return view('dashboards.workspace', $data);
    }

    /** مركز التخطيط والمتابعة */
    public function planning()
    {
        abort_unless(Access::canReview(auth()->user()), 403);
        $ctx = $this->ctx();
        $q = $ctx->quarter;
        $plans = $ctx->year ? Plan::where('planning_year_id', $ctx->year->id)->with(['position', 'year.quarters', 'owner'])->get()->sortBy('position.sort') : collect();
        $results = new Results();
        $rows = [];
        $gaps = [];
        foreach ($plans as $p) {
            $r = $p->isLocked() && $p->objectives()->exists() ? $results->plan($p, $q) : null;
            $rows[] = ['plan' => $p, 'r' => $r];
            foreach ($r['objectives'] ?? [] as $o) {
                foreach ($o['indicators'] as $i) {
                    if (in_array($i['status']['key'], ['late', 'follow_up'], true) || in_array($i['period']['state'], ['missing', 'pending'], true)) {
                        $gaps[] = ['plan' => $p, 'i' => $i];
                    }
                }
            }
        }
        $planIds = $plans->pluck('id');
        $pending = ProgressUpdate::whereIn('plan_id', $planIds)->where('status', 'pending')->with(['plan.position', 'indicator', 'task', 'attachments'])->oldest()->get();
        $actions = CorrectiveAction::whereIn('plan_id', $planIds)->where('status', 'open')->with(['plan.position', 'owner'])->orderBy('due_on')->get();
        $toReview = $plans->where('status', 'submitted');
        $crs = ChangeRequest::whereIn('plan_id', $planIds)->where('status', 'pending')->with('plan.position')->get();
        $missingPositions = Position::whereNotIn('id', $plans->pluck('position_id'))->orderBy('sort')->get();

        return view('dashboards.planning', compact('rows', 'gaps', 'pending', 'actions', 'toReview', 'crs', 'q', 'missingPositions'));
    }

    /** لوحة الرئيس والإدارة التنفيذية */
    public function executive()
    {
        abort_unless(Access::canSeeExecutiveBoard(auth()->user()), 403);
        $ctx = $this->ctx();
        $q = $ctx->quarter;
        $plans = $ctx->year ? Plan::where('planning_year_id', $ctx->year->id)->with(['position', 'year.quarters'])->get()->sortBy('position.sort') : collect();
        $results = new Results();
        $rows = [];
        foreach ($plans as $p) {
            $rows[] = ['plan' => $p, 'r' => $p->isLocked() && $p->objectives()->exists() ? $results->plan($p, $q) : null];
        }
        $measured = collect($rows)->filter(fn ($x) => $x['r'] && $x['r']['period_achievement'] !== null);
        $org = [
            'period' => $measured->count() ? $measured->avg(fn ($x) => $x['r']['period_achievement']) : null,
            'annual' => ($ann = collect($rows)->filter(fn ($x) => $x['r'] && $x['r']['annual_achievement'] !== null))->count() ? $ann->avg(fn ($x) => $x['r']['annual_achievement']) : null,
            'annual_count' => $ann->count(),
            'plans' => $plans->count(),
            'active' => $plans->whereIn('status', ['approved', 'active'])->count(),
            'measured' => $measured->count(),
        ];
        $late = collect($rows)->flatMap(fn ($x) => collect($x['r']['objectives'] ?? [])->flatMap(fn ($o) => $o['indicators'])
            ->where('status.key', 'late')->map(fn ($i) => ['plan' => $x['plan'], 'i' => $i]))->values();
        $decisions = [
            'approve' => $plans->where('status', 'recommended'),
            'crs' => ChangeRequest::whereIn('plan_id', $plans->pluck('id'))->where('status', 'pending')->with('plan.position')->get(),
        ];
        $calc = $results->calculator();

        return view('dashboards.executive', compact('rows', 'org', 'late', 'decisions', 'q', 'calc'));
    }
}
