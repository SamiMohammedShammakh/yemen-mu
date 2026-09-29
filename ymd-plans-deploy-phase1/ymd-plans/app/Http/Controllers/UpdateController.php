<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Plan;
use App\Models\ProgressUpdate;
use App\Models\Task;
use App\Services\ProgressService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    public const FILE_RULE = 'file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip';

    public function store(Request $r, Plan $plan)
    {
        $this->canView($plan);
        $d = $r->validate([
            'indicator_id' => 'nullable|integer', 'task_id' => 'nullable|integer', 'quarter' => 'required|integer|between:1,4',
            'period_label' => 'nullable|string|max:100', 'actual_value' => 'nullable|numeric', 'participants' => 'nullable|integer|min:0',
            'achieved' => 'nullable|string|max:5000', 'not_achieved' => 'nullable|string|max:5000', 'delay_reason' => 'nullable|string|max:5000',
            'obstacles' => 'nullable|string|max:5000', 'support_needed' => 'nullable|string|max:5000', 'claims_completion' => 'nullable|boolean',
            'files' => 'array|max:5', 'files.*' => self::FILE_RULE,
        ], [], ['actual_value' => 'القيمة الفعلية', 'files.*' => 'المرفق']);
        $upd = app(ProgressService::class)->submit($r->user(), $plan, $d, $r->file('files', []));

        return back()->with('ok', 'سُجّل التحديث #' . $upd->id . ' وهو بانتظار تحقق مسؤول التخطيط. لا يدخل في الإنجاز الرسمي قبل اعتماده.');
    }

    /** قائمة الأدلة بانتظار التحقق — لمسؤول التخطيط */
    public function queue()
    {
        abort_unless(Access::canReview(auth()->user()), 403);
        $updates = ProgressUpdate::where('status', 'pending')->with(['plan.position', 'plan.year', 'indicator.targets', 'task', 'creator', 'attachments'])->oldest()->get();
        $recent = ProgressUpdate::whereIn('status', ['approved', 'returned'])->where('is_adjustment', false)->with(['plan.position', 'indicator', 'task', 'reviewer'])->latest('reviewed_at')->limit(15)->get();

        return view('verification.index', compact('updates', 'recent'));
    }

    public function review(Request $r, ProgressUpdate $update)
    {
        $this->canView($update->plan);
        $d = $r->validate(['decision' => 'required|in:approve,return', 'review_note' => 'nullable|string|max:2000']);
        app(ProgressService::class)->review($r->user(), $update, $d['decision'] === 'approve', $d['review_note'] ?? null);

        return back()->with('ok', $d['decision'] === 'approve' ? 'اعتُمد التحديث ودخل في الإنجاز الرسمي.' : 'أُعيد التحديث للمسؤول مع السبب.');
    }

    public function defer(Request $r, Task $task)
    {
        $this->canView($task->plan);
        $d = $r->validate(['reason' => 'required|string|max:2000', 'new_due_on' => 'required|date', 'owner_user_id' => 'nullable|exists:users,id'],
            [], ['reason' => 'سبب النقل', 'new_due_on' => 'الموعد الجديد']);
        app(ProgressService::class)->defer($r->user(), $task, $d);

        return back()->with('ok', 'نُقلت المهمة إلى الربع التالي، ويبقى تأخرها ظاهرًا في ربعها الأصلي.');
    }

    /** تنزيل مرفق: يُتحقق من صلاحية الخطة في كل طلب */
    public function attachment(Attachment $attachment)
    {
        $this->canView($attachment->plan);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        Audit::log('attachment.download', $attachment, null, null, $attachment->plan_id);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
