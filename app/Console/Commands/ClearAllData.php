<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ClearAllData extends Command
{
    protected $signature = 'data:clear';
    protected $description = 'Clear all user data while preserving admin users, slabs, and settings';

    public function handle()
    {
        if (!$this->confirm('This will delete ALL user data, projects, and plots. Only admin users, slabs, and settings will be preserved. Continue?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Starting data clearing process...');
        $this->newLine();

        DB::beginTransaction();

        try {
            // Get admin user IDs
            $adminIds = DB::table('users')->where('user_type', 'admin')->pluck('id')->toArray();
            
            if (empty($adminIds)) {
                throw new \Exception('No admin users found! Cannot proceed.');
            }
            
            $this->info('Admin IDs found: ' . implode(', ', $adminIds));
            $this->newLine();

            // Delete all transactions
            $count = DB::table('transactions')->count();
            DB::table('transactions')->delete();
            $this->info("✓ Deleted {$count} transactions");

            // Delete all sales
            $count = DB::table('sales')->count();
            DB::table('sales')->delete();
            $this->info("✓ Deleted {$count} sales");

            // Delete all payment requests
            $count = DB::table('payment_requests')->count();
            DB::table('payment_requests')->delete();
            $this->info("✓ Deleted {$count} payment requests");

            // Delete slab upgrades
            if (DB::getSchemaBuilder()->hasTable('slab_upgrades')) {
                $count = DB::table('slab_upgrades')->count();
                DB::table('slab_upgrades')->delete();
                $this->info("✓ Deleted {$count} slab upgrades");
            }

            // Delete KYC documents
            $count = DB::table('kyc_documents')->count();
            DB::table('kyc_documents')->delete();
            $this->info("✓ Deleted {$count} KYC documents");

            // Reset wallet balances
            DB::table('wallets')->update([
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_deposited' => 0,
            ]);
            $this->info("✓ Reset all wallet balances to 0");

            // Delete user wallets (keep admin wallets)
            $count = DB::table('wallets')->whereNotIn('user_id', $adminIds)->count();
            DB::table('wallets')->whereNotIn('user_id', $adminIds)->delete();
            $this->info("✓ Deleted {$count} user wallets");

            // Delete OTP verifications
            if (DB::getSchemaBuilder()->hasTable('otp_verifications')) {
                $count = DB::table('otp_verifications')->count();
                DB::table('otp_verifications')->delete();
                $this->info("✓ Deleted {$count} OTP verifications");
            }

            // Delete personal access tokens (except admin)
            $count = DB::table('personal_access_tokens')->whereNotIn('tokenable_id', $adminIds)->count();
            DB::table('personal_access_tokens')->whereNotIn('tokenable_id', $adminIds)->delete();
            $this->info("✓ Deleted {$count} personal access tokens");

            // Delete sessions
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                $count = DB::table('sessions')->count();
                DB::table('sessions')->delete();
                $this->info("✓ Deleted {$count} sessions");
            }

            // Delete payment methods
            $count = DB::table('payment_methods')->count();
            DB::table('payment_methods')->delete();
            $this->info("✓ Deleted {$count} payment methods");

            // Delete plots first (due to foreign key constraints)
            $count = DB::table('plots')->count();
            DB::table('plots')->delete();
            $this->info("✓ Deleted {$count} plots");

            // Delete projects
            $count = DB::table('projects')->count();
            DB::table('projects')->delete();
            $this->info("✓ Deleted {$count} projects");

            // Delete all users except admin
            $count = DB::table('users')->where('user_type', '!=', 'admin')->count();
            DB::table('users')->where('user_type', '!=', 'admin')->delete();
            $this->info("✓ Deleted {$count} non-admin users");

            // Clear cache
            Cache::flush();
            $this->info("✓ Cleared cache");

            DB::commit();

            $this->newLine();
            $this->info('✅ SUCCESS! All data cleared successfully!');
            $this->newLine();
            $this->info('Preserved:');
            $this->line('  - Admin users');
            $this->line('  - Slabs');
            $this->line('  - Settings');
            $this->line('  - Property Types');
            $this->line('  - Measurement Units');
            $this->newLine();
            $this->info('Ready to import data from backup!');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ ERROR: ' . $e->getMessage());
            return 1;
        }
    }
}
