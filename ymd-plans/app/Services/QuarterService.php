<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\ProgressUpdate;
use App\Models\Quarter;
use App\Models\QuarterSnapshot;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuarterService
{
    /**
     * إقفال الربع: يحفظ لقطة من الخطة (رقم النسخة) والنتائج والاعتمادات وقواعد الحالات لكل خطة معتمدة/نشطة.
     * يُمنع الإقفال إن وُجدت تحديثات بانتظار التحقق في هذا الربع حتى لا تبقى معلّقة خارج اللقطة.
     */
    public function close(Quarter $quarter, User $by): void
    {
        if ($quarter->isClosed()) {
            throw ValidationException::withMessages(['quarter' => 'هذا الربع مغلق مسبقًا.']);
        }
        $year = $quarter->year;
        $plans = Plan::where('planning_year_id', $year->id)->whereIn('status', ['approved', 'active'])->get();
        $pending = ProgressUpdate::whereIn('plan_id', $plans->pluck('id'))->where('quarter', $quarter->number)->where('status', 'pending')->count();
        if ($pending) {
            throw ValidationException::withMessages(['quarter' => "لا يمكن إقفال الربع: يوجد {$pending} تحديث بانتظار التحقق فيه. راجعها أولًا."]);
        }
        if ($quarter->number > 1 && $year->quarters()->where('number', '<', $quarter->number)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['quarter' => 'أقفل الأرباع السابقة أولًا.']);
        }

        DB::transaction(function () use ($quarter, $plans, $by) {
            $calc = new Calculator();
            foreach ($plans as $plan) {
                $plan->unsetRelation('objectives');
                $data = $calc->plan($plan, $quarter->number);
                $data['approvals'] = $plan->reviews()->whereIn('action', ['approve', 'amend'])->get(['action', 'version_no', 'user_id', 'created_at'])->toArray();
                $data['tasks_list'] = $this->tasksForQuarter($plan, $quarter->number);
                QuarterSnapshot::create([
                    'plan_id' => $plan->id, 'quarter' => $quarter->number, 'revision' => 1,
                    'plan_version_no' => $plan->current_version, 'data' => $data, 'rules' => $calc->rules(), 'created_by' => $by->id,
                ]);
            }
            $quarter->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $by->id]);
            Audit::log('quarter.close', $quarter, null, ['year' => $quarter->year->year, 'quarter' => $quarter->number, 'plans' => $plans->count()]);
        });
    }

    /** مهام الربع، مع إبقاء المهام المنقولة ظاهرة في ربعها الأصلي بتأخرها */
    public function tasksForQuarter(Plan $plan, int $q): array
    {
        return $plan->tasks()->with(['deferrals', 'project'])->get()
            ->filter(fn ($t) => $t->quarter == $q || $t->deferrals->contains('from_quarter', $q))
            ->map(function ($t) use ($q) {
                $def = $t->deferrals->firstWhere('from_quarter', $q);

                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'project' => $t->project?->name,
                    'responsible' => $t->responsible,
                    'due_on' => $t->due_on?->toDateString(),
                    'status' => $def ? 'deferred' : $t->status,
                    'status_label' => $def ? 'متأخرة — نُقلت إلى الربع ' . $def->to_quarter : $t->statusLabel(),
                    'deferral_reason' => $def?->reason,
                    'new_due_on' => $def?->new_due_on?->toDateString(),
                    'overdue' => ! $def && $t->isOverdue(),
                ];
            })->values()->all();
    }
}
