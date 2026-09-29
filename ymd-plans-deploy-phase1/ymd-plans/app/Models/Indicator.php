<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    public const KINDS = ['cumulative' => 'تراكمي', 'periodic' => 'دوري', 'point' => 'قيمة في نقطة زمنية'];
    public const DIRECTIONS = ['higher' => 'الأعلى أفضل', 'lower' => 'الأقل أفضل', 'range' => 'قيمة ضمن نطاق'];
    public const FREQUENCIES = ['monthly' => 'شهري', 'quarterly' => 'ربعي', 'semiannual' => 'نصف سنوي', 'annual' => 'سنوي'];
    public const AGGREGATIONS = [
        'sum' => 'مجموع الأرباع',
        'average' => 'متوسط الأرباع المقاسة',
        'last' => 'آخر قيمة مقاسة',
        'weighted_average' => 'متوسط مرجح بعدد المشاركين',
    ];

    protected $fillable = ['plan_id', 'objective_id', 'name', 'definition', 'unit', 'direction', 'range_min', 'range_max',
        'kind', 'aggregation', 'baseline', 'annual_target', 'data_source', 'verification_method', 'frequency',
        'required_evidence', 'owner_user_id', 'weight', 'sort'];

    protected $casts = ['range_min' => 'float', 'range_max' => 'float', 'baseline' => 'float', 'annual_target' => 'float', 'weight' => 'float'];

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function objective(): BelongsTo { return $this->belongsTo(Objective::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function targets(): HasMany { return $this->hasMany(IndicatorTarget::class)->orderBy('quarter'); }
    public function updates(): HasMany { return $this->hasMany(ProgressUpdate::class)->orderByDesc('id'); }

    /** المستهدف الربعي (قيمة الفترة) */
    public function targetFor(int $q): ?float
    {
        $t = $this->targets->firstWhere('quarter', $q);
        return $t && $t->target !== null ? (float) $t->target : null;
    }

    /** هل يستحق القياس في نهاية هذا الربع وفق دورية التحديث؟ */
    public function measuredInQuarter(int $q): bool
    {
        return match ($this->frequency) {
            'annual' => $q === 4,
            'semiannual' => in_array($q, [2, 4], true),
            default => true,
        };
    }

    public function kindLabel(): string { return self::KINDS[$this->kind] ?? $this->kind; }
    public function directionLabel(): string { return self::DIRECTIONS[$this->direction] ?? $this->direction; }
}
