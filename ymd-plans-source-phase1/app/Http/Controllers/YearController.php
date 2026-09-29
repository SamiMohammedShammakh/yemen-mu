<?php

namespace App\Http\Controllers;

use App\Models\PlanningYear;
use App\Models\StatusRule;
use App\Services\QuarterService;
use App\Services\YearService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;

class YearController extends Controller
{
    public function index()
    {
        $u = auth()->user();
        abort_unless(Access::canManageYears($u) || Access::hasGlobalView($u), 403);
        $years = PlanningYear::with(['quarters' => fn ($q) => $q->orderBy('number'), 'plans.position', 'owner'])->orderByDesc('year')->get();
        $rules = StatusRule::orderByDesc('version')->get();
        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('years.index', compact('years', 'rules', 'users'));
    }

    private const LABELS = ['year' => 'السنة', 'name' => 'اسم السنة', 'starts_on' => 'تاريخ البداية', 'ends_on' => 'تاريخ النهاية',
        'owner_user_id' => 'المسؤول', 'plans_due_on' => 'موعد اعتماد الخطط', 'description' => 'الوصف', 'status' => 'الحالة'];

    private function yearRules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
            'owner_user_id' => 'nullable|exists:users,id',
            'plans_due_on' => 'nullable|date',
            'description' => 'nullable|string|max:3000',
        ];
    }

    public function store(Request $r)
    {
        abort_unless(Access::canManageYears($r->user()), 403);
        $d = $r->validate(['year' => 'required|integer|min:2020|max:2100|unique:planning_years,year', 'status' => 'required|in:draft,open'] + $this->yearRules(),
            ['year.unique' => 'هذه السنة موجودة مسبقًا.'], self::LABELS);
        $y = app(YearService::class)->create((int) $d['year'], $r->user(), $d);
        session(['year_id' => $y->id, 'quarter' => 1]);

        return redirect()->route('years.index')->with('ok', 'أُنشئت «' . $y->displayName() . '» بأرباعها الأربعة (' . $y->statusLabel() . '). يستطيع كل مسؤول الآن إنشاء خطته أو نسخ هيكل خطته السابقة.');
    }

    public function edit(PlanningYear $year)
    {
        abort_unless(Access::canManageYears(auth()->user()), 403);
        $year->load(['quarters', 'owner', 'closer']);
        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $history = \App\Models\AuditLog::where('entity_type', 'PlanningYear')->where('entity_id', $year->id)->with('user')->latest('id')->get();

        return view('years.edit', compact('year', 'users', 'history'));
    }

    public function update(Request $r, PlanningYear $year)
    {
        abort_unless(Access::canManageYears($r->user()), 403);
        $d = $r->validate($this->yearRules(), [], self::LABELS);
        app(YearService::class)->update($year, $r->user(), $d);

        return redirect()->route('years.edit', $year)->with('ok', 'حُفظت بيانات السنة.');
    }

    public function transition(Request $r, PlanningYear $year, string $action)
    {
        abort_unless(Access::canManageYears($r->user()), 403);
        $d = $r->validate(['reason' => 'nullable|string|max:2000'], [], ['reason' => 'السبب']);
        app(YearService::class)->transition($year, $r->user(), $action, $d['reason'] ?? null);

        return back()->with('ok', YearService::TRANSITIONS[$action]['label'] . ': تم. الحالة الآن «' . $year->statusLabel() . '».');
    }

    public function closeQuarter(Request $r, PlanningYear $year, int $number)
    {
        abort_unless(Access::canReview($r->user()), 403, 'إقفال الأرباع لمسؤول التخطيط والمتابعة.');
        $quarter = $year->quarters()->where('number', $number)->firstOrFail();
        app(QuarterService::class)->close($quarter, $r->user());

        return back()->with('ok', 'أُقفل ' . $quarter->label() . ' ' . $year->year . ' وحُفظت لقطات النتائج.');
    }

    /** نسخة جديدة من حدود الحالات؛ تحفظ التقارير المعتمدة نسخة القواعد التي استخدمتها */
    public function rules(Request $r)
    {
        $u = $r->user();
        abort_unless(Access::canReview($u) || Access::isPresident($u), 403);
        $d = $r->validate(['on_track_min' => 'required|numeric|min:0|max:100', 'follow_up_min' => 'required|numeric|min:0|lt:on_track_min'],
            ['follow_up_min.lt' => 'حد «يحتاج متابعة» يجب أن يكون أقل من حد «يسير حسب الخطة».']);
        $v = (int) StatusRule::max('version') + 1;
        StatusRule::query()->update(['is_active' => false]);
        $rule = StatusRule::create($d + ['version' => $v, 'is_active' => true, 'created_by' => $u->id]);
        Audit::log('rules.create', $rule, null, $d + ['version' => $v]);

        return back()->with('ok', 'فُعّلت نسخة القواعد رقم ' . $v . '.');
    }
}
