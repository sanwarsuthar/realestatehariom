<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        // Admin gets SHOB00001 as both broker_id and referral_code
        $admin = DB::table('users')->insertGetId([
            'name' => 'DK',
            'email' => 'admin@shreehariom.com',
            'password' => Hash::make('admin123'),
            'user_type' => 'admin',
            'status' => 'active',
            'kyc_verified' => true,
            'broker_id' => 'SHOB00001',
            'referral_code' => 'SHOB00001',
            'profile_image_path' => '/Users/mac/Documents/1.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create default Bronze slab if not exists
        $bronzeSlab = DB::table('slabs')->where('name', 'Bronze')->first();
        if (!$bronzeSlab) {
            DB::table('slabs')->insert([
                'name' => 'Bronze',
                'minimum_target' => 0,
                'commission_ratio' => 2.00,
                'bonus_percentage' => 0,
                'description' => 'Entry level slab for new brokers',
                'color_code' => '#CD7F32',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
