<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Export extends Model
{
    public const SCOPES = [
        'plan_full' => 'الخطة كاملة',
        'plan_quarter' => 'خطة ربع محدد',
        'results_annual' => 'نتائج الخطة السنوية',
        'results_quarter' => 'نتائج ربع محدد',
        'objective' => 'نتيجة هدف',
        'indicator' => 'نتيجة مؤشر',
        'project' => 'مشروع ومهامه',
        'year_package' => 'حزمة السنة كاملة',
        'org_summary' => 'تقرير شامل لأداء الجمعية',
    ];
    public const FORMATS = ['pdf' => 'PDF', 'docx' => 'DOCX', 'xlsx' => 'XLSX', 'csv' => 'CSV', 'zip' => 'ZIP'];

    protected $fillable = ['uuid', 'user_id', 'plan_id', 'planning_year_id', 'quarter', 'scope', 'subject_id', 'format',
        'file_path', 'file_name', 'size', 'plan_version_no', 'plan_status', 'data_as_of', 'rules', 'download_count'];

    protected $casts = ['data_as_of' => 'datetime', 'rules' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function year(): BelongsTo { return $this->belongsTo(PlanningYear::class, 'planning_year_id'); }
    public function downloads(): HasMany { return $this->hasMany(ExportDownload::class); }
    public function scopeLabel(): string { return self::SCOPES[$this->scope] ?? $this->scope; }
}
