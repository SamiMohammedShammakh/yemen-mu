<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** إدارة الحسابات وإسناد المناصب — لمدير النظام التقني والرئيس. لا تمنح قراءة الخطط بحد ذاتها. */
class UserController extends Controller
{
    private function guard(): void
    {
        abort_unless(Access::canManageAccounts(auth()->user()), 403);
    }

    public function index()
    {
        $this->guard();
        $users = User::with(['positionAssignments.position', 'executiveMemberships' => fn ($q) => $q->whereNull('revoked_at')])->orderBy('name')->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $this->guard();

        return view('admin.users.form', ['user' => null, 'positions' => Position::orderBy('sort')->get()]);
    }

    public function store(Request $r)
    {
        $this->guard();
        $d = $r->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:30',
            'password' => ['required', Password::min(8)->letters()->numbers()], 'is_system_admin' => 'nullable|boolean',
            'position_id' => 'nullable|exists:positions,id', 'starts_on' => 'nullable|date',
        ], [], ['name' => 'الاسم', 'email' => 'البريد', 'password' => 'كلمة المرور']);
        $u = User::create(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'password' => $d['password'], 'is_active' => true,
            'is_system_admin' => $r->boolean('is_system_admin') && auth()->user()->is_system_admin]);
        Audit::log('user.create', $u, null, $u->only(['name', 'email', 'is_system_admin']));
        if (! empty($d['position_id'])) {
            $this->doAssign($u, (int) $d['position_id'], $d['starts_on'] ?? now()->toDateString(), true);
        }

        return redirect()->route('admin.users.edit', $u)->with('ok', 'أُنشئ الحساب.');
    }

    public function edit(User $user)
    {
        $this->guard();
        $user->load(['positionAssignments.position', 'positionAssignments.assigner']);

        return view('admin.users.form', ['user' => $user, 'positions' => Position::orderBy('sort')->get()]);
    }

    public function update(Request $r, User $user)
    {
        $this->guard();
        $d = $r->validate([
            'name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)], 'phone' => 'nullable|string|max:30',
            'password' => ['nullable', Password::min(8)->letters()->numbers()], 'is_active' => 'nullable|boolean', 'is_system_admin' => 'nullable|boolean',
        ]);
        $old = $user->only(['name', 'email', 'phone', 'is_active', 'is_system_admin']);
        $user->fill(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null, 'is_active' => $r->boolean('is_active')]);
        if (auth()->user()->is_system_admin) {
            $user->is_system_admin = $r->boolean('is_system_admin');
        }
        if (! empty($d['password'])) {
            $user->password = $d['password'];
        }
        abort_if($user->id === auth()->id() && ! $user->is_active, 422, 'لا يمكنك إيقاف حسابك.');
        $user->save();
        Audit::log('user.update', $user, $old, $user->only(['name', 'email', 'phone', 'is_active', 'is_system_admin']) + ['password_changed' => ! empty($d['password'])]);

        return back()->with('ok', 'حُفظت بيانات الحساب.');
    }

    private function doAssign(User $u, int $positionId, string $startsOn, bool $primary): void
    {
        if ($primary) {
            PositionAssignment::where('user_id', $u->id)->update(['is_primary' => false]);
        }
        $a = PositionAssignment::create(['user_id' => $u->id, 'position_id' => $positionId, 'is_primary' => $primary, 'starts_on' => $startsOn, 'assigned_by' => auth()->id()]);
        Audit::log('assignment.create', $a, null, ['user' => $u->name, 'position' => Position::find($positionId)->name, 'starts_on' => $startsOn]);
    }

    public function assign(Request $r, User $user)
    {
        $this->guard();
        $d = $r->validate(['position_id' => 'required|exists:positions,id', 'starts_on' => 'required|date', 'is_primary' => 'nullable|boolean']);
        $dup = PositionAssignment::where('user_id', $user->id)->where('position_id', $d['position_id'])
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->exists();
        abort_if($dup, 422, 'المنصب مسند لهذا المستخدم حاليًا.');
        $this->doAssign($user, (int) $d['position_id'], $d['starts_on'], $r->boolean('is_primary'));

        return back()->with('ok', 'أُسند المنصب.');
    }

    public function endAssignment(Request $r, PositionAssignment $assignment)
    {
        $this->guard();
        $d = $r->validate(['ends_on' => 'required|date']);
        $old = $assignment->only(['ends_on']);
        $assignment->update(['ends_on' => $d['ends_on']]);
        Audit::log('assignment.end', $assignment, $old, $d + ['user' => $assignment->user->name, 'position' => $assignment->position->name]);

        return back()->with('ok', 'أُنهي إسناد المنصب اعتبارًا من ' . $d['ends_on'] . '.');
    }
}
