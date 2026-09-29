<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionAssignment extends Model
{
    protected $table = 'position_user';

    protected $fillable = ['user_id', 'position_id', 'is_primary', 'starts_on', 'ends_on', 'assigned_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_primary' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }

    public function isCurrent(): bool
    {
        $t = now()->startOfDay();
        return (! $this->starts_on || $this->starts_on <= $t) && (! $this->ends_on || $this->ends_on >= $t);
    }
}
