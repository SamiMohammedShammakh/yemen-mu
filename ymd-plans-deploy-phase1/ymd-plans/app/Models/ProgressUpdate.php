<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * تحديث إنجاز: سجل زمني ثابت. لا يُسمح بتعديل محتواه أو حذفه بعد الإنشاء؛
 * يُسمح فقط بتسجيل نتيجة المراجعة (اعتماد/إعادة).
 */
class ProgressUpdate extends Model
{
    public const STATUSES = ['pending' => 'بانتظار التحقق', 'approved' => 'معتمد', 'returned' => 'معاد مع سبب'];

    private const REVIEW_FIELDS = ['status', 'reviewed_by', 'reviewed_at', 'review_note', 'updated_at'];

    protected $fillable = ['plan_id', 'indicator_id', 'task_id', 'quarter', 'period_label', 'actual_value', 'participants',
        'achieved', 'not_achieved', 'delay_reason', 'obstacles', 'support_needed', 'claims_completion', 'status',
        'reviewed_by', 'reviewed_at', 'review_note', 'is_adjustment', 'change_request_id', 'plan_version_no', 'created_by'];

    protected $casts = ['actual_value' => 'float', 'claims_completion' => 'boolean', 'is_adjustment' => 'boolean', 'reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $u) {
            $changed = array_keys($u->getDirty());
            if (array_diff($changed, self::REVIEW_FIELDS)) {
                throw new LogicException('لا يمكن تعديل محتوى تحديث مسجّل؛ أضف تحديثًا جديدًا أو اطلب تغييرًا.');
            }
            if ($u->getOriginal('status') !== 'pending') {
                throw new LogicException('تمت مراجعة هذا التحديث مسبقًا.');
            }
        });
        static::deleting(function () {
            throw new LogicException('لا يمكن حذف التحديثات من السجل الزمني.');
        });
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function indicator(): BelongsTo { return $this->belongsTo(Indicator::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
