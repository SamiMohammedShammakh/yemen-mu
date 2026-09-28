<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVersion extends Model
{
    protected $fillable = ['plan_id', 'version_no', 'snapshot', 'reason', 'change_request_id', 'approved_by', 'delegation_id', 'effective_from', 'effective_to'];

    protected $casts = ['snapshot' => 'array', 'effective_from' => 'datetime', 'effective_to' => 'datetime'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
