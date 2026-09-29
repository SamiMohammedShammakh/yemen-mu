<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ChangeRequest;
use App\Models\CorrectiveAction;
use App\Models\FollowUpNote;
use App\Models\Plan;
use App\Models\PlanningYear;
use App\Models\ProgressUpdate;
use App\Models\User;
use App\Support\Access;

/**
 * التنبيهات: تُنشأ فورًا مع الأحداث، ودوريًا بأمر alerts:generate (مجدول يوميًا)
 * للتحديثات المستحقة والمهام المتأخرة والمؤشرات تحت المستهدف والأدلة التي تنتظر المراجعة.
 */
class AlertService
{
    public function notify($users, string $type, string $title, ?string $body, ?string $url, ?Plan $plan, ?string $dedupe = null): void
    {
        foreach (collect($users)->filter()->unique('id') as $u) {
            if ($dedupe) {
                Alert::firstOrCreate(['user_id' => $u->id, 'dedupe_key' => $dedupe],
                    ['plan_id' => $plan?->id, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url]);
            } else {
                Alert::create(['user_id' => $u->id, 'plan_id' => $plan?->id, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url]);
            }
        }
    }

    private function owners(Plan $p) { return Access::usersHoldingPosition($p->position_id); }
    private function planners() { return Access::usersWithRole('is_planning'); }
    private function presidents() { return Access::usersWithRole('is_president'); }

    public function workflow(Plan $p, string $action, User $by): void
    {
        $t = $p->title();
        $url = route('plans.show', $p);
        match ($action) {
            'submit' => $this->notify($this->planners(), 'workflow', "خطة بانتظار مراجعتك: $t", null, route('plans.review', $p), $p),
            'return' => $this->notify($this->owners($p), 'workflow', "أُعيدت خطتك للتعديل: $t", null, route('plans.review', $p), $p),
            'recommend' => $this->notify($this->presidents()->merge(\App\Models\Delegation::active()->with('user')->get()->pluck('user')), 'workflow', "خطة موصى باعتمادها: $t", null, route('plans.review', $p), $p),
            'approve' => $this->notify($this->owners($p)->merge($this->planners()), 'workflow', "اعتُمدت الخطة: $t (v{$p->current_version})", null, $url, $p),
            'activate', 'close' => $this->notify($this->owners($p), 'workflow', ($action === 'activate' ? 'فُعّلت الخطة: ' : 'أُغلقت الخطة: ') . $t, null, $url, $p),
            default => null,
        };
    }

    public function updateSubmitted(ProgressUpdate $u): void
    {
        $this->notify($this->planners(), 'evidence_pending', 'تحديث بانتظار التحقق: ' . $u->plan->position->name,
            $u->indicator?->name ?? $u->task?->title, route('verification.index'), $u->plan, 'evidence:' . $u->id);
    }

    public function updateReviewed(ProgressUpdate $u): void
    {
        $what = $u->indicator?->name ?? $u->task?->title;
        $this->notify($this->owners($u->plan), 'update_reviewed',
            ($u->status === 'approved' ? 'اعتُمد التحديث: ' : 'أُعيد التحديث مع سبب: ') . $what, $u->review_note,
            $u->indicator_id ? route('indicators.show', $u->indicator_id) : route('plans.show', [$u->plan, 'tab' => 'tasks']), $u->plan);
    }

    public function followUp(FollowUpNote $n): void
    {
        $plan = $n->plan;
        $to = Access::isPlanOwner($n->author, $plan) ? $this->planners() : $this->owners($plan);
        $this->notify($to->reject(fn ($x) => $x->id === $n->from_user_id), 'follow_up', 'ملاحظة متابعة: ' . $plan->title(), mb_substr($n->body, 0, 140), route('plans.show', [$plan, 'tab' => 'notes']), $plan);
    }

    public function corrective(CorrectiveAction $a): void
    {
        $this->notify([$a->owner], 'corrective_action', 'إجراء تصحيحي مطلوب: ' . $a->title, 'الموعد: ' . $a->due_on->toDateString(), route('plans.show', [$a->plan, 'tab' => 'notes']), $a->plan);
    }

    public function changeRequestCreated(ChangeRequest $cr): void
    {
        $this->notify($this->presidents()->merge($this->planners()), 'change_request', 'طلب تعديل بانتظار القرار: ' . $cr->plan->title(), $cr->reason, route('change-requests.show', $cr), $cr->plan);
    }

    public function changeRequestDecided(ChangeRequest $cr): void
    {
        $this->notify($this->owners($cr->plan)->push($cr->requester), 'change_request', ($cr->status === 'approved' ? 'اعتُمد طلب التعديل #' : 'رُفض طلب التعديل #') . $cr->id, $cr->decision_note, route('change-requests.show', $cr), $cr->plan);
    }

    /** التوليد الدوري */
    public function generate(?PlanningYear $year = null): int
    {
        $before = Alert::count();
        $years = $year ? collect([$year]) : PlanningYear::where('status', 'open')->get();
        $today = now()->startOfDay();
        foreach ($years as $y) {
            $y->load('quarters');
            $results = new Results();
            foreach (Plan::where('planning_year_id', $y->id)->whereIn('status', ['approved', 'active'])->with(['position', 'year.quarters', 'indicators.targets', 'indicators.updates', 'tasks'])->get() as $plan) {
                $owners = $this->owners($plan);
                foreach ($y->quarters as $q) {
                    if ($q->ends_on->gte($today) || $q->isClosed()) continue;
                    foreach ($plan->indicators as $i) {
                        if ($i->measuredInQuarter($q->number) && ! $i->updates->where('quarter', $q->number)->count()) {
                            $this->notify($owners, 'update_due', 'تحديث مستحق: ' . $i->name, $q->label() . ' ' . $y->year, route('indicators.show', $i), $plan, "due:{$i->id}:{$q->number}");
                        }
                    }
                }
                foreach ($plan->tasks as $t) {
                    if ($t->isOverdue()) {
                        $this->notify($owners, 'task_overdue', 'مهمة متأخرة: ' . $t->title, 'الموعد: ' . $t->due_on->toDateString(), route('plans.show', [$plan, 'tab' => 'tasks']), $plan, "overdue:{$t->id}:" . $t->due_on->toDateString());
                    }
                }
                $current = $y->quarters->first(fn ($q) => $q->starts_on->lte($today) && $q->ends_on->gte($today))?->number ?? 4;
                $res = $results->plan($plan, $current);
                foreach ($res['objectives'] as $o) {
                    foreach ($o['indicators'] as $i) {
                        if ($i['status']['key'] === 'late') {
                            $this->notify($owners->merge($this->planners()), 'below_target', 'مؤشر تحت المستهدف: ' . $i['name'],
                                $i['period']['compare_label'] . ' — ' . \App\Support\Fmt::pct($i['period']['achievement']), route('indicators.show', $i['id']), $plan, "below:{$i['id']}:{$current}");
                        }
                    }
                }
            }
        }
        foreach (ProgressUpdate::where('status', 'pending')->where('created_at', '<', now()->subDays(3))->with('plan.position')->get() as $u) {
            $this->notify($this->planners(), 'evidence_pending', 'دليل ينتظر المراجعة منذ أكثر من 3 أيام: ' . $u->plan->position->name, null, route('verification.index'), $u->plan, 'stale:' . $u->id);
        }

        return Alert::count() - $before;
    }
}
