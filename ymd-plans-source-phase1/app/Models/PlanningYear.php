<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سنة التخطيط. قيم الحالة المخزنة: draft (مسودة) | open (نشطة — القيمة الأصلية) | closed (مغلقة).
 */
class PlanningYear extends Model
{
    public const STATUSES = ['draft' => 'مسودة', 'open' => 'نشطة', 'closed' => 'مغلقة'];

    protected $fillable = ['year', 'name', 'starts_on', 'ends_on', 'owner_user_id', 'plans_due_on', 'description', 'status', 'created_by', 'closed_at', 'closed_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'plans_due_on' => 'date', 'closed_at' => 'datetime'];

    public function quarters(): HasMany { return $this->hasMany(Quarter::class)->orderBy('number'); }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }

    public function quarter(int $n): ?Quarter
    {
        return $this->quarters->firstWhere('number', $n);
    }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function isClosed(): bool { return $this->status === 'closed'; }

    public function isDraft(): bool { return $this->status === 'draft'; }

    public function displayName(): string { return $this->name ?: 'سنة التخطيط ' . $this->year; }

    /** هل تجاوز اليوم موعد اعتماد الخطط؟ */
    public function plansOverdue(): bool
    {
        return $this->plans_due_on && $this->plans_due_on->lt(now()->startOfDay());
    }
}
