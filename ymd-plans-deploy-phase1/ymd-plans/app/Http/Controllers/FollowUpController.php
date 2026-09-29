<?php

namespace App\Http\Controllers;

use App\Models\CorrectiveAction;
use App\Models\FollowUpNote;
use App\Models\Plan;
use App\Services\AlertService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    /** ملاحظة متابعة من التخطيط، أو رد من صاحب المنصب */
    public function note(Request $r, Plan $plan)
    {
        $this->canView($plan);
        $u = $r->user();
        $d = $r->validate(['body' => 'required|string|max:5000', 'parent_id' => 'nullable|integer']);
        $parent = $d['parent_id'] ? $plan->notes()->whereKey($d['parent_id'])->firstOrFail() : null;
        // الملاحظة الجديدة لمسؤول التخطيط (أو الرئيس/التنفيذية)، والرد لصاحب المنصب أو التخطيط
        abort_unless($parent ? (Access::isPlanOwner($u, $plan) || Access::hasGlobalView($u)) : Access::hasGlobalView($u), 403);
        $n = FollowUpNote::create(['plan_id' => $plan->id, 'parent_id' => $parent?->id, 'from_user_id' => $u->id, 'body' => $d['body']]);
        Audit::log($parent ? 'note.reply' : 'note.create', $n, null, ['body' => $d['body']]);
        app(AlertService::class)->followUp($n);

        return back()->with('ok', $parent ? 'أُرسل الرد.' : 'أُرسلت ملاحظة المتابعة.');
    }

    public function storeAction(Request $r, Plan $plan)
    {
        $this->canView($plan);
        abort_unless(Access::canReview($r->user()), 403, 'إنشاء الإجراءات التصحيحية لمسؤول التخطيط والمتابعة.');
        $d = $r->validate([
            'title' => 'required|string|max:255', 'reason' => 'required|string|max:3000', 'owner_user_id' => 'required|exists:users,id',
            'due_on' => 'required|date', 'expected_result' => 'required|string|max:3000', 'indicator_id' => 'nullable|integer', 'task_id' => 'nullable|integer',
        ], [], ['title' => 'عنوان الإجراء', 'reason' => 'السبب', 'owner_user_id' => 'المالك', 'due_on' => 'الموعد', 'expected_result' => 'النتيجة المتوقعة']);
        abort_if($d['indicator_id'] && ! $plan->indicators()->whereKey($d['indicator_id'])->exists(), 422);
        abort_if($d['task_id'] && ! $plan->tasks()->whereKey($d['task_id'])->exists(), 422);
        $a = $plan->correctiveActions()->create($d + ['created_by' => $r->user()->id, 'status' => 'open']);
        Audit::log('corrective.create', $a, null, $d);
        app(AlertService::class)->corrective($a);

        return back()->with('ok', 'أُنشئ الإجراء التصحيحي وأُبلغ مالكه.');
    }

    public function updateAction(Request $r, CorrectiveAction $action)
    {
        $plan = $action->plan;
        $this->canView($plan);
        $u = $r->user();
        abort_unless(Access::canReview($u) || $action->owner_user_id === $u->id || Access::isPlanOwner($u, $plan), 403);
        $d = $r->validate(['status' => 'required|in:open,done,cancelled', 'result_note' => 'nullable|string|max:3000']);
        if ($d['status'] === 'cancelled') {
            abort_unless(Access::canReview($u), 403);
        }
        $old = $action->only(['status', 'result_note']);
        $action->update($d);
        Audit::log('corrective.update', $action, $old, $d);

        return back()->with('ok', 'حُدّث الإجراء التصحيحي.');
    }
}
