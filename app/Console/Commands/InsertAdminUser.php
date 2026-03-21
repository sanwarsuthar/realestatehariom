<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InsertAdminUser extends Command
{
    protected $signature = 'admin:insert {--email=admin@shrihariomgroup.in} {--password=Admin@admin123}';
    protected $description = 'Insert or update admin user';

    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');

        try {
            // Check if users table exists
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            
            if (empty($tables)) {
                $this->error("❌ Users table does not exist. Please import your database backup first.");
                $this->info("   Run: php artisan db:import-backup --backup-path=/path/to/backup.sqlite");
                return 1;
            }

            // Check if admin already exists
            $existingAdmin = DB::table('users')
                ->where('email', $email)
                ->first();

            if ($existingAdmin) {
                $this->warn("⚠️  Admin user already exists. Updating password...");
                DB::table('users')
                    ->where('id', $existingAdmin->id)
                    ->update([
                        'password' => Hash::make($password),
                        'user_type' => 'admin',
                        'status' => 'active',
                        'kyc_verified' => true,
                        'updated_at' => now(),
                    ]);
                $this->info("✅ Admin password updated successfully!");
            } else {
                // Insert admin user
                $adminId = DB::table('users')->insertGetId([
                    'name' => 'Admin',
                    'email' => $email,
                    'password' => Hash::make($password),
                    'user_type' => 'admin',
                    'status' => 'active',
                    'kyc_verified' => true,
                    'broker_id' => 'SHOB00001',
                    'referral_code' => 'SHOB00001',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->info("✅ Admin user created successfully!");
                $this->info("   ID: $adminId");
            }

            $this->info("   Email: $email");
            $this->info("   Password: $password");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
