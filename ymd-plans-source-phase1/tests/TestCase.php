<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Position;
use App\Models\User;
use App\Support\Access;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * أساس الاختبارات: قاعدة بيانات جديدة ببيانات تجريبية، وتاريخ ثابت (26-09-2026):
 * الربع الأول مغلق، الربع الثاني مستحق، الربع الثالث لم ينتهِ بعد.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 12:00:00');
        Storage::fake('local');
        // قاعدة SQLite في الذاكرة جديدة لكل اختبار
        Artisan::call('migrate', ['--force' => true]);
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
        Auth::logout();
        Access::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function user(string $code): User
    {
        if ($code === 'admin') {
            return User::where('is_system_admin', true)->first();
        }

        return User::where('email', DemoSeeder::USERS[$code][0])->firstOrFail();
    }

    protected function plan(string $code, int $year = 2026): Plan
    {
        return Plan::whereHas('year', fn ($q) => $q->where('year', $year))
            ->where('position_id', Position::where('code', $code)->value('id'))->firstOrFail();
    }

    protected function as(string $code): static
    {
        Access::flush();

        return $this->actingAs($this->user($code));
    }
}
