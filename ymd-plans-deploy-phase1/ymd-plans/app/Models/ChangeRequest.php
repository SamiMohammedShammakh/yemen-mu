<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    public const TYPES = ['plan_amendment' => 'تعديل الخطة المعتمدة', 'closed_quarter_result' => 'تعديل نتيجة ربع مغلق'];
    public const STATUSES = ['pending' => 'قيد القرار', 'approved' => 'معتمد', 'rejected' => 'مرفوض'];

    protected $fillable = ['plan_id', 'type', 'reason', 'changes', 'impact', 'quarter', 'status', 'requested_by',
        'decided_by', 'decided_at', 'decision_note', 'base_version_no', 'resulting_version_no'];

    protected $casts = ['changes' => 'array', 'impact' => 'array', 'decided_at' => 'datetime'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
