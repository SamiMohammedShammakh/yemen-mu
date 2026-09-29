<?php

namespace App\Models;

use App\Support\Refs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** هدف استراتيجي للجمعية — مرجع مشترك تُربط به أهداف خطط المناصب */
class StrategicGoal extends Model
{
    public const STATUSES = ['active' => 'فعّال', 'archived' => 'مؤرشف'];

    protected $fillable = ['ref', 'title', 'description', 'from_year', 'to_year', 'status', 'sort', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $g) {
            $g->ref ??= Refs::strategicGoal();
        });
    }

    public function objectives(): HasMany { return $this->hasMany(Objective::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function label(): string { return $this->ref . ' — ' . $this->title; }

    /** هل ينطبق على سنة معيّنة؟ (فارغ = مفتوح) */
    public function coversYear(int $year): bool
    {
        return (! $this->from_year || $this->from_year <= $year) && (! $this->to_year || $this->to_year >= $year);
    }

    public function scopeUsableFor($q, int $year)
    {
        return $q->where('status', 'active')
            ->where(fn ($w) => $w->whereNull('from_year')->orWhere('from_year', '<=', $year))
            ->where(fn ($w) => $w->whereNull('to_year')->orWhere('to_year', '>=', $year))
            ->orderBy('sort')->orderBy('id');
    }
}
