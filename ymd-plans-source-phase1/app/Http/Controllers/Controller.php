<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Access;
use App\Support\Workspace;

abstract class Controller
{
    protected function ctx(): Workspace
    {
        return app(Workspace::class);
    }

    /** فحص الصلاحية على الخادم في كل طلب يخص خطة */
    protected function canView(Plan $plan): void
    {
        abort_unless(Access::canViewPlan(auth()->user(), $plan), 403, 'لا تملك صلاحية الوصول إلى هذه الخطة.');
    }

    protected function canEdit(Plan $plan): void
    {
        $this->canView($plan);
        abort_unless(Access::isPlanOwner(auth()->user(), $plan), 403, 'تعديل الخطة متاح لصاحب المنصب فقط.');
        abort_unless($plan->isEditable(), 403, 'الخطة ليست في حالة تسمح بالتعديل المباشر. بعد الاعتماد استخدم «طلب تعديل الخطة».');
    }

    protected function selectedQuarter(): int
    {
        $q = (int) request('quarter', $this->ctx()->quarter);

        return max(1, min(4, $q));
    }
}
