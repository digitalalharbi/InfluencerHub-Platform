<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** لا بيانات تجريبية. كتالوجات مرجعية فقط (أدوار/صلاحيات + تصنيفات مبدعين عامة). */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);
        // تصنيفات المبدعين العامة (غير مستأجرة) — كتالوج مرجعي لا بيانات وهمية
        \Illuminate\Support\Facades\Artisan::call('creators:seed-categories');
    }
}
