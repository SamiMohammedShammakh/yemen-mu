<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;

/** المرحلة الأولى — تسلسل المبادرة ← النشاط ← المهمة، والترقيم المرجعي التلقائي */
class HierarchyRefsTest extends TestCase
{
    /** خطة مسودة للشؤون الهندسية في 2027 مع هدف واحد */
    private function draftPlan(): Plan
    {
        $y = PlanningYear::where('year', 2027)->first();
        $this->as(Position::ENGINEERING)->post(route('plans.store'), ['position_id' => Position::where('code', Position::ENGINEERING)->value('id'), 'planning_year_id' => $y->id]);
        $plan = $this->plan(Position::ENGINEERING, 2027);
        $this->post(route('objectives.store', $plan), ['title' => 'رفع الكفاءة', 'weight' => 100])->assertSessionHasNoErrors();

        return $plan->fresh();
    }

    private function initiative(Plan $plan, array $over = []): Project
    {
        $this->post(route('projects.store', $plan), $over + ['type' => 'initiative', 'name' => 'مبادرة التدريب', 'objective_id' => $plan->objectives()->value('id')])->assertSessionHasNoErrors();

        return Project::latest('id')->first();
    }

    private function activity(Plan $plan, Project $parent, string $name = 'دورة أساسيات'): Project
    {
        $this->post(route('projects.store', $plan), ['type' => 'activity', 'parent_id' => $parent->id, 'name' => $name,
            'owner_user_id' => $this->user(Position::ENGINEERING)->id])->assertSessionHasNoErrors();

        return Project::latest('id')->first();
    }

    public function test_plan_and_objective_get_refs(): void
    {
        $plan = $this->draftPlan();
        $this->assertSame('PL-2027-ENG', $plan->ref);
        $this->assertSame('PL-2027-ENG-O1', $plan->objectives()->first()->ref);
        // الخطط المُرحّلة من البيانات السابقة حصلت على أرقامها أيضًا
        $this->assertSame('PL-2026-ENG', $this->plan(Position::ENGINEERING)->ref);
        $this->assertSame(0, Plan::whereNull('ref')->count());
        $this->assertSame(0, Task::whereNull('ref')->count());
    }

    public function test_activity_under_initiative_gets_ref_inherits_objective_and_is_audited(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $this->assertSame('PL-2027-ENG-I01', $ini->ref);

        $a1 = $this->activity($plan, $ini);
        $a2 = $this->activity($plan, $ini, 'دورة متقدمة');
        $this->assertSame('activity', $a1->type);
        $this->assertSame('PL-2027-ENG-I01-A01', $a1->ref);
        $this->assertSame('PL-2027-ENG-I01-A02', $a2->ref);
        $this->assertSame($ini->objective_id, $a1->objective_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.create', 'entity_id' => $a1->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.create', 'entity_id' => $ini->id]);

        $this->post(route('tasks.store', $plan), ['title' => 'حجز القاعة', 'project_id' => $a1->id, 'quarter' => 1])->assertSessionHasNoErrors();
        $t = Task::latest('id')->first();
        $this->assertSame('PL-2027-ENG-T001', $t->ref);
        $this->assertDatabaseHas('audit_logs', ['action' => 'task.create', 'entity_id' => $t->id]);

        // مشروع مستقل يأخذ البادئة P
        $p = $this->initiative($plan, ['type' => 'project', 'name' => 'مشروع مستقل']);
        $this->assertSame('PL-2027-ENG-P02', $p->ref);

        // تغيير هدف المبادرة ينتقل إلى أنشطتها
        $this->post(route('objectives.store', $plan), ['title' => 'هدف ثانٍ', 'weight' => 0]);
        $o2 = $plan->objectives()->where('title', 'هدف ثانٍ')->first();
        $this->put(route('projects.update', $ini), ['type' => 'initiative', 'name' => $ini->name, 'objective_id' => $o2->id])->assertSessionHasNoErrors();
        $this->assertSame($o2->id, $a1->fresh()->objective_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.update', 'entity_id' => $ini->id]);
    }

    public function test_hierarchy_rules(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $a = $this->activity($plan, $ini);

        // لا نشاط تحت نشاط
        $this->post(route('projects.store', $plan), ['type' => 'activity', 'parent_id' => $a->id, 'name' => 'x'])->assertSessionHasErrors('parent_id');
        // النشاط يتطلب مبادرة أم
        $this->post(route('projects.store', $plan), ['type' => 'activity', 'name' => 'x'])->assertSessionHasErrors('parent_id');
        // لا نشاط تحت مبادرة من خطة أخرى
        $foreign = $this->plan(Position::ENGINEERING)->projects()->whereNull('parent_id')->first();
        $this->post(route('projects.store', $plan), ['type' => 'activity', 'parent_id' => $foreign->id, 'name' => 'x'])->assertSessionHasErrors('parent_id');
        // لا حذف لمبادرة لها أنشطة
        $this->delete(route('projects.destroy', $ini))->assertSessionHasErrors('project');
        $this->assertNotNull($ini->fresh());
        $this->assertSame(1, Project::where('parent_id', $ini->id)->count());
    }

    public function test_refs_are_never_reused_after_deletion(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $a = $this->activity($plan, $ini);
        $this->delete(route('projects.destroy', $a))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.delete', 'entity_id' => $a->id]);
        $this->assertSame('PL-2027-ENG-I01-A02', $this->activity($plan, $ini, 'بديل')->ref);

        $this->post(route('tasks.store', $plan), ['title' => 'م1', 'quarter' => 1]);
        $t1 = Task::latest('id')->first();
        $this->delete(route('tasks.destroy', $t1));
        $this->post(route('tasks.store', $plan), ['title' => 'م2', 'quarter' => 2]);
        $this->assertSame('PL-2027-ENG-T002', Task::latest('id')->first()->ref);

        $this->delete(route('projects.destroy', Project::where('parent_id', $ini->id)->first()));
        $this->delete(route('projects.destroy', $ini))->assertSessionHasNoErrors();
        $this->assertSame('PL-2027-ENG-I02', $this->initiative($plan)->ref);
    }

    public function test_pages_show_refs_and_search_finds_by_ref(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $a = $this->activity($plan, $ini);
        $this->post(route('tasks.store', $plan), ['title' => 'حجز القاعة', 'project_id' => $a->id, 'quarter' => 1]);

        $this->get(route('projects.show', $ini))->assertOk()->assertSee('PL-2027-ENG-I01')->assertSee('PL-2027-ENG-I01-A01');
        $this->get(route('projects.show', $a))->assertOk()->assertSee('PL-2027-ENG-T001')->assertSee('مبادرة التدريب');
        $this->get(route('plans.show', [$plan, 'tab' => 'tasks']))->assertOk()->assertSee('PL-2027-ENG-T001')->assertSee('ضمن PL-2027-ENG-I01');
        $this->get(route('plans.edit', $plan))->assertOk()->assertSee('PL-2027-ENG-I01-A01');
        $this->get(route('plans.index'))->assertOk();

        $this->get(route('search', ['q' => 'I01-A01']))->assertOk()->assertSee('دورة أساسيات');
        $this->get(route('search', ['q' => 'PL-2027-ENG-T001']))->assertOk()->assertSee('حجز القاعة');
    }

    public function test_restricted_user_cannot_reach_other_plans_hierarchy(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $a = $this->activity($plan, $ini);

        $this->as(Position::MEDIA);
        $this->get(route('projects.show', $a))->assertForbidden();
        $this->post(route('projects.store', $plan), ['type' => 'activity', 'parent_id' => $ini->id, 'name' => 'x'])->assertForbidden();
        $this->delete(route('projects.destroy', $a))->assertForbidden();
        $this->get(route('search', ['q' => 'PL-2027-ENG']))->assertOk()->assertDontSee('دورة أساسيات');
    }

    public function test_copy_structure_keeps_activities_with_new_refs(): void
    {
        $src = $this->plan(Position::ENGINEERING);
        $ini = $src->projects()->whereNull('parent_id')->orderBy('id')->first();
        $act = $src->projects()->create(['type' => 'activity', 'parent_id' => $ini->id, 'name' => 'نشاط منقول', 'objective_id' => $ini->objective_id]);
        $src->tasks()->create(['title' => 'مهمة النشاط', 'project_id' => $act->id, 'quarter' => 1, 'original_quarter' => 1, 'status' => 'planned']);
        $this->assertStringStartsWith($ini->ref . '-A', $act->ref);

        $this->as(Position::ENGINEERING)->post(route('plans.copy', $src), ['planning_year_id' => PlanningYear::where('year', 2027)->value('id')])->assertRedirect();
        $new = $this->plan(Position::ENGINEERING, 2027);
        $newAct = $new->projects()->where('name', 'نشاط منقول')->first();
        $this->assertNotNull($newAct);
        $this->assertSame('activity', $newAct->type);
        $this->assertStringStartsWith('PL-2027-ENG-', $newAct->ref);
        $this->assertSame($new->id, $newAct->parent->plan_id);
        $this->assertSame($ini->name, $newAct->parent->name);
        $this->assertSame($newAct->id, $new->tasks()->where('title', 'مهمة النشاط')->value('project_id'));
        // لا تكرار في الأرقام المرجعية
        $this->assertSame(Project::count(), Project::distinct()->count('ref'));
        $this->assertSame(Task::count(), Task::distinct()->count('ref'));
    }

    public function test_export_contains_refs(): void
    {
        $plan = $this->draftPlan();
        $ini = $this->initiative($plan);
        $this->activity($plan, $ini);
        $e = app(\App\Services\Export\ExportService::class)->generate($this->user(Position::ENGINEERING), ['scope' => 'plan_full', 'format' => 'docx', 'plan_id' => $plan->id]);
        $zip = new \ZipArchive();
        $zip->open(\Illuminate\Support\Facades\Storage::disk('local')->path($e->file_path));
        $xml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('PL-2027-ENG-O1', $xml);
        $this->assertStringContainsString('PL-2027-ENG-I01-A01', $xml);
    }
}
