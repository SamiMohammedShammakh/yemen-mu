<?php

namespace App\Providers;

use App\Services\AlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // خلف وكيل HTTPS في الاستضافة المشتركة: اجعل الروابط المولّدة https
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // بديل المهمة المجدولة إن لم تتوفر cron: توليد التنبيهات مرة يوميًا بعد إرسال أول استجابة في اليوم.
        // يُفعّل بـ ALERTS_RUN_ON_REQUEST=true. مع وجود cron اتركه false واعتمد schedule:run.
        if (config('app.alerts_on_request') && ! $this->app->runningInConsole()) {
            $this->app->terminating(function () {
                try {
                    if (Cache::add('alerts:daily:' . now()->toDateString(), true, now()->addDay())) {
                        app(AlertService::class)->generate();
                    }
                } catch (\Throwable $e) {
                    Log::warning('alerts on request failed: ' . $e->getMessage());
                }
            });
        }
    }
}
