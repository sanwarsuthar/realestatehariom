<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slab;
use App\Models\PropertyType;
use App\Models\Setting;
use App\Models\Sale;
use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlabController extends Controller
{
    /**
     * Get user's slab details with all slabs, progress, and commission rates
     */
    public function getUserSlabs(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get all property types
            $propertyTypes = PropertyType::where('is_active', true)
                ->with('measurementUnit')
                ->orderBy('name')
                ->get();

            // Get all active slabs ordered by sort_order
            $allSlabs = Slab::where('is_active', true)
                ->with(['propertyTypes', 'measurementUnit'])
                ->orderBy('sort_order')
                ->get();

            // Get property-type-based commissions
            $propertyTypeCommissionsJson = Setting::get('property_type_commissions', '{}');
            $propertyTypeCommissions = json_decode($propertyTypeCommissionsJson, true) ?? [];

            // Get user's sales grouped by property type to calculate progress
            $userSales = Sale::where('sold_by_user_id', $user->id)
                ->where('status', 'confirmed')
                ->with(['plot.project'])
                ->get();

            // Calculate total area sold per property type
            $areaSoldByPropertyType = [];
            foreach ($userSales as $sale) {
                $plot = $sale->plot;
                if ($plot) {
                    $propertyTypeSlug = strtolower($plot->type ?? 'plot');
                    
                    // Find property type by slug
                    $propertyType = $propertyTypes->first(function($pt) use ($propertyTypeSlug) {
                        $ptSlug = strtolower(\Illuminate\Support\Str::slug($pt->name));
                        return $ptSlug === $propertyTypeSlug || strtolower($pt->name) === $propertyTypeSlug;
                    });
                    
                    if ($propertyType) {
                        $propertyTypeName = $propertyType->name;
                        if (!isset($areaSoldByPropertyType[$propertyTypeName])) {
                            $areaSoldByPropertyType[$propertyTypeName] = 0;
                        }
                        $areaSoldByPropertyType[$propertyTypeName] += (float)($plot->size ?? 0);
                    }
                }
            }

            // Build response with slabs for each property type
            $slabsByPropertyType = [];
            
            foreach ($propertyTypes as $propertyType) {
                $propertyTypeName = $propertyType->name;
                $areaSold = $areaSoldByPropertyType[$propertyTypeName] ?? 0;
                
                // Get slabs that apply to this property type
                $applicableSlabs = $allSlabs->filter(function($slab) use ($propertyType) {
                    return $slab->propertyTypes->contains('id', $propertyType->id);
                })->values();

                // Find current slab for this property type
                $currentSlab = null;
                $currentSlabIndex = -1;
                
                foreach ($applicableSlabs as $index => $slab) {
                    $minTarget = (float)($slab->minimum_target ?? 0);
                    $maxTarget = (float)($slab->maximum_target ?? 999999999);
                    
                    if ($areaSold >= $minTarget && $areaSold < $maxTarget) {
                        $currentSlab = $slab;
                        $currentSlabIndex = $index;
                        break;
                    }
                }
                
                // If no current slab found, use the first one (lowest sort_order)
                if (!$currentSlab && $applicableSlabs->isNotEmpty()) {
                    $currentSlab = $applicableSlabs->first();
                    $currentSlabIndex = 0;
                }

                // Always include property type in response, even if no slabs assigned
                // This ensures all property types are shown in the app
                // If no slabs assigned, current_slab will be null and app can show default

                // Calculate progress to next slab
                $nextSlab = null;
                $remainingTarget = 0;
                $progressPercentage = 0;
                
                if ($currentSlab && $currentSlabIndex < $applicableSlabs->count() - 1) {
                    $nextSlab = $applicableSlabs[$currentSlabIndex + 1];
                    $nextMinTarget = (float)($nextSlab->minimum_target ?? 0);
                    $currentMaxTarget = (float)($currentSlab->maximum_target ?? $nextMinTarget);
                    
                    $remainingTarget = max(0, $nextMinTarget - $areaSold);
                    
                    if ($currentMaxTarget > 0) {
                        $progressPercentage = min(100, ($areaSold / $currentMaxTarget) * 100);
                    }
                } else {
                    // User is at highest slab
                    $progressPercentage = 100;
                }

                // Build commission rates for this property type
                $commissionRates = [];
                foreach ($applicableSlabs as $slab) {
                    $slabName = $slab->name;
                    $commission = $propertyTypeCommissions[$propertyTypeName][$slabName] ?? null;
                    
                    if ($commission !== null) {
                        $commissionRates[$slabName] = (float)$commission;
                    }
                }

                $slabsByPropertyType[] = [
                    'property_type' => [
                        'id' => $propertyType->id,
                        'name' => $propertyTypeName,
                        'slug' => \Illuminate\Support\Str::slug($propertyTypeName),
                        'measurement_unit' => $propertyType->measurementUnit ? [
                            'id' => $propertyType->measurementUnit->id,
                            'name' => $propertyType->measurementUnit->name,
                        ] : null,
                    ],
                    'current_slab' => $currentSlab ? [
                        'id' => $currentSlab->id,
                        'name' => $currentSlab->name,
                        'minimum_target' => (float)($currentSlab->minimum_target ?? 0),
                        'maximum_target' => (float)($currentSlab->maximum_target ?? 0),
                        'sort_order' => $currentSlab->sort_order ?? 0,
                        'color_code' => $currentSlab->color_code ?? '#9333EA',
                    ] : null,
                    'next_slab' => $nextSlab ? [
                        'id' => $nextSlab->id,
                        'name' => $nextSlab->name,
                        'minimum_target' => (float)($nextSlab->minimum_target ?? 0),
                        'maximum_target' => (float)($nextSlab->maximum_target ?? 0),
                        'sort_order' => $nextSlab->sort_order ?? 0,
                        'color_code' => $nextSlab->color_code ?? '#9333EA',
                    ] : null,
                    'area_sold' => $areaSold,
                    'remaining_target' => $remainingTarget,
                    'progress_percentage' => $progressPercentage,
                    'all_slabs' => $applicableSlabs->map(function($slab) {
                        return [
                            'id' => $slab->id,
                            'name' => $slab->name,
                            'minimum_target' => (float)($slab->minimum_target ?? 0),
                            'maximum_target' => (float)($slab->maximum_target ?? 0),
                            'sort_order' => $slab->sort_order ?? 0,
                            'color_code' => $slab->color_code ?? '#9333EA',
                            'bonus_percentage' => (float)($slab->bonus_percentage ?? 0),
                        ];
                    })->values()->all(),
                    'commission_rates' => $commissionRates,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'current_slab' => $user->slab ? $user->slab->name : 'Slab1',
                    ],
                    'slabs_by_property_type' => $slabsByPropertyType,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch slab details: ' . $e->getMessage()
            ], 500);
        }
    }
}

