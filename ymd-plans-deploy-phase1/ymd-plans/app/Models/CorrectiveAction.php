<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends Model
{
    public const STATUSES = ['open' => 'مفتوح', 'done' => 'منفّذ', 'cancelled' => 'ملغى'];

    protected $fillable = ['plan_id', 'indicator_id', 'task_id', 'title', 'reason', 'owner_user_id', 'due_on',
        'expected_result', 'status', 'result_note', 'created_by'];

    protected $casts = ['due_on' => 'date'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function indicator(): BelongsTo { return $this->belongsTo(Indicator::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function isOverdue(): bool { return $this->status === 'open' && $this->due_on->lt(now()->startOfDay()); }
}
