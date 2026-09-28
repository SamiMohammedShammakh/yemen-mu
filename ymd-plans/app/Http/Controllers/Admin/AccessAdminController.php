<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delegation;
use App\Models\ExecutiveMembership;
use App\Models\Position;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;

/** مجموعة الإدارة التنفيذية وتفويضات الاعتماد — كل منح أو إيقاف مسجّل بمن ومتى ولماذا */
class AccessAdminController extends Controller
{
    private function guard(): void
    {
        abort_unless(Access::canManageExecutive(auth()->user()), 403);
    }

    public function index()
    {
        $this->guard();
        $memberships = ExecutiveMembership::with(['user', 'granter', 'revoker'])->latest('granted_at')->get();
        $delegations = Delegation::with(['user', 'granter'])->latest()->get();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $positions = Position::orderBy('sort')->get();

        return view('admin.access', compact('memberships', 'delegations', 'users', 'positions'));
    }

    public function grant(Request $r)
    {
        $this->guard();
        $d = $r->validate(['user_id' => 'required|exists:users,id', 'grant_reason' => 'required|string|max:2000'], [], ['grant_reason' => 'سند/سبب المنح']);
        abort_if(ExecutiveMembership::where('user_id', $d['user_id'])->active()->exists(), 422, 'المستخدم عضو حاليًا في الإدارة التنفيذية.');
        $m = ExecutiveMembership::create($d + ['granted_by' => $r->user()->id, 'granted_at' => now()]);
        Audit::log('executive.grant', $m, null, ['user' => $m->user->name, 'reason' => $d['grant_reason']]);

        return back()->with('ok', 'أُضيف ' . $m->user->name . ' إلى الإدارة التنفيذية وأصبح يرى جميع الخطط.');
    }

    public function revoke(Request $r, ExecutiveMembership $membership)
    {
        $this->guard();
        abort_if($membership->revoked_at, 422);
        $d = $r->validate(['revoke_reason' => 'required|string|max:2000'], [], ['revoke_reason' => 'سبب الإيقاف']);
        $membership->update($d + ['revoked_by' => $r->user()->id, 'revoked_at' => now()]);
        Audit::log('executive.revoke', $membership, ['active' => true], ['active' => false, 'user' => $membership->user->name, 'reason' => $d['revoke_reason']]);

        return back()->with('ok', 'أُوقفت عضوية ' . $membership->user->name . ' في الإدارة التنفيذية.');
    }

    public function delegate(Request $r)
    {
        abort_unless(Access::isPresident($r->user()) || $r->user()->is_system_admin, 403);
        $d = $r->validate(['user_id' => 'required|exists:users,id', 'document_ref' => 'required|string|max:255', 'starts_on' => 'required|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on'],
            [], ['document_ref' => 'رقم/مرجع وثيقة التفويض']);
        $del = Delegation::create($d + ['permission' => 'approve_plans', 'granted_by' => $r->user()->id]);
        Audit::log('delegation.grant', $del, null, $d + ['user' => $del->user->name]);

        return back()->with('ok', 'سُجّل تفويض الاعتماد.');
    }

    public function revokeDelegation(Request $r, Delegation $delegation)
    {
        abort_unless(Access::isPresident($r->user()) || $r->user()->is_system_admin, 403);
        $delegation->update(['revoked_by' => $r->user()->id, 'revoked_at' => now()]);
        Audit::log('delegation.revoke', $delegation, null, ['user' => $delegation->user->name]);

        return back()->with('ok', 'أُلغي التفويض.');
    }
}
