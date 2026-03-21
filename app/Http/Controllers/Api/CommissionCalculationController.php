<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plot;
use App\Services\CommissionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommissionCalculationController extends Controller
{
    /**
     * Calculate broker commission for a given plot and user
     * This is a centralized endpoint to ensure consistent commission calculation
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateCommission(Request $request)
    {
        try {
            $request->validate([
                'plot_id' => 'required|exists:plots,id',
                'user_id' => 'nullable|exists:users,id', // Optional, defaults to authenticated user
            ]);

            $plotId = $request->input('plot_id');
            $userId = $request->input('user_id') ?? $request->user()?->id;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required',
                ], 400);
            }

            $plot = Plot::with(['project', 'propertyType.measurementUnit'])->findOrFail($plotId);
            $user = User::with('slab')->findOrFail($userId);

            $plotSize = (float)($plot->size ?? 0);
            $pricePerUnit = (float)($plot->price_per_unit ?? 0);
            $totalPlotValue = $plotSize > 0 && $pricePerUnit > 0 ? $plotSize * $pricePerUnit : 0;

            // Get user's current slab
            $userSlab = $user->slab;
            if (!$userSlab) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have an assigned slab',
                    'data' => [
                        'broker_name' => $user->name,
                        'broker_slab_name' => 'N/A',
                        'property_type' => $plot->type ?? 'N/A',
                        'property_size' => $plotSize,
                        'measurement_unit' => $plot->propertyType->measurementUnit->symbol ?? 'sqft',
                        'fixed_amount_per_unit' => 0,
                        'broker_commission' => 0,
                        'total_plot_value' => $totalPlotValue,
                    ],
                ]);
            }

            $brokerSlabName = $userSlab->name;
            $plotTypeSlug = strtolower(trim($plot->type ?? 'plot'));

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

            if (!$propertyTypeModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property type not found',
                    'data' => [
                        'broker_name' => $user->name,
                        'broker_slab_name' => $brokerSlabName,
                        'property_type' => $plot->type ?? 'N/A',
                        'property_size' => $plotSize,
                        'measurement_unit' => 'sqft',
                        'fixed_amount_per_unit' => 0,
                        'broker_commission' => 0,
                        'total_plot_value' => $totalPlotValue,
                    ],
                ]);
            }

            $propertyTypeName = $propertyTypeModel->name;
            $measurementUnit = $propertyTypeModel->measurementUnit->symbol ?? 'sqft';

            // Get allocated amount from project config (per property type)
            $project = $plot->project;
            $allocatedAmountConfig = $project->allocated_amount_config ?? [];
            $propertyTypeConfig = $allocatedAmountConfig[$propertyTypeName] ?? null;
            
            // Calculate allocated amount based on config
            $allocatedAmount = 0;
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
                // Fallback to old allocated_amount field (for backward compatibility)
                $allocatedAmount = (float)($project->allocated_amount ?? 0);
            }
            
            if ($allocatedAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allocated amount not configured for this property type',
                    'data' => [
                        'broker_name' => $user->name,
                        'broker_slab_name' => $brokerSlabName,
                        'property_type' => $propertyTypeName,
                        'property_size' => $plotSize,
                        'measurement_unit' => $measurementUnit,
                        'price_per_unit' => $pricePerUnit,
                        'total_plot_value' => $totalPlotValue,
                        'allocated_amount' => 0,
                        'broker_commission' => 0,
                    ],
                ]);
            }

            // Calculate progressive commission (based on total volume before this sale)
            $commissionService = new CommissionDistributionService();
            
            // Get total volume sold before this sale (for this property type)
            // Include team volume (own sales + team sales) for accurate slab calculation
            $totalVolumeBeforeSale = $commissionService->calculateTotalAreaSoldForPropertyType($user, $propertyTypeModel, null, true);
            
            // Calculate progressive commission breakdown with allocated amount
            $progressiveCommission = $commissionService->calculateProgressiveCommission(
                $user,
                $propertyTypeModel,
                $plotTypeSlug,
                $totalVolumeBeforeSale,
                $plotSize,
                $allocatedAmount
            );
            
            $brokerCommission = $progressiveCommission['total_commission'] ?? 0;
            $progressiveBreakdown = $progressiveCommission['breakdown'] ?? [];
            $weightedAveragePercentage = $progressiveCommission['weighted_average_percentage'] ?? 0;

            return response()->json([
                'success' => true,
                'message' => 'Commission calculated successfully',
                'data' => [
                    'broker_name' => $user->name,
                    'broker_slab_name' => $brokerSlabName,
                    'property_type' => $propertyTypeName,
                    'property_size' => $plotSize,
                    'measurement_unit' => $measurementUnit,
                    'price_per_unit' => $pricePerUnit,
                    'total_plot_value' => $totalPlotValue,
                    'allocated_amount' => $allocatedAmount,
                    'commission_percentage' => $weightedAveragePercentage, // Weighted average percentage
                    'broker_commission' => $brokerCommission,
                    'formula' => "Commission = (Allocated Amount × Slab %) × Area Sold",
                    'total_volume_before_sale' => $totalVolumeBeforeSale,
                    'progressive_breakdown' => $progressiveBreakdown,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Commission calculation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate commission: ' . $e->getMessage(),
            ], 500);
        }
    }
}

