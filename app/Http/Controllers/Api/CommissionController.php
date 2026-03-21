<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Plot;
use App\Models\Sale;
use App\Services\CommissionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CommissionController extends Controller
{
    /**
     * Get commission summary data
     */
    public function getCommissionData(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get total commission earned (from user table)
            $totalCommission = (float) ($user->total_commission_earned ?? 0);
            
            // Get monthly commission (current month) - using IST timezone
            $istTimezone = new \DateTimeZone('Asia/Kolkata');
            $monthStart = Carbon::now($istTimezone)->startOfMonth();
            $monthEnd = Carbon::now($istTimezone)->endOfMonth();
            
            $monthlyCommission = Transaction::where('user_id', $user->id)
                ->where('type', 'commission')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');
            
            // Get pending commission (from pending transactions)
            $pendingCommission = Transaction::where('user_id', $user->id)
                ->where('type', 'commission')
                ->where('status', 'pending')
                ->sum('amount');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_commission' => (float) $totalCommission,
                    'monthly_commission' => (float) $monthlyCommission,
                    'pending_commission' => (float) $pendingCommission,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commission data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get commission history
     */
    public function getCommissionHistory(Request $request)
    {
        try {
            $user = $request->user();
            
            // Set timezone to IST
            $istTimezone = new \DateTimeZone('Asia/Kolkata');
            
            $transactions = Transaction::where('user_id', $user->id)
                ->where('type', 'commission')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($txn) use ($istTimezone, $user) {
                    // Convert to IST
                    $istDate = \Carbon\Carbon::parse($txn->created_at)->setTimezone($istTimezone);
                    
                    // Parse metadata if available
                    $metadata = [];
                    if ($txn->metadata) {
                        $metadata = is_string($txn->metadata) ? json_decode($txn->metadata, true) : $txn->metadata;
                    }
                    
                    // If metadata is missing critical fields, try to recalculate from sale/plot
                    $areaSold = isset($metadata['area_sold']) ? (float)$metadata['area_sold'] : null;
                    $fixedAmountPerUnit = isset($metadata['fixed_amount_per_unit']) ? (float)$metadata['fixed_amount_per_unit'] : null;
                    $slabName = $metadata['slab_name'] ?? null;
                    $plotType = $metadata['plot_type'] ?? null;
                    $plotId = $metadata['plot_id'] ?? null;
                    
                    // If critical fields are missing, try to recalculate using centralized service
                    // IMPORTANT: Use the slab at the TIME OF SALE (from metadata), not current slab
                    if (($areaSold === null || $fixedAmountPerUnit === null || $slabName === null) && $plotId) {
                        try {
                            $plot = Plot::with('project')->find($plotId);
                            if ($plot) {
                                $plotTypeSlug = strtolower(trim($plot->type ?? ($plotType ?? 'plot')));
                                $plotSize = (float)($plot->size ?? 0);
                                
                                // Get PropertyType model
                                $propertyTypeModel = \App\Models\PropertyType::where('is_active', true)
                                    ->with('measurementUnit')
                                    ->get()
                                    ->first(function ($pt) use ($plotTypeSlug) {
                                        $nameLower = strtolower($pt->name);
                                        $slugFromName = strtolower(\Illuminate\Support\Str::slug($pt->name));
                                        return $nameLower === $plotTypeSlug || 
                                               $slugFromName === $plotTypeSlug ||
                                               str_replace(' ', '-', $nameLower) === $plotTypeSlug ||
                                               str_replace(' ', '_', $nameLower) === $plotTypeSlug;
                                    });
                                
                                if ($propertyTypeModel && $plotSize > 0) {
                                    // Try to get slab from sale record (slab at time of sale)
                                    $saleId = $metadata['sale_id'] ?? $txn->reference_id;
                                    $sale = null;
                                    if ($saleId) {
                                        $sale = Sale::with('soldByUser.slab')->find($saleId);
                                    }
                                    
                                    // Use slab from sale time if available, otherwise use current slab as fallback
                                    $slabAtSaleTime = null;
                                    if ($sale && $sale->soldByUser && $sale->soldByUser->slab) {
                                        // Get the slab that was used at the time of sale
                                        // Check commission_distribution in sale record
                                        $commissionDistribution = $sale->commission_distribution ?? [];
                                        if (isset($commissionDistribution[1]['slab_name'])) {
                                            $slabAtSaleTime = $commissionDistribution[1]['slab_name'];
                                        } else {
                                            // Fallback: use the slab from the user at sale time
                                            // But we don't have historical slab, so use metadata slab_name if available
                                            $slabAtSaleTime = $slabName ?? $sale->soldByUser->slab->name;
                                        }
                                    } else {
                                        // Fallback: use slab from metadata (slab at time of sale)
                                        $slabAtSaleTime = $slabName ?? ($user->slab->name ?? null);
                                    }
                                    
                                    if ($slabAtSaleTime) {
                                        // Get allocated amount from sale's commission distribution metadata
                                        $allocatedAmount = isset($metadata['allocated_amount']) ? (float)$metadata['allocated_amount'] : null;
                                        $commissionPercentage = isset($metadata['commission_percentage']) ? (float)$metadata['commission_percentage'] : null;
                                        
                                        // If not in metadata, try to get from project
                                        if ($allocatedAmount === null || $allocatedAmount <= 0) {
                                            $project = $plot->project;
                                            $allocatedAmountConfig = $project->allocated_amount_config ?? [];
                                            $propertyTypeConfig = $allocatedAmountConfig[$propertyTypeModel->name] ?? null;
                                            
                                            if ($propertyTypeConfig) {
                                                $configType = $propertyTypeConfig['type'] ?? 'fixed';
                                                $configValue = (float)($propertyTypeConfig['value'] ?? 0);
                                                
                                                if ($configType === 'fixed') {
                                                    $allocatedAmount = $configValue;
                                                } elseif ($configType === 'percentage') {
                                                    $propertyRatePerUnit = (float)($plot->price_per_unit ?? 0);
                                                    if ($propertyRatePerUnit <= 0) {
                                                        $propertyRatePerUnit = (float)($project->price_per_sqft ?? 0);
                                                    }
                                                    
                                                    if ($propertyRatePerUnit > 0 && $configValue > 0) {
                                                        $allocatedAmount = ($propertyRatePerUnit * $configValue / 100);
                                                    }
                                                }
                                            } else {
                                                $allocatedAmount = (float)($project->allocated_amount ?? 0);
                                            }
                                        }
                                        
                                        // Get commission percentage if not in metadata
                                        if ($commissionPercentage === null || $commissionPercentage <= 0) {
                                        $commissionService = new CommissionDistributionService();
                                            $commissionPercentage = $commissionService->getSlabCommissionPercentage(
                                            $slabAtSaleTime,
                                            $plotTypeSlug,
                                            $propertyTypeModel
                                        );
                                        }
                                        
                                        // Calculate commission per unit for display
                                        $fixedAmountPerUnit = $allocatedAmount > 0 && $commissionPercentage > 0 
                                            ? ($allocatedAmount * $commissionPercentage / 100) 
                                            : 0;
                                        
                                        // Update area_sold if missing
                                        $areaSold = $areaSold ?? $plotSize;
                                        
                                        // Update metadata with values from time of sale
                                        $metadata['area_sold'] = $areaSold;
                                        $metadata['allocated_amount'] = $allocatedAmount;
                                        $metadata['commission_percentage'] = $commissionPercentage;
                                        $metadata['fixed_amount_per_unit'] = $fixedAmountPerUnit; // For backward compatibility
                                        $metadata['slab_name'] = $slabAtSaleTime; // Slab at time of sale
                                        $metadata['measurement_unit'] = $propertyTypeModel->measurementUnit->name ?? 'unit';
                                        $metadata['measurement_unit_symbol'] = $propertyTypeModel->measurementUnit->symbol ?? 'sqft';
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // If recalculation fails, continue with existing metadata
                            \Illuminate\Support\Facades\Log::warning("Failed to recalculate commission for transaction {$txn->id}: " . $e->getMessage());
                        }
                    }
                    
                    return [
                        'id' => $txn->id,
                        'transaction_id' => $txn->transaction_id,
                        'amount' => (float) $txn->amount, // Use stored amount (calculated at time of sale)
                        'status' => $txn->status,
                        'description' => $txn->description,
                        'created_at' => $istDate->format('Y-m-d H:i:s'),
                        'date' => $istDate->format('d M Y'),
                        'time' => $istDate->format('h:i A'),
                        'date_time' => $istDate->format('d M Y, h:i A'),
                        // Full details from metadata
                        'project_name' => $metadata['project_name'] ?? null,
                        'project_location' => $metadata['project_location'] ?? null,
                        'plot_number' => $metadata['plot_number'] ?? null,
                        'plot_type' => $metadata['plot_type'] ?? $plotType,
                        'level' => $metadata['level'] ?? null,
                        'booking_amount' => isset($metadata['booking_amount']) ? (float)$metadata['booking_amount'] : null,
                        'booking_date' => $metadata['booking_date'] ?? null,
                        'customer_name' => $metadata['customer_name'] ?? null,
                        'sale_id' => $metadata['sale_id'] ?? $txn->reference_id,
                        'source' => $metadata['source'] ?? null, // 'direct' or 'referral'
                        'from_user_name' => $metadata['from_user_name'] ?? null, // User who made the booking (for referral commissions)
                        // Calculation details (using slab at TIME OF SALE)
                        'price_per_sqft' => isset($metadata['price_per_sqft']) ? (float)$metadata['price_per_sqft'] : null,
                        'price_per_unit' => isset($metadata['price_per_unit']) ? (float)$metadata['price_per_unit'] : null,
                        'square_feet_sold' => isset($metadata['square_feet_sold']) ? (float)$metadata['square_feet_sold'] : null,
                        'area_sold' => $areaSold,
                        'plot_price' => isset($metadata['plot_price']) ? (float)$metadata['plot_price'] : null,
                        'allocated_amount' => isset($metadata['allocated_amount']) ? (float)$metadata['allocated_amount'] : null,
                        'commission_percentage' => isset($metadata['commission_percentage']) ? (float)$metadata['commission_percentage'] : null,
                        'fixed_amount_per_unit' => $fixedAmountPerUnit, // For backward compatibility
                        'measurement_unit' => $metadata['measurement_unit'] ?? null,
                        'measurement_unit_symbol' => $metadata['measurement_unit_symbol'] ?? null,
                        'slab_commission_percent' => isset($metadata['commission_percentage']) ? (float)$metadata['commission_percentage'] : (isset($metadata['slab_commission_percent']) ? (float)$metadata['slab_commission_percent'] : null), // Use commission_percentage if available
                        'referral_percent' => isset($metadata['referral_percent']) ? (float)$metadata['referral_percent'] : null,
                        'level1_commission' => isset($metadata['level1_commission']) ? (float)$metadata['level1_commission'] : null,
                        'slab_name' => $slabName, // Slab at time of sale (from metadata)
                        // Progressive commission breakdown
                        'progressive_breakdown' => $metadata['progressive_breakdown'] ?? null,
                        'total_volume_before_sale' => isset($metadata['total_volume_before_sale']) ? (float)$metadata['total_volume_before_sale'] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch commission history: ' . $e->getMessage()
            ], 500);
        }
    }
}

