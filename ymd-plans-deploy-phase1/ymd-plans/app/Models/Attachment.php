<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = ['plan_id', 'progress_update_id', 'task_id', 'original_name', 'path', 'mime', 'size', 'sha256', 'uploaded_by'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function progressUpdate(): BelongsTo { return $this->belongsTo(ProgressUpdate::class, 'progress_update_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function humanSize(): string
    {
        $s = $this->size;
        return $s > 1048576 ? round($s / 1048576, 1) . ' م.ب' : max(1, round($s / 1024)) . ' ك.ب';
    }
}
