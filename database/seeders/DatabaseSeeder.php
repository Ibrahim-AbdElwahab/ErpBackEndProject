<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. إنشاء حساب مدير النظام (الأدمن)
        User::create([
            'name' => 'مدير النظام',
            'email' => 'admin@erp.com',
            'phone' => '01000000000',
            'role' => 'admin',
            'password' => Hash::make('123456'), // الباسوورد متشفر
        ]);

        // 2. إنشاء تصنيفات قطع الغيار الأساسية
        $categories = [
            'شاشات',
            'بطاريات',
            'بورد',
            'فلاتات',
            'سوكت شحن',
            'إكسسوارات'
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category
            ]);
        }
    }
}
