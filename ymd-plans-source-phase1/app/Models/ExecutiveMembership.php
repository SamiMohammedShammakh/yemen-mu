<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveMembership extends Model
{
    protected $fillable = ['user_id', 'granted_by', 'granted_at', 'grant_reason', 'revoked_by', 'revoked_at', 'revoke_reason'];

    protected $casts = ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function granter(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }
    public function revoker(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by'); }

    public function scopeActive($q) { return $q->whereNull('revoked_at'); }
}
