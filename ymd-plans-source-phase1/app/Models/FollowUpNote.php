<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUpNote extends Model
{
    protected $fillable = ['plan_id', 'parent_id', 'from_user_id', 'body'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('id'); }
}
