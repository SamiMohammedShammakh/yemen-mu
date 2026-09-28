<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['plan_id', 'objective_id', 'type', 'name', 'description', 'responsible', 'owner_user_id', 'starts_on', 'ends_on', 'resources'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function objective(): BelongsTo { return $this->belongsTo(Objective::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class)->orderBy('quarter')->orderBy('id'); }
    public function typeLabel(): string { return $this->type === 'initiative' ? 'مبادرة' : 'مشروع'; }
}
