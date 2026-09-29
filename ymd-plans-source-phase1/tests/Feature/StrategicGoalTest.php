<?php

namespace Tests\Feature;

use App\Models\Objective;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\StrategicGoal;
use App\Services\PlanValidator;
use Tests\TestCase;

/** المرحلة الأولى — الأهداف الاستراتيجية وربط الخطط بها */
class StrategicGoalTest extends TestCase
{
    private function makeGoal(string $who = Position::PLANNING, array $over = []): StrategicGoal
    {
        $this->as($who)->post(route('strategic-goals.store'), $over + ['title' => 'تمكين المهندسين مهنيًا', 'description' => 'وصف', 'from_year' => 2026, 'to_year' => 2028])
            ->assertSessionHasNoErrors();

        return StrategicGoal::latest('id')->first();
    }

    public function test_president_and_planning_create_goals_with_sequential_refs_and_audit(): void
    {
        $a = $this->makeGoal(Position::PLANNING);
        $b = $this->makeGoal(Position::PRESIDENT, ['title' => 'استدامة الموارد']);
        $this->assertSame('SG-01', $a->ref);
        $this->assertSame('SG-02', $b->ref);
        $this->assertDatabaseHas('audit_logs', ['action' => 'strategic_goal.create', 'entity_id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'strategic_goal.create', 'entity_id' => $b->id, 'user_id' => $this->user(Position::PRESIDENT)->id]);
    }

    public function test_other_roles_cannot_manage_goals(): void
    {
        $g = $this->makeGoal();
        foreach ([Position::ENGINEERING, Position::VICE_PRESIDENT, 'admin'] as $who) {
            $this->as($who);
            $this->post(route('strategic-goals.store'), ['title' => 'x'])->assertForbidden();
            $this->put(route('strategic-goals.update', $g), ['title' => 'x'])->assertForbidden();
            $this->post(route('strategic-goals.status', $g), ['status' => 'archived'])->assertForbidden();
            $this->delete(route('strategic-goals.destroy', $g))->assertForbidden();
        }
        // أصحاب المناصب يطّلعون، ومدير النظام التقني (بلا منصب) لا
        $this->as(Position::ENGINEERING)->get(route('strategic-goals.index'))->assertOk()->assertSee('SG-01');
        $this->get(route('strategic-goals.show', $g))->assertOk();
        $this->as('admin')->get(route('strategic-goals.index'))->assertForbidden();
    }

    public function test_restricted_user_sees_only_own_linked_objectives(): void
    {
        $g = $this->makeGoal();
        $eng = $this->plan(Position::ENGINEERING)->objectives()->first();
        $med = $this->plan(Position::MEDIA)->objectives()->first();
        Objective::whereIn('id', [$eng->id, $med->id])->update(['strategic_goal_id' => $g->id]);

        $this->as(Position::ENGINEERING)->get(route('strategic-goals.show', $g))->assertOk()->assertSee($eng->title)->assertDontSee($med->title);
        $this->as(Position::PLANNING)->get(route('strategic-goals.show', $g))->assertSee($eng->title)->assertSee($med->title);
    }

    public function test_linking_objective_in_draft_plan_and_submission_requires_link(): void
    {
        $y = PlanningYear::where('year', 2027)->first();
        $g = $this->makeGoal();
        $archived = $this->makeGoal(Position::PLANNING, ['title' => 'قديم']);
        $this->post(route('strategic-goals.status', $archived), ['status' => 'archived']);
        $outOfRange = $this->makeGoal(Position::PLANNING, ['title' => 'لاحق', 'from_year' => 2029, 'to_year' => 2030]);

        $this->as(Position::MEDIA)->post(route('plans.store'), ['position_id' => Position::where('code', Position::MEDIA)->value('id'), 'planning_year_id' => $y->id]);
        $plan = $this->plan(Position::MEDIA, 2027);
        $this->post(route('objectives.store', $plan), ['title' => 'هدف أ', 'weight' => 100])->assertSessionHasNoErrors();
        $o = $plan->objectives()->first();

        $msgs = implode("\n", array_column((new PlanValidator())->errors($plan->fresh()), 'message'));
        $this->assertStringContainsString('اربط الهدف «هدف أ» بهدف استراتيجي', $msgs);

        foreach ([$archived, $outOfRange] as $bad) {
            $this->put(route('objectives.update', $o), ['title' => 'هدف أ', 'weight' => 100, 'strategic_goal_id' => $bad->id])->assertSessionHasErrors('strategic_goal_id');
        }
        $this->put(route('objectives.update', $o), ['title' => 'هدف أ', 'weight' => 100, 'strategic_goal_id' => $g->id])->assertSessionHasNoErrors();
        $this->assertSame($g->id, $o->fresh()->strategic_goal_id);
        $msgs = implode("\n", array_column((new PlanValidator())->errors($plan->fresh()), 'message'));
        $this->assertStringNotContainsString('اربط الهدف', $msgs);
        $this->get(route('plans.edit', $plan))->assertOk()->assertSee($g->label());
    }

    public function test_link_is_not_required_when_no_goals_exist(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $this->assertSame([], (new PlanValidator())->errors($plan));
    }

    public function test_linked_goal_cannot_be_deleted_only_archived_and_link_survives(): void
    {
        $g = $this->makeGoal();
        $o = $this->plan(Position::ENGINEERING)->objectives()->first();
        $o->update(['strategic_goal_id' => $g->id]);
        $this->delete(route('strategic-goals.destroy', $g))->assertStatus(422);
        $this->post(route('strategic-goals.status', $g), ['status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame($g->id, $o->fresh()->strategic_goal_id);
        $this->assertFalse(StrategicGoal::usableFor(2026)->whereKey($g->id)->exists());

        $free = $this->makeGoal(Position::PLANNING, ['title' => 'غير مرتبط']);
        $this->delete(route('strategic-goals.destroy', $free))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'strategic_goal.delete', 'entity_id' => $free->id]);
        // الرقم المحذوف لا يُعاد استخدامه
        $this->assertSame('SG-03', $this->makeGoal(Position::PLANNING, ['title' => 'جديد'])->ref);
    }

    public function test_approved_plan_links_goal_only_through_change_request_with_new_version(): void
    {
        $g = $this->makeGoal();
        $plan = $this->plan(Position::ENGINEERING);
        $o = $plan->objectives()->first();
        $this->as(Position::ENGINEERING)->put(route('objectives.update', $o), ['title' => $o->title, 'weight' => $o->weight, 'strategic_goal_id' => $g->id])->assertForbidden();

        $this->post(route('change-requests.store', $plan), ['type' => 'plan_amendment', 'reason' => 'ربط بالخطة الاستراتيجية',
            'objectives' => [$o->id => ['strategic_goal_id' => $g->id]]])->assertSessionHasNoErrors()->assertRedirect();
        $cr = $plan->changeRequests()->first();
        $this->assertSame('غير مرتبط', $cr->changes[0]['old_label']);
        $this->assertSame($g->label(), $cr->changes[0]['new_label']);
        $this->as(Position::PRESIDENT)->post(route('change-requests.decide', $cr), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame($g->id, $o->fresh()->strategic_goal_id);
        $v2 = $plan->versions()->where('version_no', 2)->first();
        $this->assertSame($g->label(), $v2->snapshot['objectives'][0]['strategic_goal']);
        $this->assertNull($plan->versions()->where('version_no', 1)->first()->snapshot['objectives'][0]['strategic_goal'] ?? null);
        $this->get(route('change-requests.show', $cr))->assertSee($g->label());
    }

    public function test_copy_structure_keeps_goal_links(): void
    {
        $g = $this->makeGoal();
        $src = $this->plan(Position::ENGINEERING);
        $src->objectives()->update(['strategic_goal_id' => $g->id]);
        $this->as(Position::ENGINEERING)->post(route('plans.copy', $src), ['planning_year_id' => PlanningYear::where('year', 2027)->value('id')])->assertRedirect();
        $new = $this->plan(Position::ENGINEERING, 2027);
        $this->assertSame([$g->id], $new->objectives()->pluck('strategic_goal_id')->unique()->values()->all());
    }

    public function test_plan_export_shows_strategic_goal(): void
    {
        $g = $this->makeGoal();
        $plan = $this->plan(Position::ENGINEERING);
        $plan->objectives()->first()->update(['strategic_goal_id' => $g->id]);
        $e = app(\App\Services\Export\ExportService::class)->generate($this->user(Position::ENGINEERING), ['scope' => 'plan_full', 'format' => 'docx', 'plan_id' => $plan->id]);
        $zip = new \ZipArchive();
        $zip->open(\Illuminate\Support\Facades\Storage::disk('local')->path($e->file_path));
        $this->assertStringContainsString('SG-01', $zip->getFromName('word/document.xml'));
    }
}
