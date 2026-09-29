<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const TYPES = ['initiative' => 'مبادرة', 'project' => 'مشروع', 'activity' => 'نشاط'];

    protected $fillable = ['ref', 'plan_id', 'objective_id', 'parent_id', 'type', 'name', 'description', 'responsible', 'owner_user_id', 'starts_on', 'ends_on', 'resources'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->ref ??= \App\Support\Refs::project($p);
        });
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function activities(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function isActivity(): bool { return $this->type === 'activity'; }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function objective(): BelongsTo { return $this->belongsTo(Objective::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class)->orderBy('quarter')->orderBy('id'); }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? 'مشروع'; }
}
