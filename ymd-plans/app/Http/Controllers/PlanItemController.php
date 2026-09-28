<?php

namespace App\Http\Controllers;

use App\Models\Objective;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Task;
use App\Services\QuarterService;
use App\Services\Results;
use App\Support\Audit;
use Illuminate\Http\Request;

/** الأهداف والمشاريع والمهام */
class PlanItemController extends Controller
{
    private function back(Plan $plan, string $msg, string $anchor = '')
    {
        return redirect(route('plans.edit', $plan) . $anchor)->with('ok', $msg);
    }

    public function storeObjective(Request $r, Plan $plan)
    {
        $this->canEdit($plan);
        $d = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string', 'weight' => 'nullable|numeric|min:0|max:100'], [], ['title' => 'عنوان الهدف', 'weight' => 'الوزن']);
        $o = $plan->objectives()->create($d + ['sort' => $plan->objectives()->count()]);
        Audit::log('objective.create', $o, null, $d);

        return $this->back($plan, 'أُضيف الهدف. أضف له مؤشرًا قابلًا للقياس.', '#obj-' . $o->id);
    }

    public function updateObjective(Request $r, Objective $objective)
    {
        $this->canEdit($objective->plan);
        $d = $r->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string', 'weight' => 'nullable|numeric|min:0|max:100']);
        $old = $objective->only(array_keys($d));
        $objective->update($d);
        Audit::log('objective.update', $objective, $old, $d);

        return $this->back($objective->plan, 'حُفظ الهدف.', '#obj-' . $objective->id);
    }

    public function destroyObjective(Objective $objective)
    {
        $plan = $objective->plan;
        $this->canEdit($plan);
        Audit::log('objective.delete', $objective, $objective->toArray(), null);
        $objective->delete();

        return $this->back($plan, 'حُذف الهدف ومؤشراته.');
    }

    public function showObjective(Objective $objective)
    {
        $plan = $objective->plan;
        $this->canView($plan);
        $q = $this->selectedQuarter();
        $result = (new Results())->findObjective((new Results())->plan($plan, $q), $objective->id);

        return view('plans.objective', compact('plan', 'objective', 'q', 'result'));
    }

    private function projectRules(): array
    {
        return ['type' => 'required|in:initiative,project', 'name' => 'required|string|max:255', 'description' => 'nullable|string',
            'objective_id' => 'nullable|integer', 'responsible' => 'nullable|string|max:255', 'owner_user_id' => 'nullable|exists:users,id',
            'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on', 'resources' => 'nullable|string'];
    }

    public function storeProject(Request $r, Plan $plan)
    {
        $this->canEdit($plan);
        $d = $r->validate($this->projectRules(), [], ['name' => 'اسم المشروع', 'ends_on' => 'تاريخ الانتهاء']);
        abort_if($d['objective_id'] && ! $plan->objectives()->whereKey($d['objective_id'])->exists(), 422);
        $p = $plan->projects()->create($d);
        Audit::log('project.create', $p, null, $d);

        return $this->back($plan, 'أُضيف المشروع/المبادرة.', '#projects');
    }

    public function updateProject(Request $r, Project $project)
    {
        $this->canEdit($project->plan);
        $d = $r->validate($this->projectRules());
        abort_if($d['objective_id'] && ! $project->plan->objectives()->whereKey($d['objective_id'])->exists(), 422);
        $old = $project->only(array_keys($d));
        $project->update($d);
        Audit::log('project.update', $project, $old, $d);

        return $this->back($project->plan, 'حُفظ المشروع.', '#projects');
    }

    public function destroyProject(Project $project)
    {
        $plan = $project->plan;
        $this->canEdit($plan);
        Audit::log('project.delete', $project, $project->toArray(), null);
        $project->delete();

        return $this->back($plan, 'حُذف المشروع.', '#projects');
    }

    public function showProject(Project $project)
    {
        $plan = $project->plan;
        $this->canView($plan);
        $project->load(['tasks.deferrals', 'tasks.owner', 'objective']);

        return view('plans.project', compact('plan', 'project'));
    }

    private function taskRules(): array
    {
        return ['title' => 'required|string|max:255', 'description' => 'nullable|string', 'project_id' => 'nullable|integer',
            'responsible' => 'nullable|string|max:255', 'owner_user_id' => 'nullable|exists:users,id', 'quarter' => 'required|integer|between:1,4',
            'due_on' => 'nullable|date', 'required_evidence' => 'nullable|string'];
    }

    public function storeTask(Request $r, Plan $plan)
    {
        $this->canEdit($plan);
        $d = $r->validate($this->taskRules(), [], ['title' => 'عنوان المهمة', 'quarter' => 'الربع']);
        abort_if($d['project_id'] && ! $plan->projects()->whereKey($d['project_id'])->exists(), 422);
        $t = $plan->tasks()->create($d + ['original_quarter' => $d['quarter'], 'status' => 'planned']);
        Audit::log('task.create', $t, null, $d);

        return $this->back($plan, 'أُضيفت المهمة.', '#projects');
    }

    public function updateTask(Request $r, Task $task)
    {
        $this->canEdit($task->plan);
        $d = $r->validate($this->taskRules());
        abort_if($d['project_id'] && ! $task->plan->projects()->whereKey($d['project_id'])->exists(), 422);
        $old = $task->only(array_keys($d));
        $task->update($d + ['original_quarter' => $d['quarter']]);
        Audit::log('task.update', $task, $old, $d);

        return $this->back($task->plan, 'حُفظت المهمة.', '#projects');
    }

    public function destroyTask(Task $task)
    {
        $plan = $task->plan;
        $this->canEdit($plan);
        Audit::log('task.delete', $task, $task->toArray(), null);
        $task->delete();

        return $this->back($plan, 'حُذفت المهمة.', '#projects');
    }
}
