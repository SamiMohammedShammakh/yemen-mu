<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\User;
use App\Services\ChangeRequestService;
use App\Services\PlanCopier;
use App\Services\PlanValidator;
use App\Services\PlanWorkflow;
use App\Services\QuarterService;
use App\Services\Results;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        $year = $this->ctx()->year;
        $plans = $year ? Access::visiblePlans($u)->where('planning_year_id', $year->id)->with(['position', 'owner'])->get()->sortBy('position.sort') : collect();

        return view('plans.index', compact('plans'));
    }

    /** إنشاء خطة جديدة لمنصب المستخدم في السنة المختارة (أو نسخ هيكل سنة سابقة) */
    public function store(Request $request)
    {
        $u = $request->user();
        $data = $request->validate(['position_id' => 'required|exists:positions,id', 'planning_year_id' => 'required|exists:planning_years,id']);
        abort_unless(Access::holdsPosition($u, (int) $data['position_id']), 403, 'إنشاء الخطة متاح لصاحب المنصب فقط.');
        $existing = Plan::where($data)->first();
        if ($existing) {
            return redirect()->route('plans.show', $existing);
        }
        $plan = Plan::create($data + ['owner_user_id' => $u->id, 'status' => 'draft']);
        Audit::log('plan.create', $plan, null, $data);

        return redirect()->route('plans.edit', $plan)->with('ok', 'أُنشئت مسودة الخطة. أكمل نطاق العمل والأهداف والمؤشرات.');
    }

    public function copy(Request $request, Plan $plan)
    {
        $this->canView($plan);
        $u = $request->user();
        abort_unless(Access::isPlanOwner($u, $plan), 403);
        $target = PlanningYear::findOrFail($request->integer('planning_year_id'));
        abort_if($target->year <= $plan->year->year, 422, 'اختر سنة لاحقة لسنة الخطة المصدر.');
        if ($ex = Plan::where('planning_year_id', $target->id)->where('position_id', $plan->position_id)->first()) {
            return redirect()->route('plans.show', $ex)->withErrors(['copy' => 'توجد خطة لهذا المنصب في سنة ' . $target->year . ' مسبقًا.']);
        }
        $new = app(PlanCopier::class)->copy($plan, $target, $u);
        session(['year_id' => $target->id]);

        return redirect()->route('plans.show', $new)->with('ok', 'نُسخ هيكل خطة ' . $plan->year->year . ' إلى مسودة ' . $target->year . ' دون الإنجاز أو الأدلة أو الاعتمادات.');
    }

    public function show(Request $request, Plan $plan)
    {
        $this->canView($plan);
        $u = $request->user();
        $plan->load(['position', 'year.quarters', 'owner', 'objectives.indicators.targets', 'projects.tasks']);
        $q = $this->selectedQuarter();
        $tab = $request->query('tab', 'overview');
        $results = $plan->objectives->isNotEmpty() ? (new Results())->plan($plan, $q) : null;
        $data = compact('plan', 'q', 'tab', 'results');
        $data['actions'] = app(PlanWorkflow::class)->availableActions($u, $plan);
        $data['canEdit'] = Access::canEditPlan($u, $plan);
        $data['canUpdate'] = Access::canPostUpdate($u, $plan);
        $data['canReview'] = Access::canReview($u);
        $data['canRequestChange'] = app(ChangeRequestService::class)->canRequest($u, $plan);
        $data['errors_list'] = $plan->isEditable() ? (new PlanValidator())->errors($plan) : [];

        switch ($tab) {
            case 'tasks':
                $data['tasks'] = $plan->tasks()->with(['project', 'deferrals', 'owner'])->get();
                $data['users'] = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
                break;
            case 'updates':
                $data['updates'] = $plan->updates()->with(['indicator', 'task', 'creator', 'reviewer', 'attachments'])->paginate(30)->withQueryString();
                break;
            case 'notes':
                $data['notes'] = $plan->notes()->with(['author', 'replies.author'])->get();
                $data['correctives'] = $plan->correctiveActions()->with(['owner', 'indicator', 'task', 'creator'])->get();
                $data['users'] = Access::usersHoldingPosition($plan->position_id);
                break;
            case 'files':
                $data['files'] = $plan->attachments()->with(['uploader', 'progressUpdate.indicator', 'progressUpdate.task'])->get();
                break;
            case 'downloads':
                $data['exports'] = Export::where('plan_id', $plan->id)->when(! Access::hasGlobalView($u), fn ($q) => $q->where('user_id', $u->id))->with('user')->latest()->limit(30)->get();
                break;
            case 'history':
                $data['versions'] = $plan->versions()->with('approver')->get();
                $data['reviews'] = $plan->reviews()->with(['user', 'delegation'])->get();
                $data['crs'] = $plan->changeRequests()->with('requester')->get();
                $data['snapshots'] = $plan->snapshots()->with('creator')->orderBy('quarter')->orderBy('revision')->get();
                break;
        }
        if (Access::isPlanOwner($u, $plan)) {
            $data['laterYears'] = PlanningYear::where('year', '>', $plan->year->year)->orderBy('year')->get();
        }

        return view('plans.show', $data);
    }

    public function edit(Plan $plan)
    {
        $this->canEdit($plan);
        $plan->load(['objectives.indicators', 'projects', 'position', 'year']);
        $errors_list = (new PlanValidator())->errors($plan);

        return view('plans.edit', compact('plan', 'errors_list'));
    }

    public function update(Request $request, Plan $plan)
    {
        $this->canEdit($plan);
        $data = $request->validate([
            'scope_description' => 'nullable|string|max:5000',
            'overall_outcome' => 'nullable|string|max:5000',
            'risks' => 'nullable|string|max:5000',
            'resources' => 'nullable|string|max:5000',
        ]);
        $old = $plan->only(array_keys($data));
        $plan->update($data);
        Audit::log('plan.update', $plan, $old, $data);

        return back()->with('ok', 'حُفظت بيانات الخطة.');
    }

    public function quarter(Plan $plan, int $q)
    {
        $this->canView($plan);
        abort_unless($q >= 1 && $q <= 4, 404);
        $plan->load(['position', 'year.quarters']);
        $res = new Results();
        $results = $plan->objectives()->exists() ? $res->plan($plan, $q) : null;
        $tasks = ($results['source'] ?? '') === 'snapshot' && isset($results['tasks_list']) ? $results['tasks_list'] : app(QuarterService::class)->tasksForQuarter($plan, $q);
        $quarter = $plan->year->quarters->firstWhere('number', $q);

        return view('plans.quarter', compact('plan', 'q', 'results', 'tasks', 'quarter'));
    }

    public function version(Plan $plan, int $no)
    {
        $this->canView($plan);
        $version = $plan->versions()->where('version_no', $no)->with('approver')->firstOrFail();
        $plan->load(['position', 'year']);

        return view('plans.version', compact('plan', 'version'));
    }
}
