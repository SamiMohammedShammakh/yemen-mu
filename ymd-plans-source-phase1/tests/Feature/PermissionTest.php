<?php

namespace Tests\Feature;

use App\Models\ExecutiveMembership;
use App\Models\Plan;
use App\Models\Position;
use App\Support\Access;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** حالات الصلاحيات 1–4 من قائمة الاختبار */
class PermissionTest extends TestCase
{
    /** 1. يدخل كل مستخدم إلى مساحة منصبه الصحيحة */
    public function test_each_user_lands_in_own_position_workspace(): void
    {
        foreach ([Position::ENGINEERING, Position::MEDIA, Position::FINANCE, Position::SECRETARY, Position::VICE_PRESIDENT, Position::PLANNING, Position::PRESIDENT] as $code) {
            $u = $this->user($code);
            $this->flushSession();
            $this->post('/login', ['email' => $u->email, 'password' => 'Demo@2026'])->assertRedirect(route('home'));
            $this->get('/')->assertRedirect(route('workspace'));
            $name = Position::where('code', $code)->value('name');
            $this->get('/workspace')->assertOk()->assertSee('<h1>' . $name . '</h1>', false);
            $this->post('/logout');
        }
        // مدير النظام التقني يفتح إدارة الحسابات لا لوحة خطط
        $this->flushSession();
        $this->post('/login', ['email' => $this->user('admin')->email, 'password' => 'ChangeMe!2026']);
        $this->get('/')->assertRedirect(route('admin.users.index'));
    }

    public function test_user_with_two_positions_can_switch_only_to_own_workspaces(): void
    {
        $u = $this->user(Position::MEDIA);
        \App\Models\PositionAssignment::create(['user_id' => $u->id, 'position_id' => Position::where('code', Position::SECRETARY)->value('id'), 'is_primary' => false, 'starts_on' => '2026-01-01']);
        $sec = Position::where('code', Position::SECRETARY)->value('id');
        $eng = Position::where('code', Position::ENGINEERING)->value('id');
        $this->as(Position::MEDIA)->post('/context', ['ws' => 'pos:' . $sec])->assertRedirect(route('home'));
        $this->get('/workspace')->assertOk()->assertSee('<h1>أمين السر</h1>', false);
        $this->post('/context', ['ws' => 'pos:' . $eng])->assertForbidden();
    }

    /** 2. نائب الرئيس لا يرى خطط الآخرين إلا بعضوية تنفيذية رسمية */
    public function test_vice_president_needs_explicit_executive_membership(): void
    {
        $eng = $this->plan(Position::ENGINEERING);
        $vp = $this->user(Position::VICE_PRESIDENT);

        $this->as(Position::VICE_PRESIDENT)->get(route('plans.show', $eng))->assertForbidden();
        $this->get(route('executive'))->assertForbidden();
        $this->getJson('/api/v1/plans')->assertOk()->assertJsonCount(1, 'data');

        // منح العضوية من الرئيس مع تسجيل من منح ومتى
        $this->as(Position::PRESIDENT)->post(route('admin.executive.grant'), ['user_id' => $vp->id, 'grant_reason' => 'قرار مجلس الإدارة رقم 7'])->assertRedirect();
        $m = ExecutiveMembership::where('user_id', $vp->id)->first();
        $this->assertSame($this->user(Position::PRESIDENT)->id, $m->granted_by);
        $this->assertNotNull($m->granted_at);

        $this->as(Position::VICE_PRESIDENT)->get(route('plans.show', $eng))->assertOk();
        $this->get(route('executive'))->assertOk();
        $this->getJson('/api/v1/plans')->assertJsonCount(5, 'data');

        // الإيقاف يسحب الرؤية فورًا ويُسجَّل
        $this->as(Position::PRESIDENT)->post(route('admin.executive.revoke', $m), ['revoke_reason' => 'انتهاء المهمة']);
        $this->assertNotNull($m->fresh()->revoked_at);
        $this->as(Position::VICE_PRESIDENT)->get(route('plans.show', $eng))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'executive.grant']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'executive.revoke']);
    }

    /** 3. يرى الرئيس والإدارة التنفيذية المعتمدة ومسؤول التخطيط جميع الخطط */
    public function test_president_planning_and_executive_see_all_plans(): void
    {
        $vp = $this->user(Position::VICE_PRESIDENT);
        ExecutiveMembership::create(['user_id' => $vp->id, 'granted_by' => $this->user(Position::PRESIDENT)->id, 'granted_at' => now(), 'grant_reason' => 'قرار']);
        foreach ([Position::PRESIDENT, Position::PLANNING, Position::VICE_PRESIDENT] as $code) {
            $this->as($code);
            foreach (Plan::all() as $p) {
                $this->get(route('plans.show', $p))->assertOk();
                $this->getJson(route('api.results', $p))->assertOk();
            }
            $this->getJson('/api/v1/plans')->assertJsonCount(Plan::count(), 'data');
        }
    }

    /** 4. لا يصل المسؤول إلى خطة أخرى بتغيير الرابط أو طلب API أو تنزيل ملفها */
    public function test_restricted_user_cannot_reach_other_plan_by_any_route(): void
    {
        $media = $this->plan(Position::MEDIA);
        $ind = $media->indicators()->first();
        $obj = $media->objectives()->first();
        $proj = $media->projects()->first();
        $task = $media->tasks()->first();

        // مرفق في خطة الإعلام
        $this->as(Position::MEDIA)->post(route('updates.store', $media), ['indicator_id' => $ind->id, 'quarter' => 3, 'actual_value' => 5,
            'files' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')]])->assertRedirect();
        $att = $media->attachments()->first();
        $export = app(\App\Services\Export\ExportService::class)->generate($this->user(Position::MEDIA), ['scope' => 'plan_full', 'format' => 'csv', 'plan_id' => $media->id]);

        $this->as(Position::ENGINEERING);
        foreach ([
            route('plans.show', $media), route('plans.show', [$media, 'tab' => 'files']), route('plans.quarter', [$media, 2]), route('plans.review', $media),
            route('plans.version', [$media, 1]), route('objectives.show', $obj), route('indicators.show', $ind), route('projects.show', $proj),
            route('attachments.show', $att), route('exports.download', $export), route('change-requests.create', $media),
            route('exports.preview', ['scope' => 'plan_full', 'format' => 'pdf', 'plan_id' => $media->id]),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach ([route('api.plan', $media), route('api.results', $media), route('api.indicator', $ind)] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        // محاولات كتابة وإنشاء ملف
        $this->post(route('exports.store'), ['scope' => 'year_package', 'format' => 'zip', 'plan_id' => $media->id])->assertForbidden();
        $this->post(route('updates.store', $media), ['indicator_id' => $ind->id, 'quarter' => 3, 'actual_value' => 1])->assertForbidden();
        $this->post(route('tasks.defer', $task), ['reason' => 'x', 'new_due_on' => '2026-11-01'])->assertForbidden();
        $this->post(route('notes.store', $media), ['body' => 'x'])->assertForbidden();

        // البحث والقوائم والتقارير لا تكشف أسماء خطط الآخرين
        $this->get(route('search', ['q' => 'الإعلامية']))->assertOk()->assertDontSee($ind->name);
        $this->getJson(route('api.search', ['q' => 'المواد']))->assertJsonCount(0, 'indicators');
        $this->get(route('plans.index'))->assertDontSee('مسؤول العلاقات العامة والإعلام');
        $this->get(route('reports.index'))->assertDontSee($export->file_name);
        $this->getJson('/api/v1/exports')->assertJsonMissing(['uuid' => $export->uuid]);
        $this->get(route('planning.center'))->assertForbidden();
        $this->get(route('exports.preview', ['scope' => 'org_summary', 'format' => 'pdf', 'planning_year_id' => $media->planning_year_id, 'quarter' => 2]))->assertForbidden();
    }

    public function test_system_admin_manages_accounts_but_cannot_read_plans(): void
    {
        $eng = $this->plan(Position::ENGINEERING);
        $this->as('admin')->get(route('admin.users.index'))->assertOk();
        $this->get(route('plans.show', $eng))->assertForbidden();
        $this->getJson('/api/v1/plans')->assertJsonCount(0, 'data');
        $this->get(route('executive'))->assertForbidden();
        $this->assertFalse(Access::hasGlobalView($this->user('admin')));
    }

    public function test_approval_delegate_sees_only_plans_awaiting_decision(): void
    {
        $sec = $this->user(Position::SECRETARY);
        \App\Models\Delegation::create(['user_id' => $sec->id, 'document_ref' => 'تفويض 2026/3', 'starts_on' => '2026-09-01', 'granted_by' => $this->user(Position::PRESIDENT)->id]);
        $eng = $this->plan(Position::ENGINEERING);
        $this->as(Position::SECRETARY)->get(route('plans.show', $eng))->assertForbidden();
        $eng->update(['status' => 'recommended']);
        $this->get(route('plans.show', $eng))->assertOk();
        $this->assertTrue(Access::canApprove($sec));
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $u = $this->user(Position::MEDIA);
        $u->update(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Demo@2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
