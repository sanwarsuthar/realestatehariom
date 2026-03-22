<?php

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\PropertyType;
use App\Models\Slab;
use App\Models\SlabUpgrade;
use App\Models\ReferralCommission;
use App\Models\PaymentRequest;
use App\Models\SaleCommissionRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionDistributionService
{
    /**
     * Preview commission distribution without actually distributing
     * Used for displaying commission breakdown before approval
     * 
     * @param User $bookingUser The user who made the booking
     * @param Project $project The project being booked
     * @param float $plotSize Area sold
     * @param string $plotType Plot type
     * @return array Commission distribution preview
     */
    public function previewCommissionDistribution(User $bookingUser, Project $project, float $plotSize, string $plotType): array
    {
        $commissionDistribution = [];
        
        if ($plotSize <= 0) {
            return $commissionDistribution;
        }
        
        // Get property type from plot type
        $propertyTypeSlug = strtolower(trim($plotType ?? 'plot'));
        
        // Get PropertyType model
        $propertyTypeModel = PropertyType::where('is_active', true)
            ->with('measurementUnit')
            ->get()
            ->first(function ($pt) use ($propertyTypeSlug) {
                $nameLower = strtolower($pt->name);
                $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                return $nameLower === $propertyTypeSlug || 
                       $slugFromName === $propertyTypeSlug ||
                       str_replace(' ', '-', $nameLower) === $propertyTypeSlug ||
                       str_replace(' ', '_', $nameLower) === $propertyTypeSlug;
            });
        
        if (!$propertyTypeModel) {
            return $commissionDistribution;
        }
        
        // Get user's current slab for this property type
        $userSlab = $this->calculateCurrentSlabForPropertyType($bookingUser, $propertyTypeModel, $plotSize);
        
        if (!$userSlab) {
            return $commissionDistribution;
        }
        
        $primarySlabName = $userSlab->name;
        
        // Get allocated amount from project config
        $allocatedAmountConfig = $project->allocated_amount_config ?? [];
        $propertyTypeConfig = $allocatedAmountConfig[$propertyTypeModel->name] ?? null;
        
        $allocatedAmount = 0;
        $isFixedAllocatedAmount = true; // Track if allocated amount is fixed (not per unit)
        
        if ($propertyTypeConfig) {
            $configType = $propertyTypeConfig['type'] ?? 'fixed';
            $configValue = (float)($propertyTypeConfig['value'] ?? 0);
            
            if ($configType === 'fixed') {
                $allocatedAmount = $configValue;
                $isFixedAllocatedAmount = true; // Fixed amount per sale
            } elseif ($configType === 'percentage') {
                $plot = \App\Models\Plot::where('project_id', $project->id)->first();
                $propertyRatePerUnit = $plot ? (float)($plot->price_per_unit ?? 0) : 0;
                if ($propertyRatePerUnit <= 0) {
                    $propertyRatePerUnit = (float)($project->price_per_sqft ?? 0);
                }
                
                if ($propertyRatePerUnit > 0 && $configValue > 0) {
                    $allocatedAmount = ($propertyRatePerUnit * $configValue / 100);
                    $isFixedAllocatedAmount = false; // Per unit amount (will be multiplied by area)
                }
            }
        } else {
            $allocatedAmount = (float)($project->allocated_amount ?? 0);
            $isFixedAllocatedAmount = true; // Assume fixed if using old field
        }
        
        if ($allocatedAmount <= 0) {
            return $commissionDistribution;
        }
        
        // Calculate total volume before this sale (exclude current sale for preview)
        // Include team volume to match actual distribution logic
        $totalVolumeBeforeSale = $this->calculateTotalAreaSoldForPropertyType($bookingUser, $propertyTypeModel, null, true);
        
        // Use progressive commission calculation to match actual distribution
        $progressiveCommission = $this->calculateProgressiveCommission(
            $bookingUser,
            $propertyTypeModel,
            $propertyTypeSlug,
            $totalVolumeBeforeSale,
            $plotSize,
            $allocatedAmount
        );
        
        $level1Commission = $progressiveCommission['total_commission'];
        $progressiveBreakdown = $progressiveCommission['breakdown'];
        $primarySlabName = $progressiveCommission['primary_slab_name'] ?? 'Slab1';
        
        // Get primary slab percentage for referral calculation
        $primarySlabPercentage = $this->getSlabCommissionPercentage(
            $primarySlabName,
            $propertyTypeSlug,
            $propertyTypeModel
        );
        
        // Level 1: Booking user (using progressive commission)
        $commissionDistribution[1] = [
            'user_id' => $bookingUser->id,
            'user_name' => $bookingUser->name,
            'broker_id' => $bookingUser->broker_id ?? 'N/A',
            'referral_code' => $bookingUser->referral_code ?? 'N/A',
            'slab_name' => $primarySlabName,
            'commission_amount' => $level1Commission,
            'level' => 1,
            'commission_type' => 'direct',
            'commission_percentage' => $progressiveCommission['weighted_average_percentage'] ?? $primarySlabPercentage,
            'allocated_amount' => $allocatedAmount,
            'area_sold' => $plotSize,
            'progressive_breakdown' => $progressiveBreakdown,
            'total_volume_before_sale' => $totalVolumeBeforeSale,
        ];
        
        // Calculate referral commissions preview using actual level1 commission
        $referralCommissions = $this->previewReferralCommissions(
            $bookingUser,
            $allocatedAmount,
            $plotSize,
            $primarySlabName,
            $primarySlabPercentage,
            $propertyTypeModel,
            $level1Commission
        );
        
        // Add referral commissions to distribution
        foreach ($referralCommissions as $level => $referralCommission) {
            $commissionDistribution[$level] = $referralCommission;
        }
        
        // Add pool information for display
        // Referral Pool Per Unit = Allocated Amount - (Actual Level1 Commission / Area Sold)
        $childCommissionPerUnit = $plotSize > 0 ? ($level1Commission / $plotSize) : 0;
        $referralPoolPerUnit = $allocatedAmount - $childCommissionPerUnit;
        $commissionDistribution['_pool_info'] = [
            'pool_total' => $referralPoolPerUnit * $plotSize,
            'pool_per_unit' => $referralPoolPerUnit,
            'is_fixed' => false,
        ];
        
        return $commissionDistribution;
    }
    
    /**
     * Preview referral commissions without actually distributing
     * 
     * Logic:
     * 1. Referral Pool Per Unit = Allocated Amount - (actual Level1 commission per unit)
     *    When $actualLevel1Commission is provided, use it for accurate pool calculation
     * 2. Parent Referral Commission = (Parent Slab % - Child Slab %) × Allocated Amount × Sold Volume
     * 3. Continue up chain until pool exhausted or no more parents
     */
    private function previewReferralCommissions(
        User $childUser,
        float $allocatedAmount,
        float $areaSold,
        string $childSlabName,
        float $childSlabPercentage,
        PropertyType $propertyType,
        ?float $actualLevel1Commission = null
    ): array {
        $referralDistribution = [];
        
        // Calculate referral pool per unit
        // When actual Level1 commission is provided (e.g. progressive commission), use it so the pool
        // equals what's left after paying the direct seller. Otherwise use child slab %.
        if ($actualLevel1Commission !== null && $areaSold > 0) {
            $childCommissionPerUnit = $actualLevel1Commission / $areaSold;
            $referralPoolPerUnit = $allocatedAmount - $childCommissionPerUnit;
        } else {
            $childCommissionPerUnit = ($allocatedAmount * $childSlabPercentage / 100);
            $referralPoolPerUnit = $allocatedAmount - $childCommissionPerUnit;
        }
        $remainingPoolPerUnit = $referralPoolPerUnit;
        
        // Total referral pool available
        $totalReferralPool = $referralPoolPerUnit * $areaSold;
        $remainingPoolTotal = $totalReferralPool;
        
        if ($remainingPoolPerUnit <= 0.01) {
            return $referralDistribution;
        }
        
        // Traverse up the parent chain
        $parent = $childUser->referredBy;
        $level = 2;
        $currentChildSlabName = $childSlabName;
        $currentChildSlabPercentage = $childSlabPercentage;
        
        // Get initial child slab sort_order for comparison
        $childSlabObject = Slab::where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($childSlabName)])
            ->whereHas('propertyTypes', function($query) use ($propertyType) {
                $query->where('property_types.id', $propertyType->id);
            })
            ->orderBy('sort_order', 'asc')
            ->first();
        
        // Fallback if not found with property type
        if (!$childSlabObject) {
            $childSlabObject = Slab::where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [strtolower($childSlabName)])
                ->orderBy('sort_order', 'asc')
                ->first();
        }
        
        $currentChildSlabSortOrder = $childSlabObject ? (int)($childSlabObject->sort_order ?? 0) : 0;
        
        Log::info("Starting referral chain traversal (preview) - Child: {$childUser->name} (Slab: {$childSlabName} @ {$childSlabPercentage}%, sort_order: {$currentChildSlabSortOrder}), Pool per unit: ₹{$referralPoolPerUnit}, Has parent: " . ($parent ? "Yes ({$parent->name})" : "No"));
        
        // Loop continues as long as there are parents; pool check is inside commission calculation
        while ($parent) {
            $parent->refresh();
            
            // Calculate parent's current slab for this property type
            // For preview, we don't have a sale ID, so excludeSaleId is null
            $parentSlab = $this->calculateCurrentSlabForPropertyType($parent, $propertyType, 0);
            
            if (!$parentSlab) {
                Log::warning("No slab found for parent user {$parent->id} (preview), property type {$propertyType->name}. Using primary slab for child reference update.");
                // Fix: Still update child reference so chain continues. Use parent's primary slab if available.
                $parentPrimarySlab = $parent->slab;
                if ($parentPrimarySlab) {
                    $currentChildSlabName = $parentPrimarySlab->name;
                    $currentChildSlabPercentage = $this->getSlabCommissionPercentage($currentChildSlabName, strtolower(\Illuminate\Support\Str::slug($propertyType->name)), $propertyType);
                    $currentChildSlabSortOrder = (int)($parentPrimarySlab->sort_order ?? 0);
                    Log::info("Child reference updated at Level {$level} (preview, no property slab) - Parent: {$parent->name}, using primary slab: {$currentChildSlabName} (sort_order: {$currentChildSlabSortOrder})");
                } else {
                    Log::info("Child reference NOT updated at Level {$level} (preview) - Parent: {$parent->name} has no slab, keeping previous child: {$currentChildSlabName}");
                }
                $parent = $parent->referredBy;
                $level++;
                continue;
            }
            
            $parentSlabName = $parentSlab->name;
            $parentSlabPercentage = $this->getSlabCommissionPercentage($parentSlabName, strtolower(\Illuminate\Support\Str::slug($propertyType->name)), $propertyType);
            
            // Get sort orders to compare slabs
            $parentSortOrder = (int)($parentSlab->sort_order ?? 0);
            
            // Use the tracked child sort_order (updated after each iteration)
            $childSortOrder = $currentChildSlabSortOrder;
            
            if ($childSortOrder == 0) {
                Log::warning("Child slab '{$currentChildSlabName}' has sort_order 0 (preview), which may cause comparison issues.");
            }
            
            Log::info("Level {$level} (preview) - Comparing Parent: {$parent->name} (Slab: {$parentSlabName}, sort_order: {$parentSortOrder}) vs Child ref: {$currentChildSlabName} (sort_order: {$childSortOrder}), Pool per unit: ₹{$remainingPoolPerUnit}");
            
            // Check if parent is at HIGHER slab than child; pool check is inside (only award if pool has balance)
            if ($parentSortOrder > $childSortOrder && $remainingPoolPerUnit > 0.01) {
                // Calculate slab difference percentage
                $slabDifferencePercentage = $parentSlabPercentage - $currentChildSlabPercentage;
                
                // Parent Referral Commission = (Parent Slab % - Child Slab %) × Allocated Amount × Sold Volume
                $parentReferralCommissionPerUnit = ($allocatedAmount * $slabDifferencePercentage / 100);
                $parentDeservedCommission = $parentReferralCommissionPerUnit * $areaSold;
                
                // Calculate what parent deserves from pool (per unit): min(commission per unit, remaining pool per unit)
                $parentDeservedFromPoolPerUnit = min($parentReferralCommissionPerUnit, $remainingPoolPerUnit);
                $parentReferralCommission = $parentDeservedFromPoolPerUnit * $areaSold;
                
                // Deduct from pool (in per-unit terms)
                $remainingPoolPerUnit -= $parentDeservedFromPoolPerUnit;
                $remainingPoolTotal = $remainingPoolPerUnit * $areaSold;
                
                $referralDistribution[$level] = [
                    'user_id' => $parent->id,
                    'user_name' => $parent->name,
                    'broker_id' => $parent->broker_id ?? 'N/A',
                    'referral_code' => $parent->referral_code ?? 'N/A',
                    'slab_name' => $parentSlabName,
                    'commission_amount' => $parentReferralCommission,
                    'level' => $level,
                    'commission_type' => 'referral',
                    'commission_percentage' => $parentSlabPercentage,
                    'allocated_amount' => $allocatedAmount,
                    'area_sold' => $areaSold,
                    'parent_slab_name' => $parentSlabName,
                    'child_slab_name' => $currentChildSlabName,
                    'parent_slab_percentage' => $parentSlabPercentage,
                    'child_slab_percentage' => $currentChildSlabPercentage,
                    'slab_difference_percentage' => $slabDifferencePercentage,
                    'commission_per_unit' => $parentReferralCommissionPerUnit,
                    'deserved_commission' => $parentDeservedCommission,
                    'pool_remaining_per_unit' => $remainingPoolPerUnit,
                    'pool_remaining_total' => $remainingPoolTotal,
                ];
                
                Log::info("Referral commission Level {$level} (preview) - Parent: {$parent->name} (Slab {$parentSlabName} @ {$parentSlabPercentage}%), Child: {$currentChildSlabName} @ {$currentChildSlabPercentage}%, Commission: ₹{$parentReferralCommission}, Pool Remaining per unit: ₹{$remainingPoolPerUnit}");
            } else {
                // No referral commission - log why
                if ($parentSortOrder <= $childSortOrder) {
                    Log::info("No referral commission Level {$level} (preview) - Parent Slab {$parentSlabName} (sort_order: {$parentSortOrder}) <= Child Slab {$currentChildSlabName} (sort_order: {$childSortOrder})");
                } else {
                    Log::info("No referral commission Level {$level} (preview) - Pool exhausted: ₹{$remainingPoolPerUnit} per unit remaining");
                }
            }
            
            // IMPORTANT: Always update child slab reference after each parent check, even if they didn't qualify
            // The current parent becomes the "child" for the next level
            $currentChildSlabName = $parentSlabName;
            $currentChildSlabPercentage = $parentSlabPercentage;
            $currentChildSlabSortOrder = $parentSortOrder;
            
            Log::info("Child reference updated at Level {$level} (preview) - Next level will compare against: {$currentChildSlabName} @ {$currentChildSlabPercentage}% (sort_order: {$currentChildSlabSortOrder}), Pool remaining per unit: ₹{$remainingPoolPerUnit}");
            
            // Move to next level parent
            $parent = $parent->referredBy;
            $level++;
            
            if ($parent) {
                Log::info("Next parent found (preview) - Level {$level}: {$parent->name}, Pool remaining: ₹{$remainingPoolPerUnit}");
            } else {
                Log::info("No more parents in chain (preview) - Chain ended at level " . ($level - 1) . ", Pool remaining: ₹{$remainingPoolPerUnit}");
            }
        }
        
        Log::info("Referral chain traversal completed (preview) - Total levels processed: " . ($level - 2) . ", Final pool remaining per unit: ₹{$remainingPoolPerUnit}");
        
        return $referralDistribution;
    }

    /**
     * Distribute commission to the direct seller (Level 1 only)
     * 
     * @param Sale $sale The sale/booking record
     * @param Project $project The project being booked
     * @param User $bookingUser The user who made the booking
     * @return array Commission distribution details
     */
    public function distributeCommission(Sale $sale, Project $project, User $bookingUser): array
    {
        $commissionDistribution = [];
        $totalCommissionDistributed = 0;
        
        // Validate sale status - only distribute for confirmed sales
        if ($sale->status !== 'confirmed') {
            Log::warning("Cannot distribute commission for sale {$sale->id} with status: {$sale->status}. Only confirmed sales can receive commissions.");
            return $commissionDistribution;
        }
        
        // Prevent duplicate commission distribution (unless force flag is set via sale metadata)
        // Note: Force recalculation should be handled by RecalculateCommissionsCommand which reverts first
        if ($sale->commission_distribution && !empty($sale->commission_distribution)) {
            Log::warning("Commission already distributed for sale {$sale->id}. Skipping to prevent duplicate distribution.");
            return $commissionDistribution;
        }
        
        // Get plot and property details for commission calculation
        $plot = $sale->plot;
        $areaSold = (float)($plot->size ?? $project->plot_size ?? 0); // Area sold in property's measurement unit
        
        if ($areaSold <= 0) {
            Log::warning("Zero or invalid area sold for sale {$sale->id}");
            return $commissionDistribution;
        }
        
        // Keep price info for display/metadata (not used in commission calculation)
        $plotPricePerUnit = (float)($plot->price_per_unit ?? 0);
        $projectPricePerSqft = (float)($project->price_per_sqft ?? 0);
        $plotPrice = $plotPricePerUnit > 0 ? ($plotPricePerUnit * $areaSold) : ($projectPricePerSqft * $areaSold);

        // Get property type from plot
        $propertyTypeSlug = strtolower(trim($plot->type ?? 'plot')); // Default to 'plot' if not set
        
        // Get PropertyType model to access measurement unit
        $propertyTypeModel = PropertyType::where('is_active', true)
            ->with('measurementUnit')
            ->get()
            ->first(function ($pt) use ($propertyTypeSlug) {
                $nameLower = strtolower($pt->name);
                $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                return $nameLower === $propertyTypeSlug || 
                       $slugFromName === $propertyTypeSlug ||
                       str_replace(' ', '-', $nameLower) === $propertyTypeSlug ||
                       str_replace(' ', '_', $nameLower) === $propertyTypeSlug;
            });
        
        if (!$propertyTypeModel) {
            Log::warning("Property type not found for slug: {$propertyTypeSlug}, sale ID: {$sale->id}");
            return $commissionDistribution;
        }
        
        // Get allocated amount from project config (per property type)
        $propertyTypeName = $propertyTypeModel->name;
        
        // Get allocated amount config from project
        $allocatedAmountConfig = $project->allocated_amount_config ?? [];
        $propertyTypeConfig = $allocatedAmountConfig[$propertyTypeName] ?? null;
        
        // Calculate allocated amount based on config
        $allocatedAmount = 0;
        if ($propertyTypeConfig) {
            $configType = $propertyTypeConfig['type'] ?? 'fixed';
            $configValue = (float)($propertyTypeConfig['value'] ?? 0);
            
            if ($configType === 'fixed') {
                // Fixed amount: use value directly
                $allocatedAmount = $configValue;
            } elseif ($configType === 'percentage') {
                // Percentage: calculate from property rate
                // Try plot's price_per_unit first, then project's price_per_sqft
                $propertyRatePerUnit = (float)($plot->price_per_unit ?? 0);
                if ($propertyRatePerUnit <= 0) {
                    $propertyRatePerUnit = (float)($project->price_per_sqft ?? 0);
                }
                
                if ($propertyRatePerUnit > 0 && $configValue > 0) {
                    $allocatedAmount = ($propertyRatePerUnit * $configValue / 100);
                } else {
                    Log::warning("Cannot calculate percentage-based allocated amount for property type {$propertyTypeName}. Rate: {$propertyRatePerUnit}, Percentage: {$configValue}");
                }
            }
        } else {
            // Fallback to old allocated_amount field (for backward compatibility)
            $allocatedAmount = (float)($project->allocated_amount ?? 0);
        }
        
        if ($allocatedAmount <= 0) {
            Log::warning("Zero or invalid allocated amount for project {$project->id}, sale {$sale->id}, property type {$propertyTypeName}");
            return $commissionDistribution;
        }

        // PROGRESSIVE COMMISSION CALCULATION: Calculate commission based on volume tiers
        // Each portion of the sale is paid at the slab rate for that volume range
        // Example: If user sold 200 sqyd (Slab1: 0-250), then sells 150 sqyd more:
        //   - First 50 sqyd (200-250) → Slab1 rate
        //   - Remaining 100 sqyd (250-350) → Slab2 rate
        // 
        // IMPORTANT: Progressive commission uses OWN + TEAM volume (same as slab calculation)
        // This ensures consistency - slabs are based on total volume, so progressive tiers should be too
        
        // IMPORTANT: Refresh user model to get latest slab (in case user was upgraded from previous sale)
        $bookingUser->refresh();
        
        // Get total volume sold BEFORE this sale (excluding current sale)
        // Include team volume to match slab calculation logic
        $totalVolumeBeforeSale = $this->calculateTotalAreaSoldForPropertyType($bookingUser, $propertyTypeModel, $sale->id, true);
        
        Log::info("Progressive commission calculation - User ID: {$bookingUser->id}, Property Type: {$propertyTypeModel->name}, Total Volume Before: {$totalVolumeBeforeSale}, Current Sale Area: {$areaSold}");
        
        // Calculate progressive commission breakdown using allocated amount and percentage
        $progressiveCommission = $this->calculateProgressiveCommission(
            $bookingUser,
            $propertyTypeModel,
            $propertyTypeSlug,
            $totalVolumeBeforeSale,
            $areaSold,
            $allocatedAmount
        );
        
        if ($progressiveCommission['total_commission'] <= 0) {
            Log::warning("Zero or invalid progressive commission calculated for user ID: {$bookingUser->id}, property type: {$propertyTypeModel->name}");
            return $commissionDistribution;
        }
        
        $level1Commission = $progressiveCommission['total_commission'];
        $progressiveBreakdown = $progressiveCommission['breakdown'];
        $primarySlabName = $progressiveCommission['primary_slab_name'] ?? 'Slab1';
        
        // Get primary slab for metadata - use case-insensitive matching
        $primarySlab = Slab::whereRaw('LOWER(name) = ?', [strtolower($primarySlabName)])->first();
        $bookingUserSlabId = $primarySlab ? $primarySlab->id : null;
        
        if (!$bookingUserSlabId) {
            // If primary slab not found, calculate the actual slab for this property type
            // Exclude current sale ID to prevent double counting (sale might already be in DB)
            Log::warning("Primary slab not found: {$primarySlabName}, calculating slab for property type");
            $actualSlab = $this->calculateCurrentSlabForPropertyType($bookingUser, $propertyTypeModel, $areaSold, $sale->id);
            if ($actualSlab) {
                $primarySlabName = $actualSlab->name;
                $bookingUserSlabId = $actualSlab->id;
                Log::info("Using calculated slab for property type: {$primarySlabName}");
            } else {
                // Last resort: use user's primary slab (but this might be wrong for this property type)
                $bookingUserSlab = $bookingUser->slab;
                $bookingUserSlabId = $bookingUserSlab ? $bookingUserSlab->id : null;
                $primarySlabName = $bookingUserSlab ? $bookingUserSlab->name : 'Slab1';
                Log::warning("Using fallback primary slab: {$primarySlabName}");
            }
        }

        // Level 1: Booking user gets the commission (progressive calculation)
        $commissionDistribution[1] = [
            'user_id' => $bookingUser->id,
            'user_name' => $bookingUser->name,
            'slab_id' => $bookingUserSlabId,
            'slab_name' => $primarySlabName,
            'commission_amount' => $level1Commission,
            'level' => 1,
            'commission_type' => 'direct',
            'commission_percentage' => $progressiveCommission['weighted_average_percentage'] ?? 0, // Weighted average percentage for display
            'allocated_amount' => $allocatedAmount, // Allocated amount used for calculation
            'area_sold' => $areaSold,
            'measurement_unit' => $propertyTypeModel->measurementUnit->name ?? 'unit',
            'measurement_unit_symbol' => $propertyTypeModel->measurementUnit->symbol ?? '',
            'price_per_unit' => $plotPricePerUnit > 0 ? $plotPricePerUnit : $projectPricePerSqft, // Price per measurement unit (sqft or sqyd) - for display only
            'price_per_sqft' => $projectPricePerSqft, // Keep for backward compatibility
            'plot_price' => $plotPrice, // Total plot price = price_per_unit × area_sold - for display only
            'progressive_breakdown' => $progressiveBreakdown, // Store breakdown for detailed display
            'total_volume_before_sale' => $totalVolumeBeforeSale, // Total volume before this sale
        ];
        $totalCommissionDistributed += $level1Commission;

        // Get the actual commission percentage for the primary slab (not weighted average)
        // This is needed for accurate referral pool calculation
        $primarySlabPercentage = $this->getSlabCommissionPercentage(
            $primarySlabName,
            strtolower(\Illuminate\Support\Str::slug($propertyTypeModel->name)),
            $propertyTypeModel
        );
        
        // Use primary slab percentage for referral calculation (not weighted average)
        // The weighted average is for display only - referral pool should use the primary slab percentage
        $childSlabPercentageForReferral = $primarySlabPercentage > 0 
            ? $primarySlabPercentage 
            : ($progressiveCommission['weighted_average_percentage'] ?? 0);
        
        Log::info("Using child slab percentage for referral calculation", [
            'primary_slab_name' => $primarySlabName,
            'primary_slab_percentage' => $primarySlabPercentage,
            'weighted_average_percentage' => $progressiveCommission['weighted_average_percentage'] ?? 0,
            'using_percentage' => $childSlabPercentageForReferral,
        ]);
        
        // Calculate and distribute referral commissions to parent chain.
        // Child slab for referral = seller's slab AFTER this sale (the slab they "came to").
        // primarySlabName from progressive commission is that updated slab; referral = (parent_slab - child_slab) × allocated × area.
        // Pass actual level1 commission so referral pool = allocated - (level1/areaSold).
        $referralCommissions = $this->distributeReferralCommissions(
            $sale,
            $bookingUser,
            $allocatedAmount,
            $areaSold,
            $primarySlabName,
            $childSlabPercentageForReferral,
            $propertyTypeModel,
            $level1Commission
        );
        
        // Add referral commissions to distribution array (skip meta entries like _remaining_pool)
        foreach ($referralCommissions as $level => $referralCommission) {
            $commissionDistribution[$level] = $referralCommission;
            if (isset($referralCommission['commission_amount'])) {
                $totalCommissionDistributed += $referralCommission['commission_amount'];
            }
        }

        // Record commissions as PENDING (no wallet balance impact yet).
        // Wallet balance should only be credited when the deal is marked as done.
        $this->recordPendingCommissions($commissionDistribution, $sale, $bookingUser->id);

        // Update sale record with commission distribution (after recording pending commissions)
        $sale->refresh();
        
        // Calculate total including remaining pool
        $totalIncludingRemainingPool = $totalCommissionDistributed;
        if (isset($commissionDistribution['_remaining_pool'])) {
            $totalIncludingRemainingPool += $commissionDistribution['_remaining_pool']['amount'];
        }
        
        $sale->update([
            'commission_amount' => $totalIncludingRemainingPool,
            'commission_distribution' => $commissionDistribution,
        ]);

        // Automatically upgrade slab for seller and all parents (slab = own + team sales)
        // Wrap in try-catch to prevent slab upgrade failures from affecting commission distribution
        try {
            $this->upgradeUserSlab($bookingUser, $sale);
            $parent = $bookingUser->referredBy;
            while ($parent) {
                $this->upgradeUserSlab($parent, $sale);
                $parent = $parent->referredBy;
            }
        } catch (\Exception $e) {
            // Log error but don't fail commission distribution
            Log::error("Failed to upgrade slabs after commission distribution for sale {$sale->id}: " . $e->getMessage());
        }

        return $commissionDistribution;
    }

    /**
     * Record commissions as pending transactions and per-sale commission rows.
     * IMPORTANT: This does NOT change wallet balances. Balances are credited on Deal Done.
     */
    private function recordPendingCommissions(array $commissionDistribution, Sale $sale, $bookingUserId): void
    {
        DB::beginTransaction();
        
        try {
            foreach ($commissionDistribution as $level => $distribution) {
                // Skip meta entries (e.g. _pool_info) that don't represent a user commission
                if (!is_array($distribution) || !isset($distribution['user_id'], $distribution['commission_amount'])) {
                    continue;
                }
                $userId = $distribution['user_id'];
                $commissionAmount = $distribution['commission_amount'];
                if ($userId === null || $commissionAmount === null || $commissionAmount <= 0) {
                    continue;
                }

                // Track per-sale commission ceiling and released amount for future proportional releases
                SaleCommissionRelease::updateOrCreate(
                    ['sale_id' => $sale->id, 'user_id' => $userId],
                    // released_amount stays 0 until proportional release (optional) or deal done.
                    ['total_commission' => $commissionAmount, 'released_amount' => 0]
                );

                // Reuse the same pending transaction per (sale_id, user_id) to avoid duplicates.
                $existingPending = DB::table('transactions')
                    ->where('user_id', $userId)
                    ->where('type', 'commission')
                    ->where('status', 'pending')
                    ->where('reference_id', $sale->id)
                    ->first();

                $transactionId = $existingPending
                    ? $existingPending->transaction_id
                    : ('TXN' . strtoupper(uniqid()) . time() . $userId);

                while (!$existingPending && DB::table('transactions')->where('transaction_id', $transactionId)->exists()) {
                    $transactionId = 'TXN' . strtoupper(uniqid()) . time() . $userId . rand(1000, 9999);
                }
                
                // Get plot and project details
                $plot = DB::table('plots')->where('id', $sale->plot_id)->first();
                $project = $plot ? DB::table('projects')->where('id', $plot->project_id)->first() : null;
                
                // Get distribution details for this level
                $distribution = $commissionDistribution[$level] ?? [];
                
                // Build description with full details
                $commissionType = $distribution['commission_type'] ?? 'direct';
                $commissionPercentage = $distribution['commission_percentage'] ?? 0;
                $allocatedAmount = $distribution['allocated_amount'] ?? 0;
                $areaSold = $distribution['area_sold'] ?? 0;
                $unitSymbol = $distribution['measurement_unit_symbol'] ?? $distribution['measurement_unit'] ?? 'unit';
                
                // Use commissionLevel to avoid shadowing the loop variable $level
                $commissionLevel = $distribution['level'] ?? $level;
                
                if ($commissionType === 'referral') {
                    $parentSlabName = $distribution['parent_slab_name'] ?? '';
                    $childSlabName = $distribution['child_slab_name'] ?? '';
                    $slabDifference = $distribution['slab_difference_percentage'] ?? 0;
                    $description = "Level {$commissionLevel} Referral commission (Parent: {$parentSlabName} @ {$distribution['parent_slab_percentage']}%, Child: {$childSlabName} @ {$distribution['child_slab_percentage']}%, Difference: {$slabDifference}% × ₹{$allocatedAmount} × {$areaSold} {$unitSymbol} = ₹" . number_format($commissionAmount, 2) . ")";
                } else {
                    $description = "Direct commission (₹{$allocatedAmount} × {$commissionPercentage}% × {$areaSold} {$unitSymbol} = ₹" . number_format($commissionAmount, 2) . ")";
                }
                
                if ($project && $plot) {
                    $description .= " - {$project->name}, {$plot->type} {$plot->plot_number}, {$project->location}";
                } else {
                    $description .= " - Sale #{$sale->id}";
                }
                
                // Store metadata with full details including calculation breakdown
                $metadata = [
                    'sale_id' => $sale->id,
                    'plot_id' => $sale->plot_id,
                    'plot_number' => $plot ? $plot->plot_number : null,
                    'plot_type' => $plot ? $plot->type : null,
                    'project_id' => $project ? $project->id : null,
                    'project_name' => $project ? $project->name : null,
                    'project_location' => $project ? $project->location : null,
                    'level' => $commissionLevel,
                    'booking_amount' => (float)($sale->booking_amount ?? 0),
                    'booking_date' => $sale->created_at ? $sale->created_at->format('Y-m-d H:i:s') : null,
                    'customer_name' => $sale->customer_name ?? null,
                    'source' => $distribution['commission_type'] ?? 'direct', // Commission source (direct or referral)
                    // Calculation details
                    'price_per_unit' => $distribution['price_per_unit'] ?? null, // Price per measurement unit (for display only)
                    'price_per_sqft' => $distribution['price_per_sqft'] ?? null, // Keep for backward compatibility
                    'area_sold' => $distribution['area_sold'] ?? null,
                    'plot_price' => $distribution['plot_price'] ?? null, // For display only
                    'allocated_amount' => $distribution['allocated_amount'] ?? null, // Allocated amount used for calculation
                    'commission_percentage' => $distribution['commission_percentage'] ?? null, // Commission percentage
                    'measurement_unit' => $distribution['measurement_unit'] ?? null,
                    'measurement_unit_symbol' => $distribution['measurement_unit_symbol'] ?? null,
                    'slab_name' => $distribution['slab_name'] ?? null,
                    // Progressive commission breakdown
                    'progressive_breakdown' => $distribution['progressive_breakdown'] ?? null,
                    'total_volume_before_sale' => $distribution['total_volume_before_sale'] ?? null,
                ];
                
                $metadata['projected_amount'] = $commissionAmount;
                $metadata['source'] = 'commission_pending';

                // Mark as pending and do not set balances yet (wallet not credited).
                $payload = [
                    'user_id' => $userId,
                    'transaction_id' => $transactionId,
                    'type' => 'commission',
                    'amount' => $commissionAmount,
                    'status' => 'pending',
                    'description' => $description . ' [Pending until Deal Done]',
                    // Some DB schemas enforce NOT NULL for these columns; use 0 until finalized.
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'reference_id' => $sale->id,
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                ];

                if ($existingPending) {
                    DB::table('transactions')->where('id', $existingPending->id)->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::table('transactions')->insert($payload);
                }
            }

            // Handle remaining pool if exists (send to admin wallet)
            if (isset($commissionDistribution['_remaining_pool'])) {
                $remainingPool = $commissionDistribution['_remaining_pool'];
                $adminUserId = $remainingPool['user_id'];
                $remainingPoolAmount = $remainingPool['amount'];
                
                // Admin "remaining pool" is also pending until deal done.
                $existingPending = DB::table('transactions')
                    ->where('user_id', $adminUserId)
                    ->where('type', 'commission')
                    ->where('status', 'pending')
                    ->where('reference_id', $sale->id)
                    ->first();

                $transactionId = $existingPending
                    ? $existingPending->transaction_id
                    : ('TXN' . strtoupper(uniqid()) . time() . $adminUserId);

                while (!$existingPending && DB::table('transactions')->where('transaction_id', $transactionId)->exists()) {
                    $transactionId = 'TXN' . strtoupper(uniqid()) . time() . $adminUserId . rand(1000, 9999);
                }
                
                // Get plot and project details
                $plot = DB::table('plots')->where('id', $sale->plot_id)->first();
                $project = $plot ? DB::table('projects')->where('id', $plot->project_id)->first() : null;
                
                $description = "Remaining referral pool (₹" . number_format($remainingPool['per_unit'], 2) . " per unit × {$remainingPool['area_sold']} units = ₹" . number_format($remainingPoolAmount, 2) . ") - Referral chain ended";
                
                if ($project && $plot) {
                    $description .= " - {$project->name}, {$plot->type} {$plot->plot_number}, {$project->location}";
                } else {
                    $description .= " - Sale #{$sale->id}";
                }
               
                $payload = [
                    'user_id' => $adminUserId,
                    'transaction_id' => $transactionId,
                    'type' => 'commission',
                    'amount' => $remainingPoolAmount,
                    'status' => 'pending',
                    'description' => $description . ' [Pending until Deal Done]',
                    // Some DB schemas enforce NOT NULL for these columns; use 0 until finalized.
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'reference_id' => $sale->id,
                    'metadata' => json_encode([
                        'sale_id' => $sale->id,
                        'source' => 'remaining_pool_pending',
                        'remaining_pool_per_unit' => $remainingPool['per_unit'],
                        'area_sold' => $remainingPool['area_sold'],
                        'notes' => 'Remaining referral pool pending until deal is marked done',
                    ]),
                    'updated_at' => now(),
                ];

           
                if ($existingPending) {
                    DB::table('transactions')->where('id', $existingPending->id)->update($payload);
                } else {
                      
                    $payload['created_at'] = now();
                    DB::table('transactions')->insert($payload);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to record pending commissions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Release commission to Gross wallet proportionally when client pays more (instalment).
     * Called when an instalment payment is approved (or admin records a payment).
     */
    public function releaseProportionalCommission(Sale $sale): void
    {
        $totalSaleValue = (float)($sale->total_sale_value ?? 0);
        if ($totalSaleValue <= 0) {
            Log::warning("Sale {$sale->id} has no total_sale_value. Skipping proportional release.");
            return;
        }
        $totalReceived = (float) PaymentRequest::where('sale_id', $sale->id)->where('status', 'approved')->sum('amount');
        $ratio = min(1, $totalReceived / $totalSaleValue);

        $releases = SaleCommissionRelease::where('sale_id', $sale->id)->get();
        if ($releases->isEmpty()) {
            Log::warning("No sale_commission_releases for sale {$sale->id}. Skipping proportional release.");
            return;
        }

        DB::beginTransaction();
        try {
            $plot = $sale->plot;
            $project = $plot ? $plot->project : null;
            foreach ($releases as $row) {
                $newRelease = round($ratio * (float)$row->total_commission, 2);
                $releasedSoFar = (float)$row->released_amount;
                $delta = round($newRelease - $releasedSoFar, 2);
                if ($delta <= 0) {
                    continue;
                }
                // Only update tracking table. Wallet balances are credited on Deal Done.
                $row->update(['released_amount' => $newRelease]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to release proportional commission for sale {$sale->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get commission percentage for a slab from settings
     * Priority: Property-type-based commission > Default slab commission
     * 
     * @param string $slabName Slab name (Slab1, Slab2, Slab3, etc.)
     * @param string $propertyTypeSlug Property type slug (plot, villa, flat, etc.)
     * @param PropertyType|null $propertyTypeModel PropertyType model (optional, for measurement unit info)
     * @return float Commission percentage (e.g., 35.0 for 35%)
     */
    public function getSlabCommissionPercentage(string $slabName, string $propertyTypeSlug, $propertyTypeModel = null): float
    {
        // First, try to get property-type-based commission
        $propertyTypeCommissionsJson = Setting::get('property_type_commissions', '{}');
        $propertyTypeCommissions = json_decode($propertyTypeCommissionsJson, true) ?? [];
        
        // Normalize property type (handle slugs like 'plot', 'villa', 'flat', etc.)
        $normalizedPropertyType = strtolower(trim($propertyTypeSlug));
        
        // Try to find property type name from model or slug
        $propertyTypeName = null;
        if ($propertyTypeModel) {
            $propertyTypeName = $propertyTypeModel->name;
        } else {
            // Try to find property type by slug
            $propertyTypeModel = PropertyType::where('is_active', true)
                ->get()
                ->first(function ($pt) use ($normalizedPropertyType) {
                    $nameLower = strtolower($pt->name);
                    $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                    return $nameLower === $normalizedPropertyType || 
                           $slugFromName === $normalizedPropertyType ||
                           str_replace(' ', '-', $nameLower) === $normalizedPropertyType ||
                           str_replace(' ', '_', $nameLower) === $normalizedPropertyType;
                });
            
            if ($propertyTypeModel) {
                $propertyTypeName = $propertyTypeModel->name;
            }
        }
        
        // Debug logging
        Log::info("Commission lookup - Slab: {$slabName}, Property Type Slug: {$propertyTypeSlug}, Property Type Name: " . ($propertyTypeName ?? 'N/A'));
        
        // Check if property-type-based commission exists (by name)
        // First, try to find property type in commissions (case-insensitive matching)
        $matchedPropertyType = null;
        if ($propertyTypeName) {
            // Try exact match first
            if (isset($propertyTypeCommissions[$propertyTypeName])) {
                $matchedPropertyType = $propertyTypeName;
            } else {
                // Try case-insensitive match
                foreach ($propertyTypeCommissions as $key => $value) {
                    if (strcasecmp($key, $propertyTypeName) === 0) {
                        $matchedPropertyType = $key;
                        break;
                    }
                }
            }
        }
        
        if ($matchedPropertyType && isset($propertyTypeCommissions[$matchedPropertyType]) && 
            is_array($propertyTypeCommissions[$matchedPropertyType])) {
            
            // Step 1: Try exact slab name match first (e.g., "Slab1" matches "Slab1")
            if (isset($propertyTypeCommissions[$matchedPropertyType][$slabName])) {
                $commission = (float)$propertyTypeCommissions[$matchedPropertyType][$slabName];
                if ($commission > 0) {
                    Log::info("Found property-type commission (exact match): {$matchedPropertyType} -> {$slabName} = {$commission}%");
                    return $commission;
                }
            }
            
            // Step 2: Try case-insensitive exact slab name match
            foreach ($propertyTypeCommissions[$matchedPropertyType] as $key => $value) {
                if (strcasecmp($key, $slabName) === 0) {
                    $commission = (float)$value;
                    if ($commission > 0) {
                        Log::info("Found property-type commission (case-insensitive exact match): {$matchedPropertyType} -> {$key} = {$commission}%");
                        return $commission;
                    }
                }
            }
            
            // Step 3: Try matching by extracting numeric part (e.g., "Slab1" -> "1")
            // This helps if commissions are stored differently
            $slabNumber = null;
            if (preg_match('/\d+/', $slabName, $matches)) {
                $slabNumber = $matches[0]; // Extract number from "Slab1" -> "1"
                
                // Try matching with numeric part (e.g., "Slab1" matches "1" or "Slab1")
                $numericKey = 'Slab' . $slabNumber;
                if (isset($propertyTypeCommissions[$matchedPropertyType][$numericKey])) {
                    $commission = (float)$propertyTypeCommissions[$matchedPropertyType][$numericKey];
                    if ($commission > 0) {
                        Log::info("Found property-type commission (numeric key match): {$matchedPropertyType} -> {$numericKey} = {$commission}%");
                        return $commission;
                    }
                }
            }
            
            // Step 4: Try partial matching - any slab key that contains the full slab name or vice versa
            foreach ($propertyTypeCommissions[$matchedPropertyType] as $key => $value) {
                // Check if key contains slabName or slabName contains key (case-insensitive)
                if (stripos($key, $slabName) !== false || stripos($slabName, $key) !== false) {
                    $commission = (float)$value;
                    if ($commission > 0) {
                        Log::info("Found property-type commission (partial match): {$matchedPropertyType} -> {$key} = {$commission}%");
                        return $commission;
                    }
                }
            }
            
            // Step 5: If slabNumber exists, try matching by number
            if ($slabNumber) {
                foreach ($propertyTypeCommissions[$matchedPropertyType] as $key => $value) {
                    if (preg_match('/\d+/', $key, $keyMatches) && $keyMatches[0] === $slabNumber) {
                        $commission = (float)$value;
                        if ($commission > 0) {
                            Log::info("Found property-type commission (number match): {$matchedPropertyType} -> {$key} = {$commission}%");
                            return $commission;
                        }
                    }
                }
            }
        }
        
        // Fallback to default slab commission
        // Extract slab number from name (e.g., "Slab1" -> "1", "Slab2" -> "2")
        $slabNumber = null;
        if (preg_match('/\d+/', $slabName, $matches)) {
            $slabNumber = (int)$matches[0];
        }
        
        // Map slab numbers to setting keys (Slab1 -> slab_commission_bronze, Slab2 -> slab_commission_silver, etc.)
        // Also map display names (Bronze, Silver, Gold, Diamond) for slabs that don't use numeric names
        $slabKeyMap = [
            1 => 'slab_commission_bronze',   // Slab1
            2 => 'slab_commission_silver',  // Slab2
            3 => 'slab_commission_gold',    // Slab3
            4 => 'slab_commission_diamond', // Slab4
        ];
        $slabNameToKeyMap = [
            'bronze' => 'slab_commission_bronze',
            'silver' => 'slab_commission_silver',
            'gold' => 'slab_commission_gold',
            'diamond' => 'slab_commission_diamond',
        ];

        $key = null;
        if ($slabNumber && isset($slabKeyMap[$slabNumber])) {
            $key = $slabKeyMap[$slabNumber];
        }
        if (!$key && $slabName !== '') {
            $nameLower = strtolower(trim($slabName));
            if (isset($slabNameToKeyMap[$nameLower])) {
                $key = $slabNameToKeyMap[$nameLower];
            }
        }

        if (!$key) {
            Log::warning("No commission key found for slab: {$slabName} (number: " . ($slabNumber ?? 'N/A') . ", name: " . ($slabName ?? 'N/A') . "). Property type: " . ($propertyTypeName ?? 'N/A') . ". Please configure commission in property_type_commissions setting.");
            return 0;
        }

        // Get from settings with defaults (now percentages, not fixed amounts)
        // Only provide defaults for first 4 slabs, others should be configured in property_type_commissions
        $defaults = [
            'slab_commission_bronze' => 35.0, // 35% (Slab1)
            'slab_commission_silver' => 40.0, // 40% (Slab2)
            'slab_commission_gold' => 45.0,   // 45% (Slab3)
            'slab_commission_diamond' => 50.0, // 50% (Slab4)
        ];

        $commission = (float)Setting::get($key, $defaults[$key] ?? 0);
        if ($commission > 0) {
            Log::info("Using default commission percentage for slab: {$slabName} (number: {$slabNumber}, key: {$key}) = {$commission}%");
        } else {
            Log::warning("Default commission percentage is 0 for slab: {$slabName} (number: {$slabNumber}, key: {$key}). Please configure commission in settings.");
        }
        return $commission;
    }

    /**
     * Calculate current slab for a user based on total area sold for a specific property type
     * This ensures commission uses the correct slab based on actual performance, not stored slab_id
     * 
     * @param User $user The user whose slab needs to be calculated
     * @param PropertyType $propertyType The property type to calculate slab for
     * @param float $currentSaleArea Area of the current sale being processed (to include in calculation)
     * @param int|null $excludeSaleId Sale ID to exclude from calculation (to prevent double counting)
     * @return Slab|null The calculated slab, or null if not found
     */
    public function calculateCurrentSlabForPropertyType(User $user, PropertyType $propertyType, float $currentSaleArea = 0, ?int $excludeSaleId = null): ?Slab
    {
        try {
            $propertyTypeName = $propertyType->name;
            $propertyTypeSlug = strtolower(\Illuminate\Support\Str::slug($propertyTypeName));
            
            // Slab is based on OWN sales + TEAM (downline) sales for this property type
            // Exclude the current sale from the query when excludeSaleId is set (to avoid double-counting if sale is already in DB)
            $totalAreaSold = $this->calculateTotalAreaSoldForPropertyType($user, $propertyType, $excludeSaleId, true);
            
            // When currentSaleArea > 0, add it so we get the slab the user "came to" after this sale.
            // This is required for: (1) referral child slab = seller's updated slab, (2) fallback when primary slab not found by name.
            // When called for a parent with currentSaleArea=0, we get their slab before this sale (correct).
            if ($currentSaleArea > 0) {
                $totalAreaSold += $currentSaleArea;
            }

            // Get slabs that apply to this property type
            $applicableSlabs = Slab::where('is_active', true)
                ->whereHas('propertyTypes', function($query) use ($propertyType) {
                    $query->where('property_types.id', $propertyType->id);
                })
                ->orderBy('sort_order')
                ->get();

            if ($applicableSlabs->isEmpty()) {
                Log::warning("No slabs configured for property type: {$propertyTypeName}, user ID: {$user->id}");
                return null;
            }

            // Find matching slab based on total area sold
            foreach ($applicableSlabs as $slab) {
                $minTarget = (float)($slab->minimum_target ?? 0);
                $maxTarget = (float)($slab->maximum_target ?? 999999999);
                
                if ($totalAreaSold >= $minTarget && $totalAreaSold < $maxTarget) {
                    Log::info("Calculated slab for user ID: {$user->id}, property type: {$propertyTypeName}, area sold: {$totalAreaSold}, slab: {$slab->name}");
                    return $slab;
                }
            }

            // Fallback: return first slab (lowest tier) if no match found
            $fallbackSlab = $applicableSlabs->first();
            Log::info("Using fallback slab for user ID: {$user->id}, property type: {$propertyTypeName}, area sold: {$totalAreaSold}, slab: {$fallbackSlab->name}");
            return $fallbackSlab;

        } catch (\Exception $e) {
            Log::error("Error calculating slab for user ID: {$user->id}, property type: {$propertyType->name}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Automatically upgrade user's slab for the specific property type of the sale
     * This is called after commission distribution to ensure user_slabs table stays updated
     * 
     * @param User $user The user whose slab needs to be upgraded
     * @param Sale $sale The sale that triggered this check
     * @return void
     */
    private function upgradeUserSlab(User $user, Sale $sale): void
    {
        try {
            // Get property type from the sale's plot
            $plot = $sale->plot;
            if (!$plot || !$plot->type) {
                Log::warning("Sale #{$sale->id} has no plot or plot type, cannot determine property type for slab upgrade");
                return;
            }
            
            $plotTypeSlug = strtolower(trim($plot->type));
            
            // Find matching property type
            $allPropertyTypes = PropertyType::where('is_active', true)->get();
            $propertyType = null;
            
            foreach ($allPropertyTypes as $pt) {
                $nameLower = strtolower($pt->name);
                $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                if ($nameLower === $plotTypeSlug || 
                    $slugFromName === $plotTypeSlug ||
                    str_replace(' ', '-', $nameLower) === $plotTypeSlug ||
                    str_replace(' ', '_', $nameLower) === $plotTypeSlug) {
                    $propertyType = $pt;
                    break;
                }
            }
            
            if (!$propertyType) {
                Log::warning("Property type not found for plot type: {$plotTypeSlug}, sale ID: {$sale->id}");
                return;
            }
            
            // Calculate current slab for THIS property type only
            // Passing 0 for currentSaleArea and null for excludeSaleId because current sale is already in database
            // The sale will be included in the query, which is correct for slab upgrade calculation
            $newSlab = $this->calculateCurrentSlabForPropertyType($user, $propertyType, 0, null);
            
            if (!$newSlab) {
                Log::warning("No slab calculated for property type: {$propertyType->name}, user ID: {$user->id}");
                return;
            }
            
            // Get current slab for this property type from user_slabs table
            $currentUserSlab = DB::table('user_slabs')
                ->where('user_id', $user->id)
                ->where('property_type_id', $propertyType->id)
                ->first();
            
            $currentSlabId = $currentUserSlab ? $currentUserSlab->slab_id : null;
            
            // Only upgrade if the slab has changed for this property type
            if ($currentSlabId != $newSlab->id) {
                $oldSlab = $currentSlabId ? Slab::find($currentSlabId) : null;
                $oldSlabName = $oldSlab ? $oldSlab->name : 'None';
                
                // Total area sold (own + team) for this property type - same as used for slab calculation
                $totalAreaSold = $this->calculateTotalAreaSoldForPropertyType($user, $propertyType, 0, true);
                
                // Update or create user_slabs entry for this property type
                if ($currentUserSlab) {
                    DB::table('user_slabs')
                        ->where('user_id', $user->id)
                        ->where('property_type_id', $propertyType->id)
                        ->update([
                            'slab_id' => $newSlab->id,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('user_slabs')->insert([
                        'user_id' => $user->id,
                        'property_type_id' => $propertyType->id,
                        'slab_id' => $newSlab->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                // Update user's primary slab_id to the first property type's slab (for backward compatibility)
                $firstPropertyType = PropertyType::where('is_active', true)->orderBy('name')->first();
                if ($firstPropertyType) {
                    $firstPropertyTypeSlab = DB::table('user_slabs')
                        ->where('user_id', $user->id)
                        ->where('property_type_id', $firstPropertyType->id)
                        ->first();
                    
                    if ($firstPropertyTypeSlab) {
                        $user->update(['slab_id' => $firstPropertyTypeSlab->slab_id]);
                    }
                }
                
                // Create slab upgrade history record
                SlabUpgrade::create([
                    'user_id' => $user->id,
                    'old_slab_id' => $currentSlabId,
                    'new_slab_id' => $newSlab->id,
                    'sale_id' => $sale->id,
                    'total_area_sold' => $totalAreaSold,
                    'notes' => "Upgraded from {$oldSlabName} to {$newSlab->name} for {$propertyType->name} after sale #{$sale->id}",
                    'upgraded_at' => now(),
                ]);
                
                // Log the upgrade
                Log::info("Slab upgraded for user ID: {$user->id} ({$user->name})", [
                    'property_type' => $propertyType->name,
                    'old_slab_id' => $currentSlabId,
                    'old_slab_name' => $oldSlabName,
                    'new_slab_id' => $newSlab->id,
                    'new_slab_name' => $newSlab->name,
                    'triggered_by_sale_id' => $sale->id,
                    'total_area_sold' => $totalAreaSold,
                    'upgraded_at' => now()->toDateTimeString(),
                ]);
            }

        } catch (\Exception $e) {
            // Don't fail commission distribution if slab upgrade fails
            Log::error("Error upgrading slab for user ID: {$user->id}, sale ID: {$sale->id}. Error: " . $e->getMessage());
        }
    }

    /**
     * Calculate total area sold across all property types for a user
     * Used for slab upgrade history tracking
     * 
     * @param User $user The user whose total area sold needs to be calculated
     * @return float Total area sold across all property types
     */
    private function calculateTotalAreaSold(User $user): float
    {
        try {
            $totalAreaSold = 0;
            
            // Get all confirmed sales for this user
            $sales = Sale::where('sold_by_user_id', $user->id)
                ->where('status', 'confirmed')
                ->with(['plot'])
                ->get();

            // Sum up area sold from all sales
            foreach ($sales as $sale) {
                $plot = $sale->plot;
                if ($plot && $plot->size) {
                    $totalAreaSold += (float)($plot->size ?? 0);
                }
            }

            return $totalAreaSold;
        } catch (\Exception $e) {
            Log::error("Error calculating total area sold for user ID: {$user->id}. Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate total area sold for a specific property type (excluding a specific sale)
     * 
     * @param User $user The user
     * @param PropertyType $propertyType The property type
     * @param int|null $excludeSaleId Sale ID to exclude from calculation
     * @return float Total area sold
     */
    /**
     * Get all downline user IDs (team: users referred by this user, and their referrals, recursively).
     * Used to compute total volume = own sales + team sales for slab calculation.
     * Optimized to use batch queries instead of N+1 queries.
     */
    private function getDownlineUserIds(User $user, array $visited = []): array
    {
        // Prevent infinite loops from circular references
        if (in_array($user->id, $visited)) {
            return [];
        }
        $visited[] = $user->id;
        
        $ids = [];
        // Get all direct referrals in one query
        $directReferralIds = User::where('referred_by_user_id', $user->id)
            ->whereNull('deleted_at') // Exclude soft-deleted users
            ->pluck('id')
            ->toArray();
        
        foreach ($directReferralIds as $id) {
            $ids[] = $id;
            // Recursively get their downline (pass visited array to prevent loops)
            $downlineUser = User::find($id);
            if ($downlineUser) {
                $ids = array_merge($ids, $this->getDownlineUserIds($downlineUser, $visited));
            }
        }
        
        return array_unique($ids);
    }

    public function calculateTotalAreaSoldForPropertyType(User $user, PropertyType $propertyType, ?int $excludeSaleId = null, bool $includeTeamVolume = false): float
    {
        try {
            $propertyTypeSlug = strtolower(\Illuminate\Support\Str::slug($propertyType->name));
            $allPropertyTypes = PropertyType::where('is_active', true)->get();
            
            // User IDs to sum sales for: just this user, or user + entire downline (team)
            $userIds = $includeTeamVolume
                ? array_merge([$user->id], $this->getDownlineUserIds($user))
                : [$user->id];
            
            // Get all confirmed sales for these user(s)
            $salesQuery = Sale::whereIn('sold_by_user_id', $userIds)
                ->where('status', 'confirmed')
                ->with(['plot']);
            
            if ($excludeSaleId) {
                $salesQuery->where('id', '!=', $excludeSaleId);
            }
            
            $sales = $salesQuery->get();
            
            $totalAreaSold = 0;
            foreach ($sales as $sale) {
                $plot = $sale->plot;
                if ($plot) {
                    $plotTypeSlug = strtolower(trim($plot->type ?? 'plot'));
                    
                    // Match property type
                    $matches = false;
                    foreach ($allPropertyTypes as $pt) {
                        $nameLower = strtolower($pt->name);
                        $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                        if (($nameLower === $plotTypeSlug || 
                             $slugFromName === $plotTypeSlug ||
                             str_replace(' ', '-', $nameLower) === $plotTypeSlug ||
                             str_replace(' ', '_', $nameLower) === $plotTypeSlug) &&
                            $pt->id === $propertyType->id) {
                            $matches = true;
                            break;
                        }
                    }
                    
                    if ($matches) {
                        $totalAreaSold += (float)($plot->size ?? 0);
                    }
                }
            }
            
            return $totalAreaSold;
        } catch (\Exception $e) {
            Log::error("Error calculating total area sold for property type - User ID: {$user->id}, Property Type: {$propertyType->name}. Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate progressive commission based on volume tiers
     * Each portion of the sale is paid at the slab rate for that volume range
     * 
     * @param User $user The user
     * @param PropertyType $propertyType The property type
     * @param string $propertyTypeSlug Property type slug
     * @param float $totalVolumeBeforeSale Total volume sold before this sale
     * @param float $currentSaleArea Area of current sale
     * @param float $allocatedAmount Allocated amount for commission calculation
     * @return array Commission breakdown with total_commission, breakdown, primary_slab_name, weighted_average_percentage
     */
    public function calculateProgressiveCommission(
        User $user,
        PropertyType $propertyType,
        string $propertyTypeSlug,
        float $totalVolumeBeforeSale,
        float $currentSaleArea,
        float $allocatedAmount
    ): array {
        $breakdown = [];
        $totalCommission = 0;
        $remainingArea = $currentSaleArea;
        $currentVolume = $totalVolumeBeforeSale;
        $primarySlabName = 'Slab1';
        $totalWeightedPercentage = 0;
        
        // Get all applicable slabs ordered by sort_order (lowest to highest)
        $applicableSlabs = Slab::where('is_active', true)
            ->whereHas('propertyTypes', function($query) use ($propertyType) {
                $query->where('property_types.id', $propertyType->id);
            })
            ->orderBy('sort_order')
            ->get();

            
            
        
        if ($applicableSlabs->isEmpty()) {
            Log::warning("No slabs found for property type: {$propertyType->name}, using default Slab1");
            // Fallback: use default Slab1 percentage
            $defaultPercentage = $this->getSlabCommissionPercentage('Slab1', $propertyTypeSlug, $propertyType);
            $commissionPerUnit = ($allocatedAmount * $defaultPercentage / 100);
            $totalCommission = $commissionPerUnit * $currentSaleArea;
            return [
                'total_commission' => $totalCommission,
                'breakdown' => [[
                    'slab_name' => 'Slab1',
                    'volume_range' => "{$totalVolumeBeforeSale} - " . ($totalVolumeBeforeSale + $currentSaleArea),
                    'area_in_tier' => $currentSaleArea,
                    'commission_percentage' => $defaultPercentage,
                    'commission_per_unit' => $commissionPerUnit,
                    'commission' => $totalCommission,
                ]],
                'primary_slab_name' => 'Slab1',
                'weighted_average_percentage' => $defaultPercentage,
            ];
        }
        
        // Process each slab tier
        foreach ($applicableSlabs as $slab) {
            if ($remainingArea <= 0) {
                break; // All area has been allocated
            }
            
            $minTarget = (float)($slab->minimum_target ?? 0);
            $maxTarget = (float)($slab->maximum_target ?? 999999999);
            
            // Calculate how much of current sale falls into this slab tier
            $tierStart = max($minTarget, $currentVolume);
            $tierEnd = min($maxTarget, $currentVolume + $remainingArea);
          
            if ($tierEnd > $tierStart) {
                $areaInTier = $tierEnd - $tierStart;
                
                // Get commission percentage for this slab
                $commissionPercentage = $this->getSlabCommissionPercentage($slab->name, $propertyTypeSlug, $propertyType);
                // Calculate commission per unit: (allocated_amount × percentage / 100)
                $commissionPerUnit = ($allocatedAmount * $commissionPercentage / 100);
                // Calculate commission for this tier: commission_per_unit × area_in_tier
                $tierCommission = $commissionPerUnit * $areaInTier;
                
                $breakdown[] = [
                    'slab_name' => $slab->name,
                    'volume_range' => "{$tierStart} - {$tierEnd}",
                    'area_in_tier' => $areaInTier,
                    'commission_percentage' => $commissionPercentage,
                    'commission_per_unit' => $commissionPerUnit,
                    'commission' => $tierCommission,
                ];
                
                $totalCommission += $tierCommission;
                $totalWeightedPercentage += $commissionPercentage * $areaInTier;
                $remainingArea -= $areaInTier;
                $currentVolume = $tierEnd;
                $primarySlabName = $slab->name; // Track highest slab used

                
            }
        }
      
        // Calculate weighted average percentage
        $weightedAveragePercentage = $currentSaleArea > 0 ? ($totalWeightedPercentage / $currentSaleArea) : 0;
      
        Log::info("Progressive commission calculated - Total: {$totalCommission}, Breakdown: " . json_encode($breakdown));
        
        return [
            'total_commission' => $totalCommission,
            'breakdown' => $breakdown,
            'primary_slab_name' => $primarySlabName,
            'weighted_average_percentage' => $weightedAveragePercentage,
        ];
    }

    /**
     * Distribute referral commissions to parent chain based on allocated amount system
     * 
     * Logic:
     * 1. Referral Pool Per Unit = Allocated Amount - (actual Level1 commission per unit).
     *    When $actualLevel1Commission is provided (e.g. from progressive commission), use it so
     *    pool = allocated - (level1Commission/areaSold). Otherwise use child slab %.
     * 2. Parent Referral Commission = (Parent Slab % - Child Slab %) × Allocated Amount × Sold Volume
     * 3. Continue up chain until pool exhausted or no more parents
     * 
     * @param Sale $sale The sale record
     * @param User $childUser The user who made the sale
     * @param float $allocatedAmount Allocated amount from project (per unit)
     * @param float $areaSold Area sold in this sale
     * @param string $childSlabName Child's slab name
     * @param float $childSlabPercentage Child's slab percentage (used when actualLevel1Commission is null)
     * @param PropertyType $propertyType Property type for slab calculation
     * @param float|null $actualLevel1Commission Total Level1 commission already calculated (e.g. progressive). When set, referral pool = allocated - (this/areaSold).
     * @return array Referral commission distribution array
     */
    private function distributeReferralCommissions(
        Sale $sale,
        User $childUser,
        float $allocatedAmount,
        float $areaSold,
        string $childSlabName,
        float $childSlabPercentage,
        PropertyType $propertyType,
        ?float $actualLevel1Commission = null
    ): array {
        $referralDistribution = [];
        
        // Calculate referral pool per unit.
        // When actual Level1 commission is provided (e.g. progressive commission), use it so the pool
        // equals what's left after paying the direct seller. Otherwise use child slab %.
        if ($actualLevel1Commission !== null && $areaSold > 0) {
            $childCommissionPerUnit = $actualLevel1Commission / $areaSold;
            $referralPoolPerUnit = $allocatedAmount - $childCommissionPerUnit;
            Log::info("Referral pool (using actual Level1 commission) - Level1 total: {$actualLevel1Commission}, area: {$areaSold}, child per unit: {$childCommissionPerUnit}, pool per unit: {$referralPoolPerUnit}");
        } else {
            $childCommissionPerUnit = ($allocatedAmount * $childSlabPercentage / 100);
            $referralPoolPerUnit = $allocatedAmount - $childCommissionPerUnit;
        }
        $remainingPoolPerUnit = $referralPoolPerUnit;
        
        if ($remainingPoolPerUnit <= 0.01) {
            Log::info("Referral pool is zero or negative for sale {$sale->id}, child user {$childUser->id}. Pool per unit: ₹{$referralPoolPerUnit}");
            return $referralDistribution;
        }
        
        // Traverse up the parent chain (no level limit - continues until pool exhausted or no more parents)
        $parent = $childUser->referredBy; // Use referredBy relationship (referred_by_user_id)
        $level = 2; // Start from Level 2 (Level 1 is direct seller)
        $currentChildSlabName = $childSlabName;
        $currentChildSlabPercentage = $childSlabPercentage;
        
        Log::info("Starting referral chain traversal (distribute) - Child: {$childUser->name} (Slab: {$childSlabName} @ {$childSlabPercentage}%), Pool per unit: ₹{$referralPoolPerUnit}, Has parent: " . ($parent ? "Yes ({$parent->name})" : "No"));
        
        // Get the actual child slab object for comparison (to get correct sort_order)
        // Use the calculated slab, not the stored one, to ensure we have the correct sort_order
        // The childSlabName comes from progressive commission which uses calculateCurrentSlabForPropertyType
        // So we need to find the slab with that name that's linked to this property type
        $childSlabObject = Slab::where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($childSlabName)])
            ->whereHas('propertyTypes', function($query) use ($propertyType) {
                $query->where('property_types.id', $propertyType->id);
            })
            ->orderBy('sort_order', 'asc')
            ->first();
        
        // Fallback if not found with property type
        if (!$childSlabObject) {
            $childSlabObject = Slab::where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [strtolower($childSlabName)])
                ->orderBy('sort_order', 'asc')
                ->first();
        }
        
        $currentChildSlabSortOrder = $childSlabObject ? (int)($childSlabObject->sort_order ?? 0) : 0;
        
        if ($currentChildSlabSortOrder == 0 && $childSlabObject) {
            Log::warning("Child slab '{$childSlabName}' has sort_order 0. Slab ID: {$childSlabObject->id}. This may cause comparison issues.");
        }
        
        Log::info("Starting referral commission distribution for sale {$sale->id}", [
            'child_user_id' => $childUser->id,
            'child_user_name' => $childUser->name,
            'child_slab_name' => $childSlabName,
            'child_slab_percentage' => $childSlabPercentage,
            'allocated_amount' => $allocatedAmount,
            'area_sold' => $areaSold,
            'child_commission_per_unit' => $childCommissionPerUnit,
            'referral_pool_per_unit' => $referralPoolPerUnit,
            'has_parent' => $parent ? true : false,
            'parent_id' => $parent ? $parent->id : null,
        ]);
        
        // Loop continues as long as there are parents; pool check is inside commission calculation
        while ($parent) {
            // Refresh parent to get latest slab
            $parent->refresh();
            
            // Calculate parent's current slab for this property type
            // Exclude current sale ID to ensure parent's slab is calculated correctly
            // This prevents the current sale from affecting parent's slab during commission distribution
            $parentSlab = $this->calculateCurrentSlabForPropertyType($parent, $propertyType, 0, $sale->id);
            
            if (!$parentSlab) {
                Log::warning("No slab found for parent user {$parent->id}, property type {$propertyType->name}. Using primary slab for child reference update.");
                // Fix: Still update child reference so chain continues. Use parent's primary slab if available.
                $parentPrimarySlab = $parent->slab;
                if ($parentPrimarySlab) {
                    $currentChildSlabName = $parentPrimarySlab->name;
                    $currentChildSlabPercentage = $this->getSlabCommissionPercentage($currentChildSlabName, strtolower(\Illuminate\Support\Str::slug($propertyType->name)), $propertyType);
                    $currentChildSlabSortOrder = (int)($parentPrimarySlab->sort_order ?? 0);
                    Log::info("Child reference updated at Level {$level} (distribute, no property slab) - Parent: {$parent->name}, using primary slab: {$currentChildSlabName} (sort_order: {$currentChildSlabSortOrder})");
                } else {
                    Log::info("Child reference NOT updated at Level {$level} (distribute) - Parent: {$parent->name} has no slab, keeping previous child: {$currentChildSlabName}");
                }
                $parent = $parent->referredBy;
                $level++;
                continue;
            }
            
            $parentSlabName = $parentSlab->name;
            $parentSlabPercentage = $this->getSlabCommissionPercentage($parentSlabName, strtolower(\Illuminate\Support\Str::slug($propertyType->name)), $propertyType);
            
            // Get sort orders to compare slabs (higher sort_order = higher tier)
            $parentSortOrder = (int)($parentSlab->sort_order ?? 0);
            
            // Use the tracked child slab sort_order (updated after each iteration)
            $childSortOrder = $currentChildSlabSortOrder;
            
            // Log slab comparison details at each level
            Log::info("Level {$level} (distribute) - Comparing Parent: {$parent->name} (Slab: {$parentSlabName}, sort_order: {$parentSortOrder}) vs Child ref: {$currentChildSlabName} (sort_order: {$childSortOrder}), Pool per unit: ₹{$remainingPoolPerUnit}", [
                'parent_id' => $parent->id,
                'parent_slab_name' => $parentSlabName,
                'child_slab_name' => $currentChildSlabName,
                'parent_higher' => $parentSortOrder > $childSortOrder,
                'remaining_pool_per_unit' => $remainingPoolPerUnit,
            ]);
            
            // Check if parent is at HIGHER slab than child; pool check is inside (only award if pool has balance)
            if ($parentSortOrder > $childSortOrder && $remainingPoolPerUnit > 0.01) {
                // Calculate slab difference percentage
                $slabDifferencePercentage = $parentSlabPercentage - $currentChildSlabPercentage;
                
                // Parent Referral Commission = (Parent Slab % - Child Slab %) × Allocated Amount × Sold Volume
                $parentReferralCommissionPerUnit = ($allocatedAmount * $slabDifferencePercentage / 100);
                $parentDeservedCommission = $parentReferralCommissionPerUnit * $areaSold;
                
                // Calculate what parent deserves from pool (per unit): min(commission per unit, remaining pool per unit)
                $parentDeservedFromPoolPerUnit = min($parentReferralCommissionPerUnit, $remainingPoolPerUnit);
                $parentReferralCommission = $parentDeservedFromPoolPerUnit * $areaSold;
                
                // Deduct from pool (in per-unit terms)
                $remainingPoolPerUnit -= $parentDeservedFromPoolPerUnit;
                
                // Add to referral distribution
                $referralDistribution[$level] = [
                    'user_id' => $parent->id,
                    'user_name' => $parent->name,
                    'slab_id' => $parentSlab->id,
                    'slab_name' => $parentSlabName,
                    'commission_amount' => $parentReferralCommission,
                    'level' => $level,
                    'commission_type' => 'referral',
                    'commission_percentage' => $parentSlabPercentage,
                    'allocated_amount' => $allocatedAmount,
                    'area_sold' => $areaSold,
                    'parent_slab_name' => $parentSlabName,
                    'child_slab_name' => $currentChildSlabName,
                    'parent_slab_percentage' => $parentSlabPercentage,
                    'child_slab_percentage' => $currentChildSlabPercentage,
                    'slab_difference_percentage' => $slabDifferencePercentage,
                    'commission_per_unit' => $parentReferralCommissionPerUnit,
                    'deserved_commission' => $parentDeservedCommission,
                    'pool_remaining_per_unit' => $remainingPoolPerUnit,
                ];
                
                // Record referral commission in database
                ReferralCommission::create([
                    'sale_id' => $sale->id,
                    'parent_user_id' => $parent->id,
                    'child_user_id' => $childUser->id,
                    'parent_slab_name' => $parentSlabName,
                    'child_slab_name' => $currentChildSlabName,
                    'parent_slab_percentage' => $parentSlabPercentage,
                    'child_slab_percentage' => $currentChildSlabPercentage,
                    'referral_commission_amount' => $parentReferralCommission,
                    'allocated_amount' => $allocatedAmount,
                    'area_sold' => $areaSold,
                    'level' => $level,
                    'notes' => "Level {$level} referral - Slab diff: {$slabDifferencePercentage}%, Commission per unit: ₹" . number_format($parentReferralCommissionPerUnit, 2) . ", Pool remaining per unit: ₹" . number_format($remainingPoolPerUnit, 2) . ", Deserved: ₹" . number_format($parentDeservedCommission, 2) . ", Actual: ₹" . number_format($parentReferralCommission, 2),
                ]);
                
                Log::info("Referral commission Level {$level} - Parent: {$parent->name} (Slab {$parentSlabName} @ {$parentSlabPercentage}%), Child: {$currentChildSlabName} @ {$currentChildSlabPercentage}%, Commission: ₹{$parentReferralCommission}, Pool Remaining per unit: ₹{$remainingPoolPerUnit}");
            } else {
                // No referral commission - same slab or pool exhausted
                if ($parentSortOrder <= $childSortOrder) {
                    Log::info("No referral commission Level {$level} - Parent Slab {$parentSlabName} (sort_order: {$parentSortOrder}) <= Child Slab {$currentChildSlabName} (sort_order: {$childSortOrder})");
                } else {
                    Log::info("No referral commission Level {$level} - Pool exhausted: ₹{$remainingPoolPerUnit} per unit remaining");
                }
            }
            
            // IMPORTANT: Always update child slab reference after each parent check, even if they didn't qualify
            // The current parent becomes the "child" for the next level
            $currentChildSlabName = $parentSlabName;
            $currentChildSlabPercentage = $parentSlabPercentage;
            $currentChildSlabSortOrder = $parentSortOrder;
            
            Log::info("Child reference updated at Level {$level} (distribute) - Next level will compare against: {$currentChildSlabName} @ {$currentChildSlabPercentage}% (sort_order: {$currentChildSlabSortOrder}), Pool remaining per unit: ₹{$remainingPoolPerUnit}");
            
            // Move to next level parent
            $parent = $parent->referredBy;
            $level++;
            
            if ($parent) {
                Log::info("Next parent found (distribute) - Level {$level}: {$parent->name}, Pool remaining: ₹{$remainingPoolPerUnit}");
            } else {
                Log::info("No more parents in chain (distribute) - Chain ended at level " . ($level - 1) . ", Pool remaining: ₹{$remainingPoolPerUnit}");
            }
        }
        
        Log::info("Referral chain traversal completed (distribute) - Total levels processed: " . ($level - 2) . ", Final pool remaining per unit: ₹{$remainingPoolPerUnit}");
        
        // Store remaining pool info in distribution array for handling in creditCommissionsToWallets
        // This ensures it's processed within the same transaction
        if ($remainingPoolPerUnit > 0.01) {
            $remainingPoolAmount = $remainingPoolPerUnit * $areaSold;
            $adminUser = User::where('user_type', 'admin')->first();
            
            if ($adminUser) {
                $referralDistribution['_remaining_pool'] = [
                    'user_id' => $adminUser->id,
                    'amount' => $remainingPoolAmount,
                    'per_unit' => $remainingPoolPerUnit,
                    'area_sold' => $areaSold,
                    'sale_id' => $sale->id,
                ];
                Log::info("Remaining referral pool ₹{$remainingPoolAmount} will be sent to admin wallet for sale {$sale->id}. Pool per unit: ₹{$remainingPoolPerUnit}, Area sold: {$areaSold}");
            } else {
                Log::warning("No admin user found to receive remaining referral pool for sale {$sale->id}. Remaining pool: ₹{$remainingPoolAmount}");
            }
        }
        
        return $referralDistribution;
    }
}

