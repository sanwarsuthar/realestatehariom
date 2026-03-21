<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePhoneNumbersCommand extends Command
{
    protected $signature = 'users:normalize-phones';
    protected $description = 'Normalize all phone numbers in database (remove spaces, dashes, etc.)';

    public function handle()
    {
        $this->info('🔄 Normalizing phone numbers...');

        $users = DB::table('users')->whereNull('deleted_at')->get();
        $updated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $originalPhone = $user->phone_number;
            $normalizedPhone = preg_replace('/[^0-9]/', '', trim($originalPhone));

            if ($originalPhone !== $normalizedPhone) {
                // Check if normalized phone already exists for another user
                $existing = DB::table('users')
                    ->where('phone_number', $normalizedPhone)
                    ->where('id', '!=', $user->id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $this->warn("⚠️  Skipping user ID {$user->id} ({$user->name}): Normalized phone {$normalizedPhone} already exists for user {$existing->id}");
                    $skipped++;
                    continue;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'phone_number' => $normalizedPhone,
                        'updated_at' => now()
                    ]);

                $this->info("✅ Updated user ID {$user->id} ({$user->name}): {$originalPhone} → {$normalizedPhone}");
                $updated++;
            }
        }

        $this->info("");
        $this->info("✅ Normalization complete!");
        $this->info("   Updated: {$updated} users");
        $this->info("   Skipped: {$skipped} users (duplicates)");

        return 0;
    }
}

