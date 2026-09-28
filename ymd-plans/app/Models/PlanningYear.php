<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningYear extends Model
{
    protected $fillable = ['year', 'status', 'created_by'];

    public function quarters(): HasMany { return $this->hasMany(Quarter::class)->orderBy('number'); }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }

    public function quarter(int $n): ?Quarter
    {
        return $this->quarters->firstWhere('number', $n);
    }
}
