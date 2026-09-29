<?php

use App\Models\PlanningYear;
use App\Services\AlertService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// توليد التنبيهات الدورية: التحديثات المستحقة، المهام المتأخرة، المؤشرات تحت المستهدف، الأدلة المنتظرة
Artisan::command('alerts:generate {year?}', function (?int $year = null) {
    $y = $year ? PlanningYear::where('year', $year)->firstOrFail() : null;
    $n = app(AlertService::class)->generate($y);
    $this->info("أُنشئ {$n} تنبيهًا جديدًا.");
})->purpose('توليد تنبيهات المتابعة الدورية');

// إنشاء سنة تخطيط بأرباعها الأربعة من سطر الأوامر
Artisan::command('year:create {year}', function (int $year) {
    $by = \App\Models\User::where('is_system_admin', true)->first();
    if (PlanningYear::where('year', $year)->exists()) {
        $this->error('السنة موجودة مسبقًا.');

        return 1;
    }
    app(\App\Services\YearService::class)->create($year, $by);
    $this->info("أُنشئت سنة {$year} بأرباعها الأربعة.");
})->purpose('إنشاء سنة تخطيط جديدة');

Schedule::command('alerts:generate')->dailyAt('07:00');
