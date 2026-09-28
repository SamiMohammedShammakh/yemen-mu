<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * ينسخ هيكل خطة سنة سابقة إلى مسودة سنة جديدة:
 * الأهداف والأوزان والمؤشرات ومستهدفاتها والمشاريع والمهام والمخاطر والموارد.
 * لا يُنسخ الإنجاز ولا الأدلة ولا الاعتمادات ولا حالة الخطة.
 */
class PlanCopier
{
    public function copy(Plan $source, PlanningYear $target, User $by): Plan
    {
        return DB::transaction(function () use ($source, $target, $by) {
            $source->load(['objectives.indicators.targets', 'projects', 'tasks']);
            $shift = $target->year - $source->year->year;
            $plan = Plan::create([
                'planning_year_id' => $target->id,
                'position_id' => $source->position_id,
                'owner_user_id' => $by->id,
                'scope_description' => $source->scope_description,
                'overall_outcome' => $source->overall_outcome,
                'risks' => $source->risks,
                'resources' => $source->resources,
                'status' => 'draft',
                'current_version' => 0,
                'copied_from_plan_id' => $source->id,
            ]);
            $objMap = [];
            foreach ($source->objectives as $o) {
                $no = $plan->objectives()->create($o->only(['title', 'description', 'weight', 'sort']));
                $objMap[$o->id] = $no->id;
                foreach ($o->indicators as $i) {
                    $ni = $no->indicators()->create(['plan_id' => $plan->id] + $i->only([
                        'name', 'definition', 'unit', 'direction', 'range_min', 'range_max', 'kind', 'aggregation', 'baseline',
                        'annual_target', 'data_source', 'verification_method', 'frequency', 'required_evidence', 'owner_user_id', 'weight', 'sort',
                    ]));
                    foreach ($i->targets as $t) {
                        $ni->targets()->create(['quarter' => $t->quarter, 'target' => $t->target]);
                    }
                }
            }
            $projMap = [];
            foreach ($source->projects as $p) {
                $np = $plan->projects()->create([
                    'objective_id' => $objMap[$p->objective_id] ?? null,
                    'starts_on' => $p->starts_on?->copy()->addYears($shift),
                    'ends_on' => $p->ends_on?->copy()->addYears($shift),
                ] + $p->only(['type', 'name', 'description', 'responsible', 'owner_user_id', 'resources']));
                $projMap[$p->id] = $np->id;
            }
            foreach ($source->tasks as $t) {
                $plan->tasks()->create([
                    'project_id' => $projMap[$t->project_id] ?? null,
                    'title' => $t->title, 'description' => $t->description, 'responsible' => $t->responsible,
                    'owner_user_id' => $t->owner_user_id, 'quarter' => $t->original_quarter, 'original_quarter' => $t->original_quarter,
                    'due_on' => $t->due_on?->copy()->addYears($shift), 'required_evidence' => $t->required_evidence, 'status' => 'planned',
                ]);
            }
            Audit::log('plan.copy_structure', $plan, null, ['from_plan' => $source->id, 'from_year' => $source->year->year]);

            return $plan;
        });
    }
}
