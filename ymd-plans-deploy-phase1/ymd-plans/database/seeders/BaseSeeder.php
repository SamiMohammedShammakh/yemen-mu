<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\StatusRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class BaseSeeder extends Seeder
{
    public const POSITIONS = [
        ['code' => Position::PRESIDENT, 'ref_code' => 'PRS', 'name' => 'الرئيس', 'global_view' => true, 'is_president' => true],
        ['code' => Position::VICE_PRESIDENT, 'ref_code' => 'VPR', 'name' => 'نائب الرئيس'],
        ['code' => Position::SECRETARY, 'ref_code' => 'SEC', 'name' => 'أمين السر'],
        ['code' => Position::FINANCE, 'ref_code' => 'FIN', 'name' => 'مسؤول الشؤون المالية والإدارية'],
        ['code' => Position::PLANNING, 'ref_code' => 'PLN', 'name' => 'مسؤول التخطيط والمتابعة', 'global_view' => true, 'is_planning' => true],
        ['code' => Position::MEDIA, 'ref_code' => 'MED', 'name' => 'مسؤول العلاقات العامة والإعلام'],
        ['code' => Position::ENGINEERING, 'ref_code' => 'ENG', 'name' => 'مسؤول الشؤون الهندسية'],
    ];

    public function run(): void
    {
        foreach (self::POSITIONS as $i => $p) {
            Position::updateOrCreate(['code' => $p['code']], $p + ['sort' => $i + 1, 'global_view' => false, 'is_planning' => false, 'is_president' => false]);
        }
        StatusRule::firstOrCreate(['version' => 1], ['on_track_min' => 90, 'follow_up_min' => 70, 'is_active' => true]);

        // مدير النظام التقني: يدير الحسابات فقط ولا يرى الخطط.
        // لا توجد كلمة مرور افتراضية: يُنشأ فقط إن ضُبط ADMIN_EMAIL و ADMIN_PASSWORD،
        // وإلا يُنشأ بالأمر التفاعلي: php artisan admin:create
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password) {
            User::firstOrCreate(['email' => $email], [
                'name' => 'مدير النظام التقني',
                'password' => $password,
                'is_system_admin' => true,
                'is_active' => true,
            ]);
            $this->command?->info("أُنشئ حساب مدير النظام: {$email}. احذف ADMIN_PASSWORD من ملف .env الآن.");
        } else {
            $this->command?->warn('لم يُنشأ حساب مدير النظام. شغّل: php artisan admin:create');
        }
    }
}
