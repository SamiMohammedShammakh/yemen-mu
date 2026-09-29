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

    /** الهدف الاستراتيجي يجب أن يكون فعّالًا ويغطي سنة الخطة (أو هو الربط الحالي نفسه) */
    private function checkGoal(Plan $plan, ?int $goalId, ?int $current = null): void
    {
        if (! $goalId || $goalId === $current) {
            return;
        }
        $ok = \App\Models\StrategicGoal::whereKey($goalId)->usableFor($plan->year->year)->exists();
        if (! $ok) {
            throw \Illuminate\Validation\ValidationException::withMessages(['strategic_goal_id' => 'الهدف الاستراتيجي المختار مؤرشف أو لا يغطي سنة الخطة.']);
        }
    }

    private const OBJ_RULES = ['title' => 'required|string|max:255', 'description' => 'nullable|string', 'weight' => 'nullable|numeric|min:0|max:100', 'strategic_goal_id' => 'nullable|integer|exists:strategic_goals,id'];

    public function storeObjective(Request $r, Plan $plan)
    {
        $this->canEdit($plan);
        $d = $r->validate(self::OBJ_RULES, [], ['title' => 'عنوان الهدف', 'weight' => 'الوزن', 'strategic_goal_id' => 'الهدف الاستراتيجي']);
        $this->checkGoal($plan, $d['strategic_goal_id'] ?? null);
        $o = $plan->objectives()->create($d + ['sort' => $plan->objectives()->count()]);
        Audit::log('objective.create', $o, null, ['ref' => $o->ref] + $d);

        return $this->back($plan, 'أُضيف الهدف ' . $o->ref . '. أضف له مؤشرًا قابلًا للقياس.', '#obj-' . $o->id);
    }

    public function updateObjective(Request $r, Objective $objective)
    {
        $this->canEdit($objective->plan);
        $d = $r->validate(self::OBJ_RULES, [], ['title' => 'عنوان الهدف', 'weight' => 'الوزن', 'strategic_goal_id' => 'الهدف الاستراتيجي']);
        $d['strategic_goal_id'] ??= null;
        $this->checkGoal($objective->plan, $d['strategic_goal_id'], $objective->strategic_goal_id);
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

    private const PROJECT_LABELS = ['name' => 'الاسم', 'ends_on' => 'تاريخ الانتهاء', 'parent_id' => 'المبادرة الأم', 'type' => 'النوع'];

    private function projectRules(): array
    {
        return ['type' => 'required|in:initiative,project,activity', 'name' => 'required|string|max:255', 'description' => 'nullable|string',
            'objective_id' => 'nullable|integer', 'parent_id' => 'nullable|integer', 'responsible' => 'nullable|string|max:255', 'owner_user_id' => 'nullable|exists:users,id',
            'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on', 'resources' => 'nullable|string'];
    }

    /**
     * التسلسل: مبادرة/مشروع (مستوى أول) ← نشاط (مستوى ثانٍ فقط) ← مهام.
     * النشاط يرث هدف مبادرته، ولا يمكن إنشاء نشاط تحت نشاط.
     */
    private function normalizeHierarchy(Plan $plan, array $d): array
    {
        if (! empty($d['parent_id'])) {
            $parent = $plan->projects()->whereKey($d['parent_id'])->first();
            if (! $parent || $parent->parent_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'يُضاف النشاط تحت مبادرة أو مشروع من الخطة نفسها فقط.']);
            }
            $d['type'] = 'activity';
            $d['objective_id'] = $parent->objective_id;
        } else {
            $d['parent_id'] = null;
            if ($d['type'] === 'activity') {
                throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'اختر المبادرة التي يتبعها النشاط.']);
            }
        }
        abort_if(! empty($d['objective_id']) && ! $plan->objectives()->whereKey($d['objective_id'])->exists(), 422);

        return $d;
    }

    public function storeProject(Request $r, Plan $plan)
    {
        $this->canEdit($plan);
        $d = $this->normalizeHierarchy($plan, $r->validate($this->projectRules(), [], self::PROJECT_LABELS));
        $p = $plan->projects()->create($d);
        Audit::log($p->isActivity() ? 'activity.create' : 'project.create', $p, null, ['ref' => $p->ref] + $d);

        return $this->back($plan, 'أُضيف ' . $p->typeLabel() . ' ' . $p->ref . '.', '#projects');
    }

    public function updateProject(Request $r, Project $project)
    {
        $plan = $project->plan;
        $this->canEdit($plan);
        $d = $r->validate($this->projectRules(), [], self::PROJECT_LABELS);
        // المستوى لا يتغير بعد الإنشاء: لا يتحول النشاط إلى مبادرة ولا العكس، ويحتفظ برقمه المرجعي
        $d['parent_id'] = $project->parent_id;
        $d['type'] = $project->isActivity() ? 'activity' : ($d['type'] === 'activity' ? $project->type : $d['type']);
        $d = $this->normalizeHierarchy($plan, $d);
        $old = $project->only(array_keys($d));
        $project->update($d);
        if (! $project->isActivity() && array_key_exists('objective_id', $d)) {
            $project->activities()->update(['objective_id' => $project->objective_id]);
        }
        $changed = array_keys(array_diff_assoc(array_map('strval', $project->only(array_keys($d))), array_map('strval', $old)));
        if ($changed) {
            Audit::log($project->isActivity() ? 'activity.update' : 'project.update', $project, array_intersect_key($old, array_flip($changed)), $project->only($changed) + ['ref' => $project->ref]);
        }

        return $this->back($plan, 'حُفظ ' . $project->typeLabel() . ' ' . $project->ref . '.', '#projects');
    }

    public function destroyProject(Project $project)
    {
        $plan = $project->plan;
        $this->canEdit($plan);
        if ($project->activities()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['project' => 'احذف أنشطة «' . $project->name . '» أولًا، أو انقل مهامها.']);
        }
        Audit::log($project->isActivity() ? 'activity.delete' : 'project.delete', $project, $project->toArray(), null);
        $project->delete();

        return $this->back($plan, 'حُذف ' . $project->typeLabel() . ' ' . $project->ref . '، وبقيت مهامه دون ارتباط. رقمه المرجعي لن يُعاد استخدامه.', '#projects');
    }

    public function showProject(Project $project)
    {
        $plan = $project->plan;
        $this->canView($plan);
        $project->load(['tasks.deferrals', 'tasks.owner', 'objective.strategicGoal', 'owner', 'parent', 'activities.tasks.deferrals', 'activities.tasks.owner', 'activities.owner']);

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
        abort_if(! empty($d['project_id']) && ! $plan->projects()->whereKey($d['project_id'])->exists(), 422);
        $t = $plan->tasks()->create($d + ['original_quarter' => $d['quarter'], 'status' => 'planned']);
        Audit::log('task.create', $t, null, ['ref' => $t->ref] + $d);

        return $this->back($plan, 'أُضيفت المهمة.', '#projects');
    }

    public function updateTask(Request $r, Task $task)
    {
        $this->canEdit($task->plan);
        $d = $r->validate($this->taskRules());
        abort_if(! empty($d['project_id']) && ! $task->plan->projects()->whereKey($d['project_id'])->exists(), 422);
        $old = $task->only(array_keys($d));
        $task->update($d + ['original_quarter' => $d['quarter']]);
        Audit::log('task.update', $task, $old, ['ref' => $task->ref] + $d);

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
