<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Setting;

class ClearAllDataCommand extends Command
{
    protected $signature = 'data:clear-all 
                            {--confirm : Skip confirmation prompt}';
    
    protected $description = 'Clear all data except admin user and SMTP settings';

    public function handle()
    {
        $this->info('⚠️  WARNING: This will delete ALL data except admin user and SMTP settings!');
        $this->warn('   - All users (except admin) will be deleted');
        $this->warn('   - All sales, projects, plots will be deleted');
        $this->warn('   - All transactions, wallets will be cleared');
        $this->warn('   - All settings except SMTP will be deleted');
        $this->warn('   - Database tables will be preserved');
        $this->newLine();

        if (!$this->option('confirm')) {
            if (!$this->confirm('Are you absolutely sure you want to proceed?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            DB::beginTransaction();

            $this->info('🔄 Starting data cleanup...');
            $this->newLine();

            // Step 1: Preserve admin user(s) and SMTP settings
            $this->info('📋 Step 1: Preserving admin user and SMTP settings...');
            
            $adminUsers = User::where('user_type', 'admin')->get();
            $adminUserIds = $adminUsers->pluck('id')->toArray();
            
            if (empty($adminUserIds)) {
                $this->error('❌ No admin user found! Cannot proceed without admin user.');
                return 1;
            }
            
            $this->info("   Found " . count($adminUserIds) . " admin user(s) to preserve");
            
            // Preserve SMTP/mail settings
            $smtpSettings = [];
            $smtpKeys = [
                'mail_host', 'mail_port', 'mail_username', 'mail_password', 
                'mail_encryption', 'mail_from_address', 'mail_from_name', 
                'mail_mailer', 'smtp_host', 'smtp_port', 'smtp_username', 
                'smtp_password', 'smtp_encryption'
            ];
            
            foreach ($smtpKeys as $key) {
                $setting = Setting::where('key', $key)->first();
                if ($setting) {
                    $smtpSettings[$key] = $setting->value;
                }
            }
            
            $this->info("   Preserved " . count($smtpSettings) . " SMTP settings");
            $this->newLine();

            // Step 2: Delete referral commissions (must be first due to foreign keys)
            $this->info('🗑️  Step 2: Deleting referral commissions...');
            $deleted = DB::table('referral_commissions')->delete();
            $this->info("   Deleted {$deleted} referral commission records");

            // Step 3: Delete sales
            $this->info('🗑️  Step 3: Deleting sales...');
            $deleted = DB::table('sales')->delete();
            $this->info("   Deleted {$deleted} sales");

            // Step 4: Delete plots
            $this->info('🗑️  Step 4: Deleting plots...');
            $deleted = DB::table('plots')->delete();
            $this->info("   Deleted {$deleted} plots");

            // Step 5: Delete projects
            $this->info('🗑️  Step 5: Deleting projects...');
            $deleted = DB::table('projects')->delete();
            $this->info("   Deleted {$deleted} projects");

            // Step 6: Delete all transactions (including admin's since we're resetting everything)
            $this->info('🗑️  Step 6: Deleting all transactions...');
            $deleted = DB::table('transactions')->delete();
            $this->info("   Deleted {$deleted} transactions");

            // Step 7: Reset admin wallets to 0, delete other wallets
            $this->info('💰 Step 7: Resetting wallets...');
            DB::table('wallets')
                ->whereIn('user_id', $adminUserIds)
                ->update([
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'total_deposited' => 0,
                    'updated_at' => now(),
                ]);
            $deleted = DB::table('wallets')
                ->whereNotIn('user_id', $adminUserIds)
                ->delete();
            $this->info("   Reset admin wallets, deleted {$deleted} other wallets");

            // Step 8: Reset admin user stats
            $this->info('👤 Step 8: Resetting admin user stats...');
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->update([
                    'total_commission_earned' => 0,
                    'total_business_volume' => 0,
                    'total_downline_count' => 0,
                    'updated_at' => now(),
                ]);

            // Step 9: Delete referrals
            $this->info('🗑️  Step 9: Deleting referrals...');
            $deleted = DB::table('referrals')->delete();
            $this->info("   Deleted {$deleted} referral records");

            // Step 10: Delete payment requests
            $this->info('🗑️  Step 10: Deleting payment requests...');
            $deleted = DB::table('payment_requests')->delete();
            $this->info("   Deleted {$deleted} payment requests");

            // Step 11: Delete payment methods
            $this->info('🗑️  Step 11: Deleting payment methods...');
            $deleted = DB::table('payment_methods')->delete();
            $this->info("   Deleted {$deleted} payment methods");

            // Step 12: Delete KYC documents
            $this->info('🗑️  Step 12: Deleting KYC documents...');
            $deleted = DB::table('kyc_documents')->delete();
            $this->info("   Deleted {$deleted} KYC documents");

            // Step 13: Delete slab upgrades
            $this->info('🗑️  Step 13: Deleting slab upgrades...');
            $deleted = DB::table('slab_upgrades')->delete();
            $this->info("   Deleted {$deleted} slab upgrade records");

            // Step 14: Delete user slabs (except admin's)
            $this->info('🗑️  Step 14: Deleting user slabs...');
            $deleted = DB::table('user_slabs')
                ->whereNotIn('user_id', $adminUserIds)
                ->delete();
            $this->info("   Deleted {$deleted} user slab records");

            // Step 15: Delete contact inquiries
            $this->info('🗑️  Step 15: Deleting contact inquiries...');
            $deleted = DB::table('contact_inquiries')->delete();
            $this->info("   Deleted {$deleted} contact inquiries");

            // Step 16: Delete OTP records
            $this->info('🗑️  Step 16: Deleting OTP records...');
            if (DB::getSchemaBuilder()->hasTable('otps')) {
                $deleted = DB::table('otps')->delete();
                $this->info("   Deleted {$deleted} OTP records");
            }

            // Step 17: Delete sessions
            $this->info('🗑️  Step 17: Deleting sessions...');
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                $deleted = DB::table('sessions')->delete();
                $this->info("   Deleted {$deleted} session records");
            }

            // Step 18: Delete personal access tokens (except admin's)
            $this->info('🗑️  Step 18: Deleting access tokens...');
            if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
                $deleted = DB::table('personal_access_tokens')
                    ->whereNotIn('tokenable_id', $adminUserIds)
                    ->delete();
                $this->info("   Deleted {$deleted} access tokens");
            }

            // Step 19: Delete all users except admin
            $this->info('🗑️  Step 19: Deleting non-admin users...');
            $deleted = DB::table('users')
                ->whereNotIn('id', $adminUserIds)
                ->delete();
            $this->info("   Deleted {$deleted} non-admin users");

            // Step 20: Delete all settings except SMTP
            $this->info('🗑️  Step 20: Deleting settings (preserving SMTP)...');
            $deleted = DB::table('settings')->delete();
            $this->info("   Deleted {$deleted} settings");

            // Step 21: Restore SMTP settings
            $this->info('💾 Step 21: Restoring SMTP settings...');
            foreach ($smtpSettings as $key => $value) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->info("   Restored " . count($smtpSettings) . " SMTP settings");

            // Step 22: Clear cache
            $this->info('🗑️  Step 22: Clearing cache...');
            Cache::flush();
            if (DB::getSchemaBuilder()->hasTable('cache')) {
                DB::table('cache')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('cache_locks')) {
                DB::table('cache_locks')->delete();
            }
            $this->info("   Cache cleared");

            // Step 23: Clear job queue
            $this->info('🗑️  Step 23: Clearing job queue...');
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                DB::table('jobs')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                DB::table('failed_jobs')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('job_batches')) {
                DB::table('job_batches')->delete();
            }
            $this->info("   Job queue cleared");

            // Step 24: Reset auto-increment counters (SQLite)
            if (DB::getDriverName() === 'sqlite') {
                $this->info('🔄 Step 24: Resetting auto-increment counters...');
                $tables = [
                    'users', 'sales', 'transactions', 'wallets', 'payment_requests', 
                    'payment_methods', 'referrals', 'kyc_documents', 'personal_access_tokens',
                    'referral_commissions', 'slab_upgrades', 'user_slabs', 'contact_inquiries',
                    'projects', 'plots'
                ];
                $placeholders = implode(',', array_fill(0, count($tables), '?'));
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ($placeholders)", $tables);
                $this->info("   Auto-increment counters reset");
            }

            DB::commit();

            $this->newLine();
            $this->info('✅ Data cleanup completed successfully!');
            $this->info("   Preserved: " . count($adminUserIds) . " admin user(s), " . count($smtpSettings) . " SMTP settings");
            $this->info("   All other data has been deleted");
            $this->newLine();
            $this->info('📝 Next steps:');
            $this->line('   1. Run migrations if needed: php artisan migrate');
            $this->line('   2. Seed initial data: php artisan db:seed');
            $this->line('   3. Create new users and projects');

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
