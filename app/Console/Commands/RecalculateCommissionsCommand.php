<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Sale;
use App\Models\User;
use App\Models\Project;
use App\Models\SaleCommissionRelease;
use App\Services\CommissionDistributionService;

class RecalculateCommissionsCommand extends Command
{
    protected $signature = 'commissions:recalculate 
                            {--dry-run : Show what would be recalculated without making changes}
                            {--sale-id= : Recalculate specific sale ID only}
                            {--user-id= : Recalculate all sales for specific user ID}
                            {--force : Force recalculation even if already calculated}';
    
    protected $description = 'Recalculate and redistribute commissions for all confirmed sales';

    protected $commissionService;

    public function __construct()
    {
        parent::__construct();
        $this->commissionService = new CommissionDistributionService();
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $saleId = $this->option('sale-id');
        $userId = $this->option('user-id');
        $force = $this->option('force');

        $this->info('🔄 Commission Recalculation Process');
        $this->info('=====================================');
        
        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
        }

        try {
            // Get sales to process
            $salesQuery = Sale::where('status', 'confirmed')
                ->with(['plot', 'project', 'soldByUser']);

            if ($saleId) {
                $salesQuery->where('id', $saleId);
                $this->info("📋 Processing Sale ID: {$saleId}");
            } elseif ($userId) {
                $salesQuery->where('sold_by_user_id', $userId);
                $this->info("📋 Processing Sales for User ID: {$userId}");
            } else {
                $this->info("📋 Processing ALL confirmed sales");
            }

            $sales = $salesQuery->get();
            $totalSales = $sales->count();

            if ($totalSales === 0) {
                $this->warn('⚠️  No sales found to process');
                return 0;
            }

            $this->info("Found {$totalSales} sale(s) to process");
            $this->newLine();

            $bar = $this->output->createProgressBar($totalSales);
            $bar->start();

            $processed = 0;
            $errors = 0;
            $skipped = 0;

            // Process each sale in its own transaction to avoid long-running transactions
            foreach ($sales as $sale) {
                try {
                    // Skip if already calculated and not forcing
                    // Skip if already calculated and not forcing recalculation
                    if (!$force && $sale->commission_distribution && !empty($sale->commission_distribution)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                    
                    // If force flag is set, we'll revert the distribution first (handled below)

                    if (!$sale->plot || !$sale->project || !$sale->soldByUser) {
                        $this->newLine();
                        $this->warn("⚠️  Sale #{$sale->id} missing required relationships, skipping...");
                        $errors++;
                        $bar->advance();
                        continue;
                    }

                    if ($dryRun) {
                        // Just preview what would be calculated
                        $preview = $this->commissionService->previewCommissionDistribution(
                            $sale->soldByUser,
                            $sale->project,
                            (float)($sale->plot->size ?? 0),
                            $sale->plot->type ?? 'plot'
                        );
                        
                        $this->newLine();
                        $this->info("Sale #{$sale->id} - Preview:");
                        $this->line("  Total Commission: ₹" . number_format(array_sum(array_column($preview, 'commission_amount')), 2));
                        $bar->advance();
                        continue;
                    }

                    // Process each sale in its own transaction
                    DB::beginTransaction();
                    try {
                        // Revert previous commission distribution
                        // $this->revertCommissionDistribution($sale);

                        // Recalculate commission distribution
                        // $commissionDistribution = $this->commissionService->distributeCommission(
                        //     $sale,
                        //     $sale->project,
                        //     $sale->soldByUser
                        // );

                        if (empty($commissionDistribution)) {
                            $this->newLine();
                            $this->warn("⚠️  Sale #{$sale->id} - No commission distribution calculated");
                            $errors++;
                            DB::rollBack();
                        } else {
                            $processed++;
                            DB::commit();
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e; // Re-throw to be caught by outer catch
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ Error processing Sale #{$sale->id}: " . $e->getMessage());
                    Log::error("RecalculateCommissionsCommand - Sale #{$sale->id} error: " . $e->getMessage());
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if (!$dryRun) {
                // Recalculate downline counts (separate transaction)
                $this->info('📊 Recalculating downline counts...');
                DB::beginTransaction();
                try {
                    // $this->recalculateDownlineCounts();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error recalculating downline counts: " . $e->getMessage());
                }

                // Update user slabs based on current sales (separate transaction)
                $this->info('📈 Updating user slabs...');
                DB::beginTransaction();
                try {
                    $this->updateUserSlabs();
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error updating user slabs: " . $e->getMessage());
                }
            }

            if ($dryRun) {
                $this->info("✅ Dry run completed. {$totalSales} sale(s) would be processed.");
            } else {
                $this->info("✅ Recalculation completed!");
                $this->info("   Processed: {$processed}");
                $this->info("   Skipped: {$skipped}");
                if ($errors > 0) {
                    $this->warn("   Errors: {$errors}");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Fatal error: " . $e->getMessage());
            Log::error("RecalculateCommissionsCommand fatal error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Revert previous commission distribution for a sale
     */
    // private function revertCommissionDistribution(Sale $sale): void
    // {
    //     $distribution = $sale->commission_distribution ?? [];
        
    //     if (empty($distribution)) {
    //         return; // Nothing to revert
    //     }

    //     $releases = SaleCommissionRelease::where('sale_id', $sale->id)->get()->keyBy('user_id');

    //     // Revert wallet balances and transactions (balance = gross by released_amount, main_balance by total_commission)
    //     foreach ($distribution as $level => $commission) {
    //         if (is_string($level) && strpos($level, '_') === 0) {
    //             continue;
    //         }
    //         if (!is_array($commission) || !isset($commission['user_id'], $commission['commission_amount'])) {
    //             continue;
    //         }

    //         $userId = $commission['user_id'];
    //         $totalCommission = (float)($commission['commission_amount'] ?? 0);
    //         if ($totalCommission <= 0) {
    //             continue;
    //         }

    //         $release = $releases->get($userId);
    //         $releasedAmount = $release ? (float)$release->released_amount : $totalCommission;

    //         $wallet = DB::table('wallets')->where('user_id', $userId)->first();
    //         if ($wallet) {
    //             $newBalance = max(0, (float)($wallet->balance ?? 0) - $releasedAmount);
    //             $newMain = (float)($wallet->main_balance ?? 0) - $totalCommission;
    //             $newTotalEarned = max(0, (float)($wallet->total_earned ?? 0) - $totalCommission);
    //             DB::table('wallets')->where('user_id', $userId)->update([
    //                 'balance' => $newBalance,
    //                 'main_balance' => $newMain,
    //                 'total_earned' => $newTotalEarned,
    //             ]);
    //         }
    //         $userRow = DB::table('users')->where('id', $userId)->first();
    //         if ($userRow) {
    //             $newUserEarned = max(0, (float)($userRow->total_commission_earned ?? 0) - $totalCommission);
    //             DB::table('users')->where('id', $userId)->update(['total_commission_earned' => $newUserEarned]);
    //         }

    //         DB::table('transactions')
    //             ->where('reference_id', $sale->id)
    //             ->where('type', 'commission')
    //             ->where('user_id', $userId)
    //             ->delete();
    //     }

    //     // Revert remaining pool if it was sent to admin
    //     if (isset($distribution['_remaining_pool'])) {
    //         $remainingPool = $distribution['_remaining_pool'];
    //         $adminUserId = $remainingPool['user_id'] ?? null;
    //         $remainingPoolAmount = (float)($remainingPool['amount'] ?? 0);

    //         if ($adminUserId && $remainingPoolAmount > 0) {
    //             // Revert admin wallet balance
    //             DB::table('wallets')
    //                 ->where('user_id', $adminUserId)
    //                 ->decrement('balance', $remainingPoolAmount);

    //             DB::table('wallets')
    //                 ->where('user_id', $adminUserId)
    //                 ->decrement('total_earned', $remainingPoolAmount);

    //             // Delete admin transaction for remaining pool
    //             DB::table('transactions')
    //                 ->where('reference_id', $sale->id)
    //                 ->where('type', 'commission')
    //                 ->where('user_id', $adminUserId)
    //                 ->where('metadata', 'like', '%remaining_pool%')
    //                 ->delete();
    //         }
    //     }

    //     // Delete referral commission records and sale commission releases (so redistribute creates fresh rows)
    //     DB::table('referral_commissions')->where('sale_id', $sale->id)->delete();
    //     SaleCommissionRelease::where('sale_id', $sale->id)->delete();

    //     // Clear commission distribution from sale
    //     $sale->update([
    //         'commission_amount' => 0,
    //         'commission_distribution' => null,
    //     ]);
    // }

    /**
     * Recalculate downline counts for all users
     */
    private function recalculateDownlineCounts(): void
    {
        $users = User::where('user_type', 'broker')->get();
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $downlineCount = User::where('referred_by_user_id', $user->id)
                ->where('user_type', 'broker')
                ->count();

            $user->update(['total_downline_count' => $downlineCount]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Update user slabs based on current sales volume
     */
    private function updateUserSlabs(): void
    {
        $users = User::where('user_type', 'broker')->get();
        $propertyTypes = \App\Models\PropertyType::where('is_active', true)->get();
        
        $bar = $this->output->createProgressBar($users->count() * $propertyTypes->count());
        $bar->start();

        foreach ($users as $user) {
            foreach ($propertyTypes as $propertyType) {
                try {
                    $slab = $this->commissionService->calculateCurrentSlabForPropertyType($user, $propertyType, 0);
                    
                    if ($slab) {
                        // Get current slab for comparison
                        $currentUserSlab = DB::table('user_slabs')
                            ->where('user_id', $user->id)
                            ->where('property_type_id', $propertyType->id)
                            ->first();
                        
                        // Update or create user_slabs entry
                        if ($currentUserSlab) {
                            if ($currentUserSlab->slab_id != $slab->id) {
                                DB::table('user_slabs')
                                    ->where('user_id', $user->id)
                                    ->where('property_type_id', $propertyType->id)
                                    ->update([
                                        'slab_id' => $slab->id,
                                        'updated_at' => now(),
                                    ]);
                            }
                        } else {
                            DB::table('user_slabs')->insert([
                                'user_id' => $user->id,
                                'property_type_id' => $propertyType->id,
                                'slab_id' => $slab->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error updating slab for user {$user->id}, property type {$propertyType->id}: " . $e->getMessage());
                }
                
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }
}
