<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    public const PRESIDENT = 'president';
    public const VICE_PRESIDENT = 'vice_president';
    public const SECRETARY = 'secretary';
    public const FINANCE = 'finance_admin';
    public const PLANNING = 'planning';
    public const MEDIA = 'pr_media';
    public const ENGINEERING = 'engineering';

    protected $fillable = ['code', 'name', 'global_view', 'is_planning', 'is_president', 'sort'];

    protected $casts = ['global_view' => 'boolean', 'is_planning' => 'boolean', 'is_president' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'position_user')->withPivot(['is_primary', 'starts_on', 'ends_on']);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
