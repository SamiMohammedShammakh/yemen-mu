<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\ProgressUpdate;
use App\Models\QuarterSnapshot;
use App\Services\PlanValidator;
use App\Services\Results;
use Tests\TestCase;

/** حالات الخطة 5–8: الاكتمال، الأرباع والمستهدفات، التحقق، طلبات التعديل */
class PlanRulesTest extends TestCase
{
    private function draft2027(): Plan
    {
        $this->as(Position::MEDIA)->post(route('plans.store'), [
            'position_id' => Position::where('code', Position::MEDIA)->value('id'),
            'planning_year_id' => PlanningYear::where('year', 2027)->value('id'),
        ])->assertRedirect();

        return $this->plan(Position::MEDIA, 2027);
    }

    /** 5. لا تُرسل خطة إذا لم تكتمل المؤشرات أو الأوزان أو المستهدفات */
    public function test_incomplete_plan_cannot_be_submitted_and_each_gap_is_explained(): void
    {
        $plan = $this->draft2027();
        $this->post(route('plans.workflow', [$plan, 'submit']))->assertSessionHasErrors('plan');
        $this->assertSame('draft', $plan->fresh()->status);

        // هدفان بمجموع 90% وأحدهما بلا مؤشر
        $this->put(route('plans.update', $plan), ['scope_description' => 'نطاق', 'overall_outcome' => 'نتيجة']);
        $this->post(route('objectives.store', $plan), ['title' => 'هدف أ', 'weight' => 60]);
        $this->post(route('objectives.store', $plan), ['title' => 'هدف ب', 'weight' => 30]);
        $a = $plan->objectives()->where('title', 'هدف أ')->first();
        // مؤشر بلا مصدر بيانات وبلا مستهدف الربع الرابع
        $this->post(route('indicators.store', $plan), [
            'objective_id' => $a->id, 'name' => 'عدد الأخبار', 'definition' => 'تعريف', 'unit' => 'خبر', 'direction' => 'higher',
            'kind' => 'cumulative', 'aggregation' => 'sum', 'baseline' => 0, 'annual_target' => 40, 'targets' => [1 => 10, 2 => 10, 3 => 10, 4 => ''],
            'verification_method' => 'مراجعة', 'frequency' => 'quarterly', 'owner_user_id' => $this->user(Position::MEDIA)->id,
        ])->assertRedirect();

        $msgs = implode("\n", array_column((new PlanValidator())->errors($plan->fresh()), 'message'));
        $this->assertStringContainsString('مجموع أوزان الأهداف يجب أن يساوي 100%، والمجموع الحالي 90%', $msgs);
        $this->assertStringContainsString('الهدف «هدف ب» لا يحتوي مؤشرًا قابلًا للقياس', $msgs);
        $this->assertStringContainsString('حقل «مصدر البيانات» مطلوب', $msgs);
        $this->assertStringContainsString('مستهدف الربع 4 مطلوب', $msgs);

        $this->post(route('plans.workflow', [$plan, 'submit']))->assertSessionHasErrors('plan');
        $this->assertSame('draft', $plan->fresh()->status);
        $this->get(route('plans.review', $plan))->assertOk()->assertSee('مستهدف الربع 4 مطلوب');

        // الاستكمال ثم الإرسال
        $this->put(route('objectives.update', $plan->objectives()->where('title', 'هدف ب')->first()), ['title' => 'هدف ب', 'weight' => 40]);
        $b = $plan->objectives()->where('title', 'هدف ب')->first();
        $ind = $plan->indicators()->first();
        $this->put(route('indicators.update', $ind), ['objective_id' => $a->id, 'name' => 'عدد الأخبار', 'definition' => 'تعريف', 'unit' => 'خبر', 'direction' => 'higher',
            'kind' => 'cumulative', 'aggregation' => 'sum', 'baseline' => 0, 'annual_target' => 40, 'targets' => [1 => 10, 2 => 10, 3 => 10, 4 => 10],
            'data_source' => 'المنصات', 'verification_method' => 'مراجعة', 'frequency' => 'quarterly', 'owner_user_id' => $this->user(Position::MEDIA)->id]);
        $this->post(route('indicators.store', $plan), ['objective_id' => $b->id, 'name' => 'نسبة التفاعل', 'definition' => 'تعريف', 'unit' => '%', 'direction' => 'higher',
            'kind' => 'point', 'aggregation' => 'weighted_average', 'baseline' => 3, 'annual_target' => 5, 'targets' => [1 => 5, 2 => 5, 3 => 5, 4 => 5],
            'data_source' => 'إحصاءات', 'verification_method' => 'لقطة', 'frequency' => 'quarterly', 'owner_user_id' => $this->user(Position::MEDIA)->id]);
        $this->assertSame([], (new PlanValidator())->errors($plan->fresh()));
        $this->post(route('plans.workflow', [$plan, 'submit']))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $plan->fresh()->status);
    }

    public function test_indicator_weights_inside_objective_must_total_100(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        Indicator::where('plan_id', $plan->id)->where('name', 'نسبة رضا المتدربين')->update(['weight' => 30]);
        $msgs = implode("\n", array_column((new PlanValidator())->errors($plan), 'message'));
        $this->assertStringContainsString('مجموع أوزان مؤشرات الهدف «تطوير القدرات المهنية للمهندسين» يجب أن يساوي 100%، والمجموع الحالي 90%', $msgs);
    }

    /** 6. خطة السنة بأرباعها الأربعة وبالمستهدف السنوي والمرحلي الصحيح + مثال الحساب */
    public function test_four_quarters_and_cumulative_targets_and_worked_example(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $this->assertSame([1, 2, 3, 4], $plan->year->quarters->pluck('number')->all());
        $training = $plan->indicators()->where('name', 'عدد البرامج التدريبية المنفذة')->first();
        $res = new Results();
        $cum = [];
        for ($q = 1; $q <= 4; $q++) {
            $cum[] = $res->findIndicator($res->plan($plan, $q), $training->id)['quarters'][$q]['cum_target'];
        }
        $this->assertEquals([2, 5, 8, 12], $cum);

        // المثال: المستهدف السنوي 12، مستهدف النصف الأول 5، المعتمد 4
        $i = $res->findIndicator($res->plan($plan, 2), $training->id);
        $this->assertSame('مستهدف النصف الأول', $i['period']['compare_label']);
        $this->assertEquals(5, $i['period']['compare_target']);
        $this->assertEquals(4, $i['period']['compare_actual']);
        $this->assertEqualsWithDelta(80.0, $i['period']['achievement'], 0.001);
        $this->assertEquals(-1, $i['period']['gap']);
        $this->assertEqualsWithDelta(33.33, $i['annual']['achievement'], 0.01);

        $this->as(Position::ENGINEERING)->get(route('indicators.show', [$training, 'quarter' => 2]))
            ->assertSee('الإنجاز مقابل مستهدف النصف الأول')->assertSee('80%')->assertSee('33.3%')->assertSee('أقل من المستهدف بـ 1 برنامج');

        // نسبة الرضا لا تُجمع: متوسط مرجح بعدد المشاركين (85×40 + 75×60) ÷ 100 = 79
        $sat = $plan->indicators()->where('name', 'نسبة رضا المتدربين')->first();
        $s = $res->findIndicator($res->plan($plan, 2), $sat->id);
        $this->assertEquals(75, $s['period']['actual']);
        $this->assertEqualsWithDelta(79.0, $s['annual']['actual'], 0.001);
        $this->assertNotEquals(160, $s['annual']['actual']);

        // الربع الثالث لم ينتهِ: لم يستحق بعد (لا صفر)، والرابع كذلك
        $q3 = $res->findIndicator($res->plan($plan, 3), $sat->id)['period'];
        $this->assertSame('not_due', $q3['state']);
        $this->assertNull($q3['achievement']);
        $this->assertNull($q3['actual']);

        // الإنجاز الموزون للهدف: 60% × 80 + 40% × 93.75 = 85.5
        $o = $res->plan($plan, 2)['objectives'][0];
        $this->assertEqualsWithDelta(85.5, $o['period_achievement'], 0.01);
    }

    public function test_due_quarter_without_approved_data_shows_missing_not_zero(): void
    {
        $plan = $this->plan(Position::MEDIA);
        $res = (new Results())->plan($plan, 2);
        $i = $res['objectives'][0]['indicators'][0];
        $this->assertSame('missing', $i['period']['state']);
        $this->assertSame('بيانات ناقصة', $i['period']['state_label']);
        $this->assertNull($i['period']['achievement']);
        $this->assertNull($res['period_achievement']);
    }

    /** 7. لا يدخل التحديث بانتظار التحقق في الإنجاز المعتمد */
    public function test_pending_update_is_excluded_until_planning_approves_it(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $training = $plan->indicators()->where('name', 'عدد البرامج التدريبية المنفذة')->first();
        $pending = ProgressUpdate::where('indicator_id', $training->id)->where('quarter', 3)->first();
        $this->assertSame('pending', $pending->status);

        \Illuminate\Support\Carbon::setTestNow('2026-10-05 10:00');
        $r = (new Results())->findIndicator((new Results())->plan($plan, 3), $training->id);
        $this->assertSame('pending', $r['period']['state']);
        $this->assertNull($r['period']['compare_actual']);
        $this->assertSame(1, $r['period']['pending_count']);
        $this->assertEquals(4, $r['annual']['actual']); // 2+2 فقط

        // صاحب المنصب لا يعتمد تحديثه
        $this->as(Position::ENGINEERING)->post(route('updates.review', $pending), ['decision' => 'approve'])->assertForbidden();
        // الإعادة تتطلب سببًا
        $this->as(Position::PLANNING)->post(route('updates.review', $pending), ['decision' => 'return'])->assertSessionHasErrors('review_note');
        $this->post(route('updates.review', $pending), ['decision' => 'approve', 'review_note' => 'مكتمل'])->assertSessionHasNoErrors();

        $r = (new Results())->findIndicator((new Results())->plan($plan->fresh(), 3), $training->id);
        $this->assertEquals(5, $r['period']['compare_actual']);
        $this->assertEquals(8, $r['period']['compare_target']);
        $this->assertEqualsWithDelta(62.5, $r['period']['achievement'], 0.001);
    }

    public function test_completion_claim_waits_for_verification_and_updates_are_immutable(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $task = $plan->tasks()->where('quarter', 3)->first();
        $this->as(Position::ENGINEERING)->post(route('updates.store', $plan), ['task_id' => $task->id, 'quarter' => 3, 'achieved' => 'اكتمل', 'claims_completion' => 1]);
        $this->assertSame('pending_verification', $task->fresh()->status);
        $upd = ProgressUpdate::where('task_id', $task->id)->first();

        $this->expectException(\LogicException::class);
        $upd->update(['achieved' => 'تعديل']);
    }

    public function test_closed_quarter_rejects_direct_updates(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $ind = $plan->indicators()->first();
        $this->as(Position::ENGINEERING)->post(route('updates.store', $plan), ['indicator_id' => $ind->id, 'quarter' => 1, 'actual_value' => 9])
            ->assertSessionHasErrors('quarter');
    }

    public function test_deferred_task_stays_late_in_original_quarter(): void
    {
        $plan = $this->plan(Position::MEDIA);
        $task = $plan->tasks()->where('quarter', 2)->first();
        $this->as(Position::MEDIA)->post(route('tasks.defer', $task), ['reason' => 'تأخر الشريك', 'new_due_on' => '2026-08-15'])->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertSame(3, $task->quarter);
        $this->assertSame(2, $task->original_quarter);
        $q2 = app(\App\Services\QuarterService::class)->tasksForQuarter($plan, 2);
        $this->assertSame('deferred', $q2[0]['status']);
        $this->assertStringContainsString('نُقلت إلى الربع 3', $q2[0]['status_label']);
        $this->assertSame('تأخر الشريك', $q2[0]['deferral_reason']);
    }

    /** 8. لا يغيّر طلب تعديل الخطة نتائج الأرباع المغلقة دون حفظ النسخ والسجل */
    public function test_amendment_keeps_versions_and_closed_quarter_snapshots(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $training = $plan->indicators()->where('name', 'عدد البرامج التدريبية المنفذة')->first();
        $q1Before = (new Results())->plan($plan, 1);
        $this->assertSame('snapshot', $q1Before['source']);

        // التعديل المباشر ممنوع بعد الاعتماد
        $this->as(Position::ENGINEERING)->put(route('indicators.update', $training), ['objective_id' => $training->objective_id, 'name' => 'x', 'direction' => 'higher', 'kind' => 'cumulative', 'aggregation' => 'sum'])->assertForbidden();
        $this->put(route('objectives.update', $training->objective), ['title' => 'x', 'weight' => 1])->assertForbidden();

        // طلب تعديل: مستهدف ر1 من 2 إلى 3 وربع 4 من 4 إلى 3 (المجموع 12)
        $this->post(route('change-requests.store', $plan), ['type' => 'plan_amendment', 'reason' => 'إعادة جدولة',
            'indicators' => [$training->id => ['targets' => [1 => 3, 4 => 3]]]])->assertRedirect();
        $cr = $plan->changeRequests()->first();
        $this->assertCount(2, $cr->changes);
        $this->assertEquals(2, $cr->changes[0]['old']);
        $this->assertEquals(3, $cr->changes[0]['new']);
        $this->assertTrue(collect($cr->impact)->firstWhere('quarter', 1)['closed']);

        // صاحب المنصب لا يعتمد؛ الرئيس يعتمد
        $this->post(route('change-requests.decide', $cr), ['decision' => 'approve'])->assertForbidden();
        $this->as(Position::PRESIDENT)->post(route('change-requests.decide', $cr), ['decision' => 'approve'])->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(2, $plan->current_version);
        $this->assertSame([2, 1], $plan->versions()->pluck('version_no')->all());
        $v1 = $plan->versions()->where('version_no', 1)->first();
        $this->assertNotNull($v1->effective_to);
        $this->assertEquals(2, collect($v1->snapshot['objectives'][0]['indicators'])->firstWhere('id', $training->id)['targets'][1]);

        // نتيجة الربع الأول المغلق كما هي في اللقطة
        $q1After = (new Results())->plan($plan, 1);
        $this->assertEquals($q1Before['period_achievement'], $q1After['period_achievement']);
        $this->assertSame(1, $q1After['version_no']);
        $this->assertSame(1, QuarterSnapshot::where('plan_id', $plan->id)->where('quarter', 1)->count());
        // الربع الثاني المفتوح يُحسب بالقيم الجديدة: التراكمي 3+3 = 6
        $i = (new Results())->findIndicator((new Results())->plan($plan, 2), $training->id);
        $this->assertEquals(6, $i['period']['compare_target']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'change_request.approve']);
    }

    public function test_closed_quarter_result_change_adds_snapshot_revision_and_keeps_original(): void
    {
        $plan = $this->plan(Position::ENGINEERING);
        $training = $plan->indicators()->where('name', 'عدد البرامج التدريبية المنفذة')->first();
        $this->as(Position::ENGINEERING)->post(route('change-requests.store', $plan), ['type' => 'closed_quarter_result', 'reason' => 'برنامج لم يُسجل',
            'indicator_id' => $training->id, 'quarter' => 1, 'new_value' => 3])->assertRedirect();
        $cr = $plan->changeRequests()->first();
        $this->assertEquals(2, $cr->changes[0]['old']);
        $this->as(Position::PRESIDENT)->post(route('change-requests.decide', $cr), ['decision' => 'reject'])->assertSessionHasErrors('decision_note');
        $this->post(route('change-requests.decide', $cr), ['decision' => 'approve', 'decision_note' => 'موافق']);

        $snaps = QuarterSnapshot::where('plan_id', $plan->id)->where('quarter', 1)->orderBy('revision')->get();
        $this->assertSame([1, 2], $snaps->pluck('revision')->all());
        $orig = collect($snaps[0]->data['objectives'][0]['indicators'])->firstWhere('id', $training->id);
        $this->assertEquals(2, $orig['period']['actual']);
        $i = (new Results())->findIndicator((new Results())->plan($plan, 1), $training->id);
        $this->assertEquals(3, $i['period']['actual']);
        $this->assertTrue($i['period']['adjusted']);
        $this->assertDatabaseHas('progress_updates', ['plan_id' => $plan->id, 'is_adjustment' => true, 'change_request_id' => $cr->id]);
    }

    public function test_copy_structure_to_new_year_without_progress_or_approval(): void
    {
        $src = $this->plan(Position::ENGINEERING);
        $y27 = PlanningYear::where('year', 2027)->first();
        $this->as(Position::ENGINEERING)->post(route('plans.copy', $src), ['planning_year_id' => $y27->id])->assertRedirect();
        $new = $this->plan(Position::ENGINEERING, 2027);
        $this->assertSame('draft', $new->status);
        $this->assertSame(0, $new->current_version);
        $this->assertSame($src->indicators()->count(), $new->indicators()->count());
        $this->assertSame(0, $new->updates()->count());
        $this->assertSame(0, $new->attachments()->count());
        $this->assertSame(0, $new->versions()->count());
        $this->assertSame(0, $new->tasks()->where('status', '!=', 'planned')->count());
    }

    public function test_quarter_close_blocked_while_evidence_pending(): void
    {
        $y = PlanningYear::where('year', 2026)->first();
        $this->as(Position::PLANNING)->post(route('quarters.close', [$y, 2]))->assertSessionHasNoErrors();
        $this->post(route('quarters.close', [$y, 3]))->assertSessionHasErrors('quarter');
        $this->as(Position::ENGINEERING)->post(route('quarters.close', [$y, 3]))->assertForbidden();
    }
}

