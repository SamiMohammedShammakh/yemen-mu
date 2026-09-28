<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** المناصب السبعة وقواعد الحالات (بلا بيانات تجريبية ولا كلمات مرور) */
    public function run(): void
    {
        $this->call(BaseSeeder::class);
    }
}
