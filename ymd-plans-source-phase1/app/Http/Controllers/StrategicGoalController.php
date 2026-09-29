<?php

namespace App\Http\Controllers;

use App\Models\Objective;
use App\Models\StrategicGoal;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * الأهداف الاستراتيجية للجمعية.
 * - الإدارة (إنشاء/تعديل/أرشفة/حذف غير المرتبط): الرئيس ومسؤولة التخطيط.
 * - الاطلاع: كل صاحب منصب. صاحب المنصب المقيّد يرى أهداف خطته المرتبطة فقط، ولا يرى أهداف خطط المناصب الأخرى.
 */
class StrategicGoalController extends Controller
{
    private const LABELS = ['title' => 'عنوان الهدف الاستراتيجي', 'from_year' => 'من سنة', 'to_year' => 'إلى سنة', 'description' => 'الوصف', 'sort' => 'الترتيب'];

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'from_year' => 'nullable|integer|min:2020|max:2100',
            'to_year' => 'nullable|integer|min:2020|max:2100|gte:from_year',
            'sort' => 'nullable|integer|min:0|max:999',
        ];
    }

    private function guardManage(): void
    {
        abort_unless(Access::canManageStrategicGoals(auth()->user()), 403, 'إدارة الأهداف الاستراتيجية للرئيس ومسؤولة التخطيط والمتابعة.');
    }

    /** أهداف الخطط المرتبطة التي يحق للمستخدم رؤيتها فقط */
    private function visibleObjectives(StrategicGoal $g)
    {
        return Objective::where('strategic_goal_id', $g->id)
            ->whereIn('plan_id', Access::visiblePlans(auth()->user())->select('id'))
            ->with(['plan.position', 'plan.year'])->orderBy('plan_id')->get();
    }

    public function index()
    {
        abort_unless(Access::canSeeStrategicGoals(auth()->user()), 403);
        $goals = StrategicGoal::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('sort')->orderBy('id')->get();
        $visible = Access::visiblePlans(auth()->user())->select('id');
        $counts = Objective::whereIn('plan_id', $visible)->whereNotNull('strategic_goal_id')
            ->selectRaw('strategic_goal_id, count(*) as n')->groupBy('strategic_goal_id')->pluck('n', 'strategic_goal_id');
        $canManage = Access::canManageStrategicGoals(auth()->user());

        return view('strategic-goals.index', compact('goals', 'counts', 'canManage'));
    }

    public function show(StrategicGoal $goal)
    {
        abort_unless(Access::canSeeStrategicGoals(auth()->user()), 403);
        $objectives = $this->visibleObjectives($goal);
        $canManage = Access::canManageStrategicGoals(auth()->user());
        $history = $canManage ? \App\Models\AuditLog::where('entity_type', 'StrategicGoal')->where('entity_id', $goal->id)->with('user')->latest('id')->get() : collect();
        $linkedTotal = $canManage ? Objective::where('strategic_goal_id', $goal->id)->count() : null;

        return view('strategic-goals.show', compact('goal', 'objectives', 'canManage', 'history', 'linkedTotal'));
    }

    public function store(Request $r)
    {
        $this->guardManage();
        $d = $r->validate($this->rules(), [], self::LABELS);
        $g = StrategicGoal::create($d + ['status' => 'active', 'created_by' => $r->user()->id, 'sort' => $d['sort'] ?? (int) StrategicGoal::max('sort') + 1]);
        Audit::log('strategic_goal.create', $g, null, $g->only(['ref', 'title', 'from_year', 'to_year']));

        return redirect()->route('strategic-goals.show', $g)->with('ok', 'أُنشئ الهدف الاستراتيجي ' . $g->ref . '.');
    }

    public function update(Request $r, StrategicGoal $goal)
    {
        $this->guardManage();
        $d = $r->validate($this->rules(), [], self::LABELS);
        $old = $goal->only(array_keys($d));
        $goal->update($d);
        $changed = array_keys(array_diff_assoc(array_map('strval', $goal->only(array_keys($d))), array_map('strval', $old)));
        if ($changed) {
            Audit::log('strategic_goal.update', $goal, array_intersect_key($old, array_flip($changed)), $goal->only($changed));
        }

        return back()->with('ok', 'حُفظ الهدف الاستراتيجي.');
    }

    /** أرشفة (لا يظهر للربط الجديد ويبقى مرتبطًا بما سبق) أو إعادة تفعيل */
    public function status(Request $r, StrategicGoal $goal)
    {
        $this->guardManage();
        $d = $r->validate(['status' => 'required|in:active,archived']);
        $old = $goal->status;
        $goal->update(['status' => $d['status']]);
        Audit::log('strategic_goal.' . ($d['status'] === 'archived' ? 'archive' : 'restore'), $goal, ['status' => $old], ['status' => $goal->status]);

        return back()->with('ok', $d['status'] === 'archived' ? 'أُرشف الهدف؛ تبقى الروابط السابقة كما هي ولا يظهر للربط الجديد.' : 'أُعيد تفعيل الهدف.');
    }

    /** الحذف مسموح فقط لهدف لم يُربط بأي هدف خطة؛ وإلا يُؤرشف */
    public function destroy(StrategicGoal $goal)
    {
        $this->guardManage();
        abort_if(Objective::where('strategic_goal_id', $goal->id)->exists(), 422, 'الهدف مرتبط بأهداف خطط؛ استخدم الأرشفة بدل الحذف.');
        Audit::log('strategic_goal.delete', $goal, $goal->only(['ref', 'title', 'description', 'from_year', 'to_year', 'status']), null);
        $goal->delete();

        return redirect()->route('strategic-goals.index')->with('ok', 'حُذف الهدف الاستراتيجي (رقمه المرجعي لن يُعاد استخدامه).');
    }
}
