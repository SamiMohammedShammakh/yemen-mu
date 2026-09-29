<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterSnapshot extends Model
{
    protected $fillable = ['plan_id', 'quarter', 'revision', 'plan_version_no', 'data', 'rules', 'change_request_id', 'created_by'];

    protected $casts = ['data' => 'array', 'rules' => 'array'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
