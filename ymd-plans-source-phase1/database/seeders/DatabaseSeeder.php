<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** البيانات الأساسية فقط. للبيانات التجريبية: php artisan db:seed --class=DemoSeeder */
    public function run(): void
    {
        $this->call(BaseSeeder::class);
    }
}
