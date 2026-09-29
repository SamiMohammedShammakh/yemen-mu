<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    protected $fillable = ['ref', 'plan_id', 'strategic_goal_id', 'title', 'description', 'weight', 'sort'];

    protected $casts = ['weight' => 'float'];

    protected static function booted(): void
    {
        static::creating(function (self $o) {
            $o->ref ??= \App\Support\Refs::objective($o);
        });
    }

    public function strategicGoal(): BelongsTo { return $this->belongsTo(StrategicGoal::class); }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function indicators(): HasMany { return $this->hasMany(Indicator::class)->orderBy('sort')->orderBy('id'); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
}
