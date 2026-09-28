<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanReview extends Model
{
    public const ACTIONS = [
        'submit' => 'إرسال لمراجعة التخطيط',
        'return' => 'إعادة للتعديل',
        'recommend' => 'توصية بالاعتماد',
        'approve' => 'اعتماد',
        'activate' => 'تفعيل',
        'close' => 'إغلاق',
        'note' => 'ملاحظة مراجعة',
        'amend' => 'اعتماد طلب تعديل',
    ];

    protected $fillable = ['plan_id', 'action', 'from_status', 'to_status', 'note', 'version_no', 'user_id', 'delegation_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function delegation(): BelongsTo { return $this->belongsTo(Delegation::class); }
    public function actionLabel(): string { return self::ACTIONS[$this->action] ?? $this->action; }
}
