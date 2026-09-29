<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanValidator;
use App\Services\PlanWorkflow;
use App\Services\Results;
use App\Support\Access;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function review(Plan $plan)
    {
        $this->canView($plan);
        $plan->load(['position', 'year.quarters', 'owner', 'objectives.indicators.targets', 'objectives.indicators.owner', 'objectives.strategicGoal', 'projects.tasks']);
        $errors_list = (new PlanValidator())->errors($plan);
        $actions = app(PlanWorkflow::class)->availableActions(auth()->user(), $plan);
        $reviews = $plan->reviews()->with(['user', 'delegation'])->get();
        $delegation = Access::isPresident(auth()->user()) ? null : Access::activeDelegation(auth()->user());

        return view('plans.review', compact('plan', 'errors_list', 'actions', 'reviews', 'delegation'));
    }

    public function apply(Request $request, Plan $plan, string $action)
    {
        $this->canView($plan);
        app(PlanWorkflow::class)->apply($request->user(), $plan, $action, $request->input('note'));
        $msg = [
            'submit' => 'أُرسلت الخطة لمراجعة التخطيط.', 'return' => 'أُعيدت الخطة للتعديل.', 'recommend' => 'رُفعت توصية باعتماد الخطة.',
            'approve' => 'اعتُمدت الخطة وحُفظت النسخة v' . $plan->current_version . '.', 'activate' => 'فُعّلت الخطة.', 'close' => 'أُغلقت الخطة.',
            'note' => 'أُضيفت ملاحظة المراجعة.',
        ][$action];

        return redirect()->route('plans.review', $plan)->with('ok', $msg);
    }
}
