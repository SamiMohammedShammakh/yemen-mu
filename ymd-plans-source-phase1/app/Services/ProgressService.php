<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Plan;
use App\Models\ProgressUpdate;
use App\Models\Task;
use App\Models\TaskDeferral;
use App\Models\User;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    /** @param UploadedFile[] $files */
    public function submit(User $u, Plan $plan, array $data, array $files = []): ProgressUpdate
    {
        abort_unless(Access::canPostUpdate($u, $plan), 403, 'لا يمكنك إضافة تحديث لهذه الخطة.');
        $q = (int) $data['quarter'];
        $quarter = $plan->year->quarters()->where('number', $q)->firstOrFail();
        if ($quarter->isClosed()) {
            throw ValidationException::withMessages(['quarter' => 'الربع مغلق. لتعديل نتيجته قدّم طلب تغيير.']);
        }
        $indicator = isset($data['indicator_id']) ? $plan->indicators()->whereKey($data['indicator_id'])->first() : null;
        $task = isset($data['task_id']) ? $plan->tasks()->whereKey($data['task_id'])->first() : null;
        if (! $indicator && ! $task) {
            throw ValidationException::withMessages(['target' => 'اختر مؤشرًا أو مهمة من خطتك.']);
        }
        if ($indicator && ($data['actual_value'] ?? null) === null) {
            throw ValidationException::withMessages(['actual_value' => 'أدخل القيمة الفعلية للفترة.']);
        }

        return DB::transaction(function () use ($u, $plan, $data, $files, $indicator, $task, $q) {
            $upd = ProgressUpdate::create([
                'plan_id' => $plan->id,
                'indicator_id' => $indicator?->id,
                'task_id' => $task?->id,
                'quarter' => $q,
                'period_label' => $data['period_label'] ?? null,
                'actual_value' => $indicator ? $data['actual_value'] : null,
                'participants' => $data['participants'] ?? null,
                'achieved' => $data['achieved'] ?? null,
                'not_achieved' => $data['not_achieved'] ?? null,
                'delay_reason' => $data['delay_reason'] ?? null,
                'obstacles' => $data['obstacles'] ?? null,
                'support_needed' => $data['support_needed'] ?? null,
                'claims_completion' => (bool) ($data['claims_completion'] ?? false),
                'status' => 'pending',
                'plan_version_no' => $plan->current_version,
                'created_by' => $u->id,
            ]);
            foreach ($files as $f) {
                $this->storeAttachment($u, $plan, $f, $upd, $task);
            }
            if ($task) {
                $task->update(['status' => $upd->claims_completion ? 'pending_verification' : ($task->status === 'planned' ? 'in_progress' : $task->status)]);
            }
            Audit::log('update.create', $upd, null, $upd->only(['indicator_id', 'task_id', 'quarter', 'actual_value', 'claims_completion']));
            app(AlertService::class)->updateSubmitted($upd);

            return $upd;
        });
    }

    public function storeAttachment(User $u, Plan $plan, UploadedFile $f, ?ProgressUpdate $upd = null, ?Task $task = null): Attachment
    {
        $path = $f->store('evidence/' . $plan->id, 'local');

        return Attachment::create([
            'plan_id' => $plan->id,
            'progress_update_id' => $upd?->id,
            'task_id' => $task?->id,
            'original_name' => $f->getClientOriginalName(),
            'path' => $path,
            'mime' => $f->getMimeType(),
            'size' => $f->getSize(),
            'sha256' => hash_file('sha256', $f->getRealPath()),
            'uploaded_by' => $u->id,
        ]);
    }

    /** اعتماد الدليل أو إعادته مع سبب — مسؤول التخطيط فقط */
    public function review(User $u, ProgressUpdate $upd, bool $approve, ?string $note): ProgressUpdate
    {
        abort_unless(Access::canReview($u), 403);
        if ($upd->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'تمت مراجعة هذا التحديث مسبقًا.']);
        }
        if (! $approve && ! trim((string) $note)) {
            throw ValidationException::withMessages(['review_note' => 'اكتب سبب الإعادة.']);
        }
        $quarter = $upd->plan->year->quarters()->where('number', $upd->quarter)->first();
        if ($quarter?->isClosed()) {
            throw ValidationException::withMessages(['quarter' => 'الربع مغلق.']);
        }

        return DB::transaction(function () use ($u, $upd, $approve, $note) {
            $upd->update(['status' => $approve ? 'approved' : 'returned', 'reviewed_by' => $u->id, 'reviewed_at' => now(), 'review_note' => $note]);
            if ($upd->task_id && $upd->claims_completion) {
                $upd->task->update($approve ? ['status' => 'done', 'completed_at' => now()] : ['status' => 'in_progress']);
            }
            Audit::log($approve ? 'update.approve' : 'update.return', $upd, ['status' => 'pending'], ['status' => $upd->status, 'note' => $note]);
            app(AlertService::class)->updateReviewed($upd);

            return $upd;
        });
    }

    /** نقل مهمة إلى الربع التالي مع إبقاء تأخرها ظاهرًا في ربعها الأصلي */
    public function defer(User $u, Task $task, array $data): TaskDeferral
    {
        $plan = $task->plan;
        abort_unless((Access::isPlanOwner($u, $plan) || Access::canReview($u)) && $plan->acceptsUpdates(), 403);
        if ($task->status === 'done') {
            throw ValidationException::withMessages(['task' => 'المهمة منجزة ولا يمكن نقلها.']);
        }
        if ($task->quarter >= 4) {
            throw ValidationException::withMessages(['task' => 'لا يوجد ربع تالٍ داخل السنة؛ أنشئ المهمة في خطة السنة القادمة.']);
        }

        return DB::transaction(function () use ($u, $task, $data) {
            $d = TaskDeferral::create([
                'task_id' => $task->id, 'from_quarter' => $task->quarter, 'to_quarter' => $task->quarter + 1,
                'reason' => $data['reason'], 'owner_user_id' => $data['owner_user_id'] ?? $task->owner_user_id,
                'new_due_on' => $data['new_due_on'], 'created_by' => $u->id,
            ]);
            $old = $task->only(['quarter', 'due_on', 'owner_user_id']);
            $task->update(['quarter' => $task->quarter + 1, 'due_on' => $data['new_due_on'], 'owner_user_id' => $d->owner_user_id]);
            Audit::log('task.defer', $task, $old, $task->only(['quarter', 'due_on', 'owner_user_id']) + ['reason' => $data['reason']], $task->plan_id);

            return $d;
        });
    }
}
