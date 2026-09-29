<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const STATUSES = [
        'draft' => 'مسودة',
        'submitted' => 'مرسلة لمراجعة التخطيط',
        'returned' => 'معادة للتعديل',
        'recommended' => 'موصى باعتمادها',
        'approved' => 'معتمدة من الرئيس',
        'active' => 'نشطة',
        'closed' => 'مغلقة',
    ];

    protected $fillable = ['ref', 'planning_year_id', 'position_id', 'owner_user_id', 'scope_description', 'overall_outcome',
        'risks', 'resources', 'status', 'current_version', 'copied_from_plan_id'];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            $p->ref ??= \App\Support\Refs::plan($p);
        });
    }

    public function year(): BelongsTo { return $this->belongsTo(PlanningYear::class, 'planning_year_id'); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function objectives(): HasMany { return $this->hasMany(Objective::class)->orderBy('sort')->orderBy('id'); }
    public function indicators(): HasMany { return $this->hasMany(Indicator::class)->orderBy('sort')->orderBy('id'); }
    public function projects(): HasMany { return $this->hasMany(Project::class)->orderBy('id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class)->orderBy('quarter')->orderBy('id'); }
    public function versions(): HasMany { return $this->hasMany(PlanVersion::class)->orderByDesc('version_no'); }
    public function reviews(): HasMany { return $this->hasMany(PlanReview::class)->orderByDesc('id'); }
    public function updates(): HasMany { return $this->hasMany(ProgressUpdate::class)->orderByDesc('id'); }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class)->orderByDesc('id'); }
    public function changeRequests(): HasMany { return $this->hasMany(ChangeRequest::class)->orderByDesc('id'); }
    public function correctiveActions(): HasMany { return $this->hasMany(CorrectiveAction::class)->orderByDesc('id'); }
    public function notes(): HasMany { return $this->hasMany(FollowUpNote::class)->whereNull('parent_id')->orderByDesc('id'); }
    public function snapshots(): HasMany { return $this->hasMany(QuarterSnapshot::class); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function isEditable(): bool { return in_array($this->status, ['draft', 'returned'], true); }

    /** الخطة المعتمدة (معتمدة/نشطة/مغلقة) لا تُعدّل إلا بطلب تعديل */
    public function isLocked(): bool { return in_array($this->status, ['approved', 'active', 'closed'], true); }

    public function acceptsUpdates(): bool { return in_array($this->status, ['approved', 'active'], true); }

    public function isOfficial(): bool { return $this->isLocked(); }

    public function versionLabel(): string
    {
        return $this->current_version > 0 ? 'v' . $this->current_version : 'مسودة (لم تُعتمد)';
    }

    public function title(): string
    {
        return 'خطة ' . $this->position->name . ' ' . $this->year->year;
    }
}
