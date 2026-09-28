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
        $years = PlanningYear::with(['quarters' => fn ($q) => $q->orderBy('number'), 'plans.position'])->orderByDesc('year')->get();
        $rules = StatusRule::orderByDesc('version')->get();

        return view('years.index', compact('years', 'rules'));
    }

    public function store(Request $r)
    {
        abort_unless(Access::canManageYears($r->user()), 403);
        $d = $r->validate(['year' => 'required|integer|min:2020|max:2100|unique:planning_years,year'], ['year.unique' => 'هذه السنة موجودة مسبقًا.'], ['year' => 'السنة']);
        $y = app(YearService::class)->create((int) $d['year'], $r->user());
        session(['year_id' => $y->id, 'quarter' => 1]);

        return back()->with('ok', 'أُنشئت سنة ' . $y->year . ' بأرباعها الأربعة. يستطيع كل مسؤول الآن إنشاء خطته أو نسخ هيكل خطته السابقة.');
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
