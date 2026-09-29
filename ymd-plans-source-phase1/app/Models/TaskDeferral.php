<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDeferral extends Model
{
    protected $fillable = ['task_id', 'from_quarter', 'to_quarter', 'reason', 'owner_user_id', 'new_due_on', 'created_by'];

    protected $casts = ['new_due_on' => 'date'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
