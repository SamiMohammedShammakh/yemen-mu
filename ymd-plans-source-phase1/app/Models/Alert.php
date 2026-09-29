<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    public const TYPES = [
        'update_due' => 'تحديث مستحق',
        'task_overdue' => 'مهمة متأخرة',
        'below_target' => 'مؤشر تحت المستهدف',
        'evidence_pending' => 'دليل بانتظار المراجعة',
        'update_reviewed' => 'نتيجة مراجعة تحديث',
        'follow_up' => 'ملاحظة متابعة',
        'corrective_action' => 'إجراء تصحيحي',
        'workflow' => 'دورة الاعتماد',
        'change_request' => 'طلب تعديل',
    ];

    protected $fillable = ['user_id', 'plan_id', 'type', 'title', 'body', 'url', 'dedupe_key', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }
}
