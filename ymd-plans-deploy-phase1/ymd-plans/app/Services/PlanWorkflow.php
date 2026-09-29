<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanReview;
use App\Models\PlanVersion;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دورة الاعتماد:
 * مسودة ← مرسلة لمراجعة التخطيط ← معادة للتعديل أو موصى باعتمادها ← معتمدة من الرئيس ← نشطة ← مغلقة.
 */
class PlanWorkflow
{
    public const TRANSITIONS = [
        'submit' => ['from' => ['draft', 'returned'], 'to' => 'submitted'],
        'return' => ['from' => ['submitted', 'recommended'], 'to' => 'returned'],
        'recommend' => ['from' => ['submitted'], 'to' => 'recommended'],
        'approve' => ['from' => ['recommended'], 'to' => 'approved'],
        'activate' => ['from' => ['approved'], 'to' => 'active'],
        'close' => ['from' => ['active'], 'to' => 'closed'],
        'note' => ['from' => ['submitted', 'recommended', 'returned', 'draft'], 'to' => null],
    ];

    public function allowed(User $u, Plan $p, string $action): bool
    {
        $t = self::TRANSITIONS[$action] ?? null;
        if (! $t || ! in_array($p->status, $t['from'], true) || ! Access::canViewPlan($u, $p)) {
            return false;
        }

        return match ($action) {
            'submit' => Access::isPlanOwner($u, $p),
            'recommend', 'note' => Access::canReview($u),
            // الإعادة: من التخطيط عند المراجعة، ومن الرئيس/المفوض عند الاعتماد
            'return' => $p->status === 'submitted' ? Access::canReview($u) : Access::canApprove($u),
            'approve' => Access::canApprove($u),
            'activate', 'close' => Access::canApprove($u) || Access::canReview($u),
            default => false,
        };
    }

    public function availableActions(User $u, Plan $p): array
    {
        return array_values(array_filter(array_keys(self::TRANSITIONS), fn ($a) => $this->allowed($u, $p, $a)));
    }

    public function apply(User $u, Plan $p, string $action, ?string $note = null): Plan
    {
        abort_unless($this->allowed($u, $p, $action), 403, 'لا تملك صلاحية تنفيذ هذا الإجراء على الخطة في حالتها الحالية.');

        if (in_array($action, ['return', 'note'], true) && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'اكتب سبب الإعادة أو نص الملاحظة.']);
        }
        if ($action === 'submit' && ($errors = (new PlanValidator())->errors($p))) {
            throw ValidationException::withMessages(['plan' => array_column($errors, 'message')]);
        }
        if ($action === 'approve' && ($errors = (new PlanValidator())->errors($p, false))) {
            throw ValidationException::withMessages(['plan' => array_column($errors, 'message')]);
        }

        return DB::transaction(function () use ($u, $p, $action, $note) {
            $from = $p->status;
            $to = self::TRANSITIONS[$action]['to'] ?? $from;
            $delegation = null;
            if ($action === 'approve') {
                $delegation = Access::isPresident($u) ? null : Access::activeDelegation($u);
                $p->current_version = (int) $p->versions()->max('version_no') + 1;
                $p->status = $to;
                $p->save();
                PlanVersion::where('plan_id', $p->id)->whereNull('effective_to')->update(['effective_to' => now()]);
                PlanVersion::create([
                    'plan_id' => $p->id,
                    'version_no' => $p->current_version,
                    'snapshot' => PlanSnapshot::make($p),
                    'reason' => $note ?: 'الاعتماد الأول للخطة',
                    'approved_by' => $u->id,
                    'delegation_id' => $delegation?->id,
                    'effective_from' => now(),
                ]);
            } else {
                $p->status = $to;
                $p->save();
            }
            PlanReview::create([
                'plan_id' => $p->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
                'note' => $note, 'version_no' => $p->current_version, 'user_id' => $u->id, 'delegation_id' => $delegation?->id,
            ]);
            Audit::log('plan.' . $action, $p, ['status' => $from], ['status' => $to, 'note' => $note, 'version' => $p->current_version]);
            app(AlertService::class)->workflow($p, $action, $u);

            return $p;
        });
    }
}
