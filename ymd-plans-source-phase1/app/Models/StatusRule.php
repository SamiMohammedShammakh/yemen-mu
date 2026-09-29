<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusRule extends Model
{
    protected $fillable = ['version', 'on_track_min', 'follow_up_min', 'is_active', 'created_by'];

    protected $casts = ['on_track_min' => 'float', 'follow_up_min' => 'float', 'is_active' => 'boolean'];

    public static function current(): self
    {
        return static::where('is_active', true)->latest('version')->first()
            ?? new static(['version' => 0, 'on_track_min' => 90, 'follow_up_min' => 70]);
    }

    public function toSnapshot(): array
    {
        return ['version' => (int) $this->version, 'on_track_min' => (float) $this->on_track_min, 'follow_up_min' => (float) $this->follow_up_min];
    }
}
