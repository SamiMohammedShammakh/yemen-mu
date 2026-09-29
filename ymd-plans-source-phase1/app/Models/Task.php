<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUSES = [
        'planned' => 'مخططة',
        'in_progress' => 'قيد التنفيذ',
        'pending_verification' => 'بانتظار التحقق',
        'done' => 'منجزة (معتمدة)',
    ];

    protected $fillable = ['ref', 'plan_id', 'project_id', 'title', 'description', 'responsible', 'owner_user_id', 'quarter',
        'original_quarter', 'due_on', 'required_evidence', 'status', 'completed_at'];

    protected $casts = ['due_on' => 'date', 'completed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $t) {
            $t->ref ??= \App\Support\Refs::task($t);
        });
    }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function deferrals(): HasMany { return $this->hasMany(TaskDeferral::class)->orderBy('id'); }
    public function updates(): HasMany { return $this->hasMany(ProgressUpdate::class)->orderByDesc('id'); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on && $this->due_on->lt(now()->startOfDay());
    }
}
