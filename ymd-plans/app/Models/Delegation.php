<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    protected $fillable = ['user_id', 'permission', 'document_ref', 'starts_on', 'ends_on', 'granted_by', 'revoked_by', 'revoked_at'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function granter(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }

    public function scopeActive($q)
    {
        $t = now()->toDateString();
        return $q->whereNull('revoked_at')->where('starts_on', '<=', $t)
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $t));
    }
}
