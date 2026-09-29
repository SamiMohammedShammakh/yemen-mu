<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Services\YearService;
use Tests\TestCase;

/** المرحلة الأولى — إنشاء وإدارة السنوات التخطيطية */
class YearManagementTest extends TestCase
{
    private function payload(array $over = []): array
    {
        return array_merge(['year' => 2028, 'name' => 'خطة 2028 التشغيلية', 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31',
            'owner_user_id' => $this->user(Position::PLANNING)->id, 'plans_due_on' => '2027-12-15', 'status' => 'draft', 'description' => 'توجهات'], $over);
    }

    public function test_planner_creates_year_with_details_and_quarters(): void
    {
        $this->as(Position::PLANNING)->post(route('years.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('years.index'));
        $y = PlanningYear::where('year', 2028)->with('quarters')->firstOrFail();
        $this->assertSame('خطة 2028 التشغيلية', $y->name);
        $this->assertSame('draft', $y->status);
        $this->assertSame('2027-12-15', $y->plans_due_on->toDateString());
        $this->assertSame($this->user(Position::PLANNING)->id, $y->owner_user_id);
        $this->assertSame(
            [['2028-01-01', '2028-03-31'], ['2028-04-01', '2028-06-30'], ['2028-07-01', '2028-09-30'], ['2028-10-01', '2028-12-31']],
            $y->quarters->map(fn ($q) => [$q->starts_on->toDateString(), $q->ends_on->toDateString()])->all()
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'year.create', 'entity_id' => $y->id]);
        $this->get(route('years.index'))->assertOk()->assertSee('خطة 2028 التشغيلية')->assertSee('مسودة');
        $this->get(route('years.edit', $y))->assertOk();
    }

    public function test_fiscal_year_not_starting_in_january_splits_into_quarters(): void
    {
        $this->as(Position::PRESIDENT)->post(route('years.store'), $this->payload(['starts_on' => '2027-07-01', 'ends_on' => '2028-06-30']))->assertSessionHasNoErrors();
        $q = PlanningYear::where('year', 2028)->first()->quarters;
        $this->assertSame('2027-10-01', $q[1]->starts_on->toDateString());
        $this->assertSame('2028-06-30', $q[3]->ends_on->toDateString());
    }

    public function test_invalid_dates_are_rejected_with_arabic_message(): void
    {
        $this->as(Position::PLANNING)->post(route('years.store'), $this->payload(['ends_on' => '2028-03-31']))->assertSessionHasErrors('ends_on');
        $this->assertFalse(PlanningYear::where('year', 2028)->exists());
        $this->post(route('years.store'), $this->payload(['year' => 2026]))->assertSessionHasErrors('year');
    }

    public function test_only_president_and_planning_manage_years(): void
    {
        $y = PlanningYear::where('year', 2027)->first();
        foreach ([Position::ENGINEERING, Position::VICE_PRESIDENT, 'admin'] as $who) {
            $this->as($who);
            $this->post(route('years.store'), $this->payload())->assertForbidden();
            $this->get(route('years.edit', $y))->assertForbidden();
            $this->put(route('years.update', $y), ['name' => 'x', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'])->assertForbidden();
            $this->post(route('years.transition', [$y, 'close']))->assertForbidden();
        }
        $this->as(Position::PRESIDENT)->get(route('years.edit', $y))->assertOk();
    }

    public function test_update_records_old_and_new_values_and_recomputes_quarters(): void
    {
        $y = PlanningYear::where('year', 2027)->first();
        $this->as(Position::PLANNING)->put(route('years.update', $y), ['name' => 'سنة 2027 المعدلة', 'starts_on' => '2027-02-01', 'ends_on' => '2028-01-31',
            'owner_user_id' => $this->user(Position::PRESIDENT)->id, 'plans_due_on' => '2027-01-20'])->assertSessionHasNoErrors();
        $y->refresh()->load('quarters');
        $this->assertSame('2027-02-01', $y->quarters[0]->starts_on->toDateString());
        $this->assertSame('2028-01-31', $y->quarters[3]->ends_on->toDateString());
        $log = AuditLog::where('action', 'year.update')->where('entity_id', $y->id)->latest('id')->first();
        $this->assertSame('سنة التخطيط 2027', $log->old_values['name']);
        $this->assertSame('سنة 2027 المعدلة', $log->new_values['name']);
        $this->assertSame('2027-01-01', $log->old_values['starts_on']);
    }

    public function test_dates_are_frozen_after_a_quarter_is_closed(): void
    {
        $y = PlanningYear::where('year', 2026)->first(); // الربع الأول مغلق في البيانات التجريبية
        $this->as(Position::PLANNING)->put(route('years.update', $y), ['name' => $y->name, 'starts_on' => '2026-02-01', 'ends_on' => '2027-01-31'])->assertSessionHasErrors('starts_on');
        $this->assertSame('2026-01-01', $y->fresh()->starts_on->toDateString());
        // تعديل الاسم دون التواريخ مسموح
        $this->put(route('years.update', $y), ['name' => 'سنة 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertSessionHasNoErrors();
        $this->assertSame('سنة 2026', $y->fresh()->name);
    }

    public function test_lifecycle_activate_close_reopen(): void
    {
        $this->as(Position::PLANNING)->post(route('years.store'), $this->payload())->assertSessionHasNoErrors();
        $y = PlanningYear::where('year', 2028)->first();

        $this->post(route('years.transition', [$y, 'close']))->assertSessionHasErrors('status'); // مسودة لا تُغلق
        $this->post(route('years.transition', [$y, 'activate']))->assertSessionHasNoErrors();
        $this->assertSame('open', $y->fresh()->status);

        $this->post(route('years.transition', [$y, 'close']))->assertSessionHasErrors('status'); // الأرباع مفتوحة
        $y->quarters()->update(['status' => 'closed']);
        $this->post(route('years.transition', [$y, 'close']), ['reason' => 'اعتماد التقرير الختامي'])->assertSessionHasNoErrors();
        $y->refresh();
        $this->assertSame('closed', $y->status);
        $this->assertNotNull($y->closed_at);
        $this->assertSame($this->user(Position::PLANNING)->id, $y->closed_by);

        // السنة المغلقة: لا خطط جديدة ولا تعديل بيانات
        $this->as(Position::MEDIA)->post(route('plans.store'), ['position_id' => Position::where('code', Position::MEDIA)->value('id'), 'planning_year_id' => $y->id])->assertStatus(422);
        $this->as(Position::PLANNING)->put(route('years.update', $y), ['name' => 'x', 'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31'])->assertSessionHasErrors('year');

        // إعادة الفتح تتطلب سببًا موثقًا
        $this->post(route('years.transition', [$y, 'reopen']))->assertSessionHasErrors('reason');
        $this->post(route('years.transition', [$y, 'reopen']), ['reason' => 'قرار المجلس رقم 12'])->assertSessionHasNoErrors();
        $this->assertSame('open', $y->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'year.reopen', 'entity_id' => $y->id]);
        $this->assertSame('قرار المجلس رقم 12', AuditLog::where('action', 'year.reopen')->latest('id')->first()->new_values['reason']);
        foreach (['year.activate', 'year.close'] as $a) {
            $this->assertDatabaseHas('audit_logs', ['action' => $a, 'entity_id' => $y->id]);
        }
    }

    public function test_multiple_active_years_are_allowed(): void
    {
        $this->as(Position::PLANNING)->post(route('years.store'), $this->payload(['status' => 'open']))->assertSessionHasNoErrors();
        $this->assertSame(3, PlanningYear::where('status', 'open')->count());
    }

    public function test_legacy_create_signature_still_creates_active_calendar_year(): void
    {
        $y = app(YearService::class)->create(2030, $this->user(Position::PLANNING));
        $this->assertSame('open', $y->status);
        $this->assertSame('2030-01-01', $y->starts_on->toDateString());
        $this->assertSame(4, $y->quarters()->count());
    }
}
