<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quarter extends Model
{
    protected $fillable = ['planning_year_id', 'number', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];

    public function year(): BelongsTo { return $this->belongsTo(PlanningYear::class, 'planning_year_id'); }

    public function isClosed(): bool { return $this->status === 'closed'; }

    public function label(): string { return 'الربع ' . ['', 'الأول', 'الثاني', 'الثالث', 'الرابع'][$this->number]; }
}
