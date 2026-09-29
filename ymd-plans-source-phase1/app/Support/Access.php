<?php

namespace App\Support;

use App\Models\Delegation;
use App\Models\ExecutiveMembership;
use App\Models\Plan;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * مصدر واحد لقواعد الصلاحيات. تُستدعى من السياسات والمتحكمات وواجهات API والتنزيلات،
 * ولا تعتمد على ما يظهر في الواجهة.
 *
 * - الرؤية الشاملة: الرئيس، مسؤول التخطيط والمتابعة، وأعضاء الإدارة التنفيذية المعتمدون (عضوية سارية).
 * - نائب الرئيس لا يحصل على رؤية شاملة بمنصبه (global_view = false) إلا بعضوية تنفيذية صريحة.
 * - مدير النظام التقني (is_system_admin) يدير الحسابات فقط، ولا يقرأ الخطط ما لم يشغل منصبًا ذا صلاحية.
 */
class Access
{
    /** @var array<int, array> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function info(User $u): array
    {
        if (! isset(self::$cache[$u->id])) {
            $positions = $u->positions()->get();
            self::$cache[$u->id] = [
                'positions' => $positions,
                'position_ids' => $positions->pluck('id')->all(),
                'president' => $positions->contains(fn ($p) => $p->is_president),
                'planning' => $positions->contains(fn ($p) => $p->is_planning),
                'position_global' => $positions->contains(fn ($p) => $p->global_view),
                'executive' => ExecutiveMembership::where('user_id', $u->id)->active()->exists(),
                'delegation' => Delegation::where('user_id', $u->id)->where('permission', 'approve_plans')->active()->first(),
            ];
        }

        return self::$cache[$u->id];
    }

    public static function positions(User $u): Collection { return self::info($u)['positions']; }
    public static function positionIds(User $u): array { return self::info($u)['position_ids']; }
    public static function holdsPosition(User $u, int $positionId): bool { return in_array($positionId, self::info($u)['position_ids'], true); }
    public static function isPresident(User $u): bool { return self::info($u)['president']; }
    public static function isPlanning(User $u): bool { return self::info($u)['planning']; }
    public static function isExecutive(User $u): bool { return self::info($u)['executive']; }
    public static function activeDelegation(User $u): ?Delegation { return self::info($u)['delegation']; }

    public static function hasGlobalView(User $u): bool
    {
        if (! $u->is_active) {
            return false;
        }
        $i = self::info($u);

        return $i['position_global'] || $i['executive'];
    }

    public static function canViewPlan(User $u, Plan $p): bool
    {
        return $u->is_active && (self::hasGlobalView($u) || self::holdsPosition($u, (int) $p->position_id) || self::delegateSees($u, $p));
    }

    /**
     * المفوَّض بالاعتماد (بوثيقة سارية) يرى فقط الخطط التي تنتظر قراره: الموصى باعتمادها،
     * أو التي عليها طلب تعديل قيد القرار. لا يحصل على رؤية شاملة.
     */
    public static function delegateSees(User $u, Plan $p): bool
    {
        if (! self::activeDelegation($u)) {
            return false;
        }

        return $p->status === 'recommended' || $p->changeRequests()->where('status', 'pending')->exists();
    }

    /** صاحب المنصب فقط ينشئ ويعدّل مسودة خطته ويرسلها */
    public static function isPlanOwner(User $u, Plan $p): bool
    {
        return $u->is_active && self::holdsPosition($u, (int) $p->position_id);
    }

    public static function canEditPlan(User $u, Plan $p): bool
    {
        return self::isPlanOwner($u, $p) && $p->isEditable();
    }

    public static function canReview(User $u): bool { return $u->is_active && self::isPlanning($u); }

    public static function canApprove(User $u): bool
    {
        return $u->is_active && (self::isPresident($u) || self::activeDelegation($u) !== null);
    }

    public static function canPostUpdate(User $u, Plan $p): bool
    {
        return self::isPlanOwner($u, $p) && $p->acceptsUpdates();
    }

    public static function canManageAccounts(User $u): bool { return $u->is_active && ($u->is_system_admin || self::isPresident($u)); }

    /** منح/إيقاف عضوية الإدارة التنفيذية والتفويض: قرار تنظيمي للرئيس، ويستطيع مدير النظام تنفيذه تقنيًا مع التوثيق */
    public static function canManageExecutive(User $u): bool { return self::canManageAccounts($u); }

    public static function canManageYears(User $u): bool { return $u->is_active && (self::isPlanning($u) || self::isPresident($u)); }

    /** الأهداف الاستراتيجية: يديرها الرئيس ومسؤولة التخطيط (بموافقة مالك النظام)، ويطّلع عليها كل صاحب منصب */
    public static function canManageStrategicGoals(User $u): bool { return $u->is_active && (self::isPresident($u) || self::isPlanning($u)); }

    public static function canSeeStrategicGoals(User $u): bool { return $u->is_active && (self::positions($u)->isNotEmpty() || self::hasGlobalView($u)); }

    public static function canSeeExecutiveBoard(User $u): bool { return self::hasGlobalView($u); }

    public static function canSeeAudit(User $u): bool { return self::hasGlobalView($u) || $u->is_system_admin; }

    /** نطاق الخطط المرئية يُطبّق على كل الاستعلامات (القوائم، البحث، التقارير، API) */
    public static function visiblePlans(User $u): Builder
    {
        $q = Plan::query();
        if (! $u->is_active) {
            return $q->whereRaw('1 = 0');
        }
        if (self::hasGlobalView($u)) {
            return $q;
        }

        $ids = self::positionIds($u) ?: [0];
        if (self::activeDelegation($u)) {
            return $q->where(fn ($w) => $w->whereIn('position_id', $ids)->orWhere('status', 'recommended')
                ->orWhereHas('changeRequests', fn ($c) => $c->where('status', 'pending')));
        }

        return $q->whereIn('position_id', $ids);
    }

    /** مساحات العمل المتاحة للمستخدم */
    public static function workspaces(User $u): array
    {
        $ws = [];
        foreach (self::positions($u) as $p) {
            $ws['pos:' . $p->id] = ['key' => 'pos:' . $p->id, 'label' => $p->name, 'type' => 'position', 'position' => $p];
        }
        if (self::isPlanning($u)) {
            $ws['planning'] = ['key' => 'planning', 'label' => 'مركز التخطيط والمتابعة', 'type' => 'planning'];
        }
        if (self::canSeeExecutiveBoard($u) && (self::isPresident($u) || self::isExecutive($u))) {
            $ws['executive'] = ['key' => 'executive', 'label' => 'لوحة الرئيس والإدارة التنفيذية', 'type' => 'executive'];
        }
        if ($u->is_system_admin) {
            $ws['admin'] = ['key' => 'admin', 'label' => 'إدارة الحسابات والصلاحيات', 'type' => 'admin'];
        }

        return $ws;
    }

    public static function usersHoldingPosition(int $positionId): Collection
    {
        return Position::find($positionId)?->users()
            ->where('users.is_active', true)
            ->where(fn ($q) => $q->whereNull('position_user.ends_on')->orWhere('position_user.ends_on', '>=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('position_user.starts_on')->orWhere('position_user.starts_on', '<=', now()->toDateString()))
            ->get() ?? collect();
    }

    public static function usersWithRole(string $flag): Collection
    {
        $posIds = Position::where($flag, true)->pluck('id');

        return $posIds->flatMap(fn ($id) => self::usersHoldingPosition($id))->unique('id')->values();
    }
}
