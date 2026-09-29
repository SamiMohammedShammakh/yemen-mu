<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'is_active', 'is_system_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_system_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** كل الإسنادات (الحالية والسابقة) */
    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    /** المناصب السارية اليوم */
    public function positions(): BelongsToMany
    {
        $today = now()->toDateString();

        return $this->belongsToMany(Position::class, 'position_user')
            ->withPivot(['is_primary', 'starts_on', 'ends_on'])
            ->where(fn ($q) => $q->whereNull('position_user.starts_on')->orWhere('position_user.starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('position_user.ends_on')->orWhere('position_user.ends_on', '>=', $today))
            ->orderBy('positions.sort');
    }

    public function executiveMemberships(): HasMany
    {
        return $this->hasMany(ExecutiveMembership::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
