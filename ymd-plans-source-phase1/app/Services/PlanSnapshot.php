<?php

namespace App\Services;

use App\Models\Plan;

class PlanSnapshot
{
    /** لقطة كاملة لهيكل الخطة (بدون إنجاز) تُحفظ مع كل نسخة معتمدة */
    public static function make(Plan $plan): array
    {
        $plan->load(['objectives.strategicGoal', 'objectives.indicators.targets', 'projects.tasks', 'position', 'year', 'owner']);

        return [
            'plan' => $plan->only(['id', 'ref', 'scope_description', 'overall_outcome', 'risks', 'resources', 'status']),
            'position' => $plan->position->name,
            'year' => $plan->year->year,
            'owner' => $plan->owner?->name,
            'objectives' => $plan->objectives->map(fn ($o) => [
                'id' => $o->id, 'ref' => $o->ref, 'strategic_goal_id' => $o->strategic_goal_id, 'strategic_goal' => $o->strategicGoal?->label(), 'title' => $o->title, 'description' => $o->description, 'weight' => $o->weight,
                'indicators' => $o->indicators->map(fn ($i) => $i->only([
                    'id', 'name', 'definition', 'unit', 'direction', 'range_min', 'range_max', 'kind', 'aggregation', 'baseline',
                    'annual_target', 'data_source', 'verification_method', 'frequency', 'required_evidence', 'owner_user_id', 'weight',
                ]) + ['targets' => $i->targets->pluck('target', 'quarter')->all()])->all(),
            ])->all(),
            'projects' => $plan->projects->map(fn ($p) => $p->only(['id', 'ref', 'parent_id', 'type', 'name', 'description', 'responsible', 'starts_on', 'ends_on', 'objective_id'])
                + ['tasks' => $p->tasks->map(fn ($t) => $t->only(['id', 'title', 'responsible', 'quarter', 'due_on', 'required_evidence']))->all()])->all(),
            'loose_tasks' => $plan->tasks()->whereNull('project_id')->get()->map(fn ($t) => $t->only(['id', 'title', 'responsible', 'quarter', 'due_on']))->all(),
        ];
    }
}
