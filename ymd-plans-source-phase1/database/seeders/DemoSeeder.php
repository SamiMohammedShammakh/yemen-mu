<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\PlanWorkflow;
use App\Services\ProgressService;
use App\Services\QuarterService;
use App\Services\YearService;
use App\Support\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * بيانات تجريبية: مستخدم لكل منصب (كلمة المرور: Demo@2026) وخطط 2026 نشطة ببعض التحديثات.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo@2026';

    public const USERS = [
        Position::PRESIDENT => ['president@ymd-tr.org', 'م. عبدالله الحكيمي'],
        Position::VICE_PRESIDENT => ['vp@ymd-tr.org', 'م. خالد العبسي'],
        Position::SECRETARY => ['secretary@ymd-tr.org', 'م. مريم الشامي'],
        Position::FINANCE => ['finance@ymd-tr.org', 'م. أحمد باوزير'],
        Position::PLANNING => ['planning@ymd-tr.org', 'م. سارة المقطري'],
        Position::MEDIA => ['media@ymd-tr.org', 'م. يوسف الأهدل'],
        Position::ENGINEERING => ['engineering@ymd-tr.org', 'م. نبيل السقاف'],
    ];

    public function run(): void
    {
        // البيانات التجريبية بكلمات مرور معروفة: ممنوعة على الإنتاج
        if (app()->environment('production')) {
            throw new \RuntimeException('DemoSeeder معطّل في بيئة الإنتاج.');
        }
        $this->call(BaseSeeder::class);
        // حساب مدير نظام تجريبي (للتجربة المحلية فقط)
        $admin = User::where('is_system_admin', true)->first()
            ?? User::create(['email' => 'admin@ymd-tr.org', 'name' => 'مدير النظام التقني', 'password' => 'ChangeMe!2026', 'is_system_admin' => true, 'is_active' => true]);
        $users = [];
        foreach (self::USERS as $code => [$email, $name]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true]);
            PositionAssignment::firstOrCreate(['user_id' => $u->id, 'position_id' => Position::where('code', $code)->value('id')],
                ['is_primary' => true, 'starts_on' => '2026-01-01', 'assigned_by' => $admin->id]);
            $users[$code] = $u;
        }
        Access::flush();

        $planner = $users[Position::PLANNING];
        $president = $users[Position::PRESIDENT];
        $years = app(YearService::class);
        $y2026 = PlanningYear::where('year', 2026)->first() ?? $years->create(2026, $planner);
        PlanningYear::where('year', 2027)->exists() || $years->create(2027, $planner);

        foreach ([Position::ENGINEERING, Position::MEDIA, Position::FINANCE, Position::SECRETARY, Position::VICE_PRESIDENT] as $code) {
            if (Plan::where('planning_year_id', $y2026->id)->where('position_id', Position::where('code', $code)->value('id'))->exists()) {
                continue;
            }
            $this->makePlan($code, $users[$code], $y2026);
        }

        $wf = app(PlanWorkflow::class);
        $prog = app(ProgressService::class);
        foreach (Plan::where('planning_year_id', $y2026->id)->where('status', 'draft')->get() as $plan) {
            $owner = $users[$plan->position->code];
            Auth::login($owner);
            $wf->apply($owner, $plan, 'submit');
            Auth::login($planner);
            $wf->apply($planner, $plan, 'recommend', 'مكتملة ومتسقة مع الخطة الاستراتيجية.');
            Auth::login($president);
            $wf->apply($president, $plan, 'approve', 'اعتماد خطة 2026');
            $wf->apply($president, $plan, 'activate');
        }

        // تحديثات تجريبية للشؤون الهندسية: تطابق المثال (12 برنامجًا، 4 معتمدة حتى نهاية الربع الثاني)
        $eng = Plan::where('planning_year_id', $y2026->id)->whereHas('position', fn ($q) => $q->where('code', Position::ENGINEERING))->first();
        if ($eng && ! $eng->updates()->exists()) {
            $owner = $users[Position::ENGINEERING];
            $inds = $eng->indicators()->get()->keyBy('name');
            $training = $inds['عدد البرامج التدريبية المنفذة'];
            $sat = $inds['نسبة رضا المتدربين'];
            $members = $inds['عدد المهندسين المستفيدين من الخدمات'];
            $rows = [
                [$training, 1, 2, null, 'تنفيذ برنامجي AutoCAD وإدارة المشاريع', true],
                [$training, 2, 2, null, 'تنفيذ برنامجين في Revit والسلامة المهنية', true],
                [$training, 3, 1, null, 'برنامج BIM المتقدم', false],
                [$sat, 1, 85, 40, 'استبيان نهاية برامج الربع الأول', true],
                [$sat, 2, 75, 60, 'استبيان نهاية برامج الربع الثاني', true],
                [$members, 1, 60, null, 'المستفيدون من الاستشارات والتدريب', true],
                [$members, 2, 70, null, 'المستفيدون من الاستشارات والتدريب', true],
            ];
            foreach ($rows as [$ind, $q, $v, $p, $txt, $approve]) {
                Auth::login($owner);
                Access::flush();
                $u = $prog->submit($owner, $eng, ['indicator_id' => $ind->id, 'quarter' => $q, 'actual_value' => $v, 'participants' => $p, 'achieved' => $txt, 'period_label' => 'الربع ' . $q]);
                if ($approve) {
                    Auth::login($planner);
                    $prog->review($planner, $u, true, 'الدليل مكتمل.');
                }
            }
            Auth::login($planner);
            app(QuarterService::class)->close($y2026->quarters()->where('number', 1)->first(), $planner);
        }
        Auth::logout();
    }

    private function makePlan(string $code, User $owner, PlanningYear $year): Plan
    {
        $pos = Position::where('code', $code)->first();
        $plan = Plan::create([
            'planning_year_id' => $year->id, 'position_id' => $pos->id, 'owner_user_id' => $owner->id,
            'scope_description' => 'نطاق عمل ' . $pos->name . ' لعام ' . $year->year . ' وفق الخطة الاستراتيجية للجمعية.',
            'overall_outcome' => 'رفع كفاءة الخدمات المقدمة للمهندسين اليمنيين في تركيا.',
            'risks' => 'محدودية التمويل؛ تغير أولويات الأعضاء؛ ضيق وقت المتطوعين.',
            'resources' => 'فريق متطوعين، شراكات مع الجامعات والنقابات، ميزانية تشغيلية.',
            'status' => 'draft',
        ]);
        $tpl = self::TEMPLATES[$code] ?? self::TEMPLATES['default'];
        foreach ($tpl as $oi => $o) {
            $obj = $plan->objectives()->create(['title' => $o['title'], 'weight' => $o['weight'], 'sort' => $oi]);
            foreach ($o['indicators'] as $ii => $d) {
                $ind = $obj->indicators()->create([
                    'plan_id' => $plan->id, 'name' => $d[0], 'definition' => $d[1], 'unit' => $d[2], 'kind' => $d[3],
                    'aggregation' => $d[4], 'direction' => 'higher', 'baseline' => $d[5], 'annual_target' => $d[6],
                    'data_source' => $d[8], 'verification_method' => 'مراجعة الدليل من مسؤول التخطيط', 'frequency' => 'quarterly',
                    'required_evidence' => $d[9], 'owner_user_id' => $owner->id, 'weight' => $d[10] ?? null, 'sort' => $ii,
                ]);
                foreach ($d[7] as $q => $t) {
                    $ind->targets()->create(['quarter' => $q + 1, 'target' => $t]);
                }
            }
        }
        $proj = $plan->projects()->create(['type' => 'project', 'name' => $tpl[0]['project'], 'objective_id' => $plan->objectives()->first()->id,
            'responsible' => $owner->name, 'owner_user_id' => $owner->id, 'starts_on' => $year->year . '-01-01', 'ends_on' => $year->year . '-12-31']);
        foreach ([[1, 'إعداد الخطة التنفيذية للمشروع', '-03-31'], [2, 'التنفيذ المرحلي الأول', '-06-30'], [3, 'التنفيذ المرحلي الثاني', '-09-30'], [4, 'التقييم والتقرير الختامي', '-12-20']] as [$q, $t, $d]) {
            $plan->tasks()->create(['project_id' => $proj->id, 'title' => $t, 'responsible' => $owner->name, 'owner_user_id' => $owner->id,
                'quarter' => $q, 'original_quarter' => $q, 'due_on' => $year->year . $d, 'required_evidence' => 'محضر أو تقرير مختصر مع صور', 'status' => 'planned']);
        }

        return $plan;
    }

    // [اسم، تعريف، وحدة، نوع، تجميع، خط أساس، مستهدف سنوي، مستهدفات الأرباع، مصدر، الدليل، وزن]
    public const TEMPLATES = [
        Position::ENGINEERING => [
            ['title' => 'تطوير القدرات المهنية للمهندسين', 'weight' => 60, 'project' => 'برنامج التأهيل المهني 2026', 'indicators' => [
                ['عدد البرامج التدريبية المنفذة', 'عدد البرامج التدريبية المكتملة التي نفذتها الجمعية خلال السنة', 'برنامج', 'cumulative', 'sum', 8, 12, [2, 3, 3, 4], 'سجل البرامج التدريبية', 'كشف الحضور وشهادات الإتمام', 60],
                ['نسبة رضا المتدربين', 'متوسط رضا المتدربين من استبيان نهاية كل برنامج', '%', 'point', 'weighted_average', 78, 80, [80, 80, 80, 80], 'استبيانات التقييم', 'ملف نتائج الاستبيان', 40],
            ]],
            ['title' => 'توسيع خدمات الدعم الفني للأعضاء', 'weight' => 40, 'project' => 'منصة الاستشارات الهندسية', 'indicators' => [
                ['عدد المهندسين المستفيدين من الخدمات', 'عدد المهندسين المستفيدين في كل ربع (تُجمع الأرباع)', 'مهندس', 'periodic', 'sum', 180, 300, [60, 70, 80, 90], 'سجل الخدمات', 'كشوف المستفيدين'],
            ]],
        ],
        Position::MEDIA => [
            ['title' => 'تعزيز الحضور الإعلامي للجمعية', 'weight' => 100, 'project' => 'الحملة الإعلامية السنوية', 'indicators' => [
                ['عدد المواد الإعلامية المنشورة', 'عدد المواد المنشورة على منصات الجمعية', 'مادة', 'cumulative', 'sum', 90, 120, [30, 30, 30, 30], 'منصات التواصل', 'روابط المنشورات'],
                ['عدد الشراكات الإعلامية', 'اتفاقيات تعاون إعلامي موقعة', 'شراكة', 'cumulative', 'sum', 1, 4, [1, 1, 1, 1], 'سجل الاتفاقيات', 'نسخة الاتفاقية'],
            ]],
        ],
        Position::FINANCE => [
            ['title' => 'استدامة الموارد المالية', 'weight' => 70, 'project' => 'تنمية موارد الجمعية', 'indicators' => [
                ['نسبة تحصيل الاشتراكات', 'الاشتراكات المحصلة ÷ المستحقة بنهاية الربع', '%', 'point', 'last', 55, 85, [60, 70, 80, 85], 'النظام المحاسبي', 'تقرير التحصيل'],
            ]],
            ['title' => 'انتظام التقارير المالية', 'weight' => 30, 'project' => 'التقارير المالية الدورية', 'indicators' => [
                ['عدد التقارير المالية الربعية المعتمدة', 'تقارير مالية ربعية مرفوعة ومعتمدة', 'تقرير', 'cumulative', 'sum', 2, 4, [1, 1, 1, 1], 'أمانة الصندوق', 'التقرير المعتمد'],
            ]],
        ],
        'default' => [
            ['title' => 'تنظيم أعمال المنصب وتوثيقها', 'weight' => 100, 'project' => 'تطوير إجراءات العمل', 'indicators' => [
                ['عدد الاجتماعات الموثقة بمحاضر', 'اجتماعات موثقة بمحاضر معتمدة', 'اجتماع', 'cumulative', 'sum', 8, 12, [3, 3, 3, 3], 'سجل المحاضر', 'المحضر الموقع'],
            ]],
        ],
    ];
}
