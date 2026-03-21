<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Plot;
use App\Models\Sale;
use App\Services\CommissionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Get all active projects with plots count
     */
    public function index()
    {
        try {
            $request = request();
            $baseUrl = $request->getSchemeAndHttpHost();
            
            // For production, ALWAYS use: https://superadmin.shrihariomgroup.com/
            if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                $baseUrl = 'https://shrihariomgroup.com/superadmin';
            }
            $projects = Project::where('is_active', true)
                ->withCount(['plots' => function($query) {
                    $query->where('status', 'available');
                }])
                ->with(['plots' => function($query) {
                    $query->where('is_active', true)->select('id', 'project_id', 'minimum_booking_amount');
                }])
                ->get()
                ->map(function($project) use ($baseUrl) {
                    // Convert image URLs to full URLs (for mobile app compatibility)
                    $images = $project->images ?? [];
                    if (is_array($images)) {
                        $images = array_map(function($imageUrl) use ($baseUrl) {
                            if (empty($imageUrl)) return $imageUrl;
                            
                            // If URL is relative (starts with /storage), make it absolute
                            // Convert /storage/projects/... to https://superadmin.shrihariomgroup.com/storage/app/public/projects/...
                            if (strpos($imageUrl, '/storage/') === 0) {
                                if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                                    // Production: always use shrihariomgroup.com/superadmin
                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($imageUrl, 8);
                                } else {
                                    // Local: use standard /storage/ path
                                    return rtrim($baseUrl, '/') . $imageUrl;
                                }
                            }
                            
                            // If URL contains localhost or 127.0.0.1, replace with current host
                            if (strpos($imageUrl, 'localhost') !== false || strpos($imageUrl, '127.0.0.1') !== false) {
                                $parsedUrl = parse_url($imageUrl);
                                $path = $parsedUrl['path'] ?? '';
                                if (strpos($path, '/storage/') === 0) {
                                    if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                                        return 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($path, 8);
                                    } else {
                                        return rtrim($baseUrl, '/') . $path;
                                    }
                                }
                                return rtrim($baseUrl, '/') . $path;
                            }
                            
                            // If URL is already absolute, ALWAYS convert to shrihariomgroup.com/superadmin format
                            if (strpos($imageUrl, 'http') === 0) {
                                // Extract file path from any format
                                $filePath = '';
                                
                                // Handle /public/storage/ format
                                if (strpos($imageUrl, '/public/storage/') !== false) {
                                    $pathParts = explode('/public/storage/', $imageUrl, 2);
                                    $filePath = $pathParts[1] ?? '';
                                }
                                // Handle /storage/ format
                                elseif (strpos($imageUrl, '/storage/') !== false) {
                                    $pathParts = explode('/storage/', $imageUrl, 2);
                                    $filePath = $pathParts[1] ?? '';
                                }
                                
                                // If we found a file path, ALWAYS return shrihariomgroup.com/superadmin format
                                if (!empty($filePath)) {
                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                                }
                                
                                return $imageUrl;
                            }
                            
                            // Return as-is if already a valid absolute URL
                            return $imageUrl;
                        }, $images);
                    }
                    
                    // Use project's booking amount (set at project creation)
                    $effectiveMinBooking = (float)($project->minimum_booking_amount ?? 0);
                    
                    // Get property types with rates and units info
                    $propertyTypesData = [];
                    $allPlots = $project->plots()->where('is_active', true)->get();
                    
                    // Get all active property types
                    $propertyTypes = \App\Models\PropertyType::where('is_active', true)
                        ->with('measurementUnit')
                        ->orderBy('name')
                        ->get();
                    
                    foreach ($propertyTypes as $propertyType) {
                        $propertyTypeName = $propertyType->name;
                        $propertyTypeSlug = strtolower(\Illuminate\Support\Str::slug($propertyTypeName));
                        
                        // Filter plots by property type (match by type field)
                        $typePlots = $allPlots->filter(function($plot) use ($propertyTypeSlug, $propertyTypeName) {
                            $plotType = strtolower($plot->type ?? '');
                            $plotTypeSlug = strtolower(\Illuminate\Support\Str::slug($plotType));
                            return $plotTypeSlug === $propertyTypeSlug || 
                                   strtolower($plotType) === strtolower($propertyTypeName);
                        });
                        
                        if ($typePlots->isEmpty()) {
                            continue; // Skip property types with no plots
                        }
                        
                        // Calculate average rate per unit for this property type
                        $rates = $typePlots->pluck('price_per_unit')->filter(function($rate) {
                            return $rate !== null && $rate > 0;
                        });
                        
                        $avgRate = $rates->isNotEmpty() ? $rates->avg() : 0;
                        $minRate = $rates->isNotEmpty() ? $rates->min() : 0;
                        $maxRate = $rates->isNotEmpty() ? $rates->max() : 0;
                        
                        // Count available and sold units
                        $availableUnits = $typePlots->where('status', 'available')->count();
                        $soldUnits = $typePlots->where('status', 'sold')->count();
                        $totalUnits = $typePlots->count();
                        
                        // Get measurement unit
                        $measurementUnit = $propertyType->measurementUnit;
                        $unitSymbol = $measurementUnit ? ($measurementUnit->symbol ?? 'sqft') : 'sqft';
                        
                        $propertyTypesData[] = [
                            'name' => $propertyTypeName,
                            'slug' => $propertyTypeSlug,
                            'rate_per_unit' => (float)$avgRate,
                            'min_rate' => (float)$minRate,
                            'max_rate' => (float)$maxRate,
                            'unit_symbol' => $unitSymbol,
                            'available_units' => $availableUnits,
                            'sold_units' => $soldUnits,
                            'total_units' => $totalUnits,
                        ];
                    }
                    
                    return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'location' => $project->location,
                    'city' => $project->city,
                    'state' => $project->state,
                    'type' => $project->type,
                    'commission_per_slab' => $project->commission_per_slab ?? [],
                    'minimum_booking_amount' => $effectiveMinBooking, // Use project's booking amount
                    'price_per_sqft' => (float)($project->price_per_sqft ?? 0), // Keep for backward compatibility
                    'plot_size' => (float)($project->plot_size ?? 0), // Keep for backward compatibility
                    'facilities' => $project->facilities ?? [],
                    'images' => $images,
                    'property_types' => $propertyTypesData, // New: Property type-specific rates and units
                    'floor_plan_pdf' => $project->floor_plan_pdf ? (
                        strpos($project->floor_plan_pdf, 'http') === 0 
                            ? (function($pdfUrl) {
                                // Extract file path from any format
                                $filePath = '';
                                if (strpos($pdfUrl, '/public/storage/') !== false) {
                                    $parts = explode('/public/storage/', $pdfUrl, 2);
                                    $filePath = $parts[1] ?? '';
                                } elseif (strpos($pdfUrl, '/storage/') !== false) {
                                    $parts = explode('/storage/', $pdfUrl, 2);
                                    $filePath = $parts[1] ?? '';
                                }
                                
                                if (!empty($filePath)) {
                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                                }
                                
                                return $pdfUrl;
                            })($project->floor_plan_pdf)
                            : (strpos($baseUrl, 'shrihariomgroup.com') !== false
                                ? (strpos($project->floor_plan_pdf, '/storage/') === 0
                                    ? 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($project->floor_plan_pdf, 8)
                                    : 'https://superadmin.shrihariomgroup.com/storage/app/public/' . ltrim($project->floor_plan_pdf, '/'))
                                : rtrim($baseUrl, '/') . $project->floor_plan_pdf)
                    ) : null,
                    'status' => $project->status,
                    'available_units' => $project->plots_count,
                    'total_units' => $project->plots()->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $projects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project details with all plots/villas
     */
    public function show($id)
    {
        try {
            $request = request();
            $baseUrl = $request->getSchemeAndHttpHost();
            
            // For production, ALWAYS use: https://superadmin.shrihariomgroup.com/
            if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                $baseUrl = 'https://shrihariomgroup.com/superadmin';
            }
            $project = Project::select([
                'id', 'name', 'description', 'location', 'city', 'state', 'pincode',
                'type', 'commission_per_slab', 'minimum_booking_amount', 'price_per_sqft', 'plot_size',
                'facilities', 'images', 'videos', 'floor_plan_pdf', 'status', 'latitude', 'longitude'
            ])->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            // Convert image URLs to full URLs (for mobile app compatibility)
            $images = $project->images ?? [];
            if (is_array($images)) {
                $images = array_map(function($imageUrl) use ($baseUrl) {
                    if (empty($imageUrl)) return $imageUrl;
                    
                    // If URL is relative (starts with /storage), make it absolute
                    // Convert /storage/projects/... to https://superadmin.shrihariomgroup.com/storage/app/public/projects/...
                    if (strpos($imageUrl, '/storage/') === 0) {
                        if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                            // Production: always use shrihariomgroup.com/superadmin
                            return 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($imageUrl, 8);
                        } else {
                            // Local: use standard /storage/ path
                            return rtrim($baseUrl, '/') . $imageUrl;
                        }
                    }
                    
                    // If URL contains localhost or 127.0.0.1, replace with current host
                    if (strpos($imageUrl, 'localhost') !== false || strpos($imageUrl, '127.0.0.1') !== false) {
                        $parsedUrl = parse_url($imageUrl);
                        $path = $parsedUrl['path'] ?? '';
                        if (strpos($path, '/storage/') === 0) {
                            if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                                return 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($path, 8);
                            } else {
                                return rtrim($baseUrl, '/') . $path;
                            }
                        }
                        return rtrim($baseUrl, '/') . $path;
                    }
                    
                    // If URL is already absolute, ALWAYS convert to shrihariomgroup.com/superadmin format
                    if (strpos($imageUrl, 'http') === 0) {
                        // Extract file path from any format
                        $filePath = '';
                        
                        // Handle /public/storage/ format
                        if (strpos($imageUrl, '/public/storage/') !== false) {
                            $pathParts = explode('/public/storage/', $imageUrl, 2);
                            $filePath = $pathParts[1] ?? '';
                        }
                        // Handle /storage/ format
                        elseif (strpos($imageUrl, '/storage/') !== false) {
                            $pathParts = explode('/storage/', $imageUrl, 2);
                            $filePath = $pathParts[1] ?? '';
                        }
                        
                        // If we found a file path, ALWAYS return shrihariomgroup.com/superadmin format
                        if (!empty($filePath)) {
                            return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                        }
                        
                        return $imageUrl;
                    }
                    
                    // Return as-is if already a valid absolute URL
                    return $imageUrl;
                }, $images);
            }

            // Optimize: Only fetch necessary fields for plots
            // Use project's booking amount for all plots
            $projectBookingAmount = (float)($project->minimum_booking_amount ?? 0);
            
            $plots = Plot::where('project_id', $id)
                ->where('is_active', true)
                ->select(['id', 'plot_number', 'type', 'size', 'price_per_unit', 'status', 'grid_batch_id', 'grid_batch_name'])
                ->orderBy('grid_batch_id')
                ->orderBy('plot_number')
                ->get()
                ->map(function($plot) use ($projectBookingAmount) {
                    return [
                        'id' => $plot->id,
                        'plot_number' => $plot->plot_number,
                        'type' => $plot->type,
                        'size' => (float)$plot->size,
                        'price_per_unit' => (float)($plot->price_per_unit ?? 0),
                        'minimum_booking_amount' => $projectBookingAmount, // Use project's booking amount
                        'status' => $plot->status,
                        'grid_batch_id' => $plot->grid_batch_id,
                        'grid_batch_name' => $plot->grid_batch_name ?? 'Default Grid',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'location' => $project->location,
                    'city' => $project->city,
                    'state' => $project->state,
                    'pincode' => $project->pincode,
                    'type' => $project->type,
                    'commission_per_slab' => $project->commission_per_slab ?? [],
                    // Use project's booking amount (set at project creation)
                    'minimum_booking_amount' => (float)($project->minimum_booking_amount ?? 0),
                    'price_per_sqft' => (float)($project->price_per_sqft ?? 0), // Keep for backward compatibility
                    'plot_size' => (float)($project->plot_size ?? 0), // Keep for backward compatibility
                    'facilities' => $project->facilities ?? [],
                    'images' => $images,
                    'videos' => $project->videos ?? [],
                    'floor_plan_pdf' => $project->floor_plan_pdf ? (
                        strpos($project->floor_plan_pdf, 'http') === 0 
                            ? (function($pdfUrl) {
                                // Extract file path from any format
                                $filePath = '';
                                if (strpos($pdfUrl, '/public/storage/') !== false) {
                                    $parts = explode('/public/storage/', $pdfUrl, 2);
                                    $filePath = $parts[1] ?? '';
                                } elseif (strpos($pdfUrl, '/storage/') !== false) {
                                    $parts = explode('/storage/', $pdfUrl, 2);
                                    $filePath = $parts[1] ?? '';
                                }
                                
                                if (!empty($filePath)) {
                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                                }
                                
                                return $pdfUrl;
                            })($project->floor_plan_pdf)
                            : (strpos($baseUrl, 'shrihariomgroup.com') !== false
                                ? (strpos($project->floor_plan_pdf, '/storage/') === 0
                                    ? 'https://superadmin.shrihariomgroup.com/storage/app/public' . substr($project->floor_plan_pdf, 8)
                                    : 'https://superadmin.shrihariomgroup.com/storage/app/public/' . ltrim($project->floor_plan_pdf, '/'))
                                : rtrim($baseUrl, '/') . $project->floor_plan_pdf)
                    ) : null,
                    'status' => $project->status,
                    'latitude' => $project->latitude ? (float)$project->latitude : null,
                    'longitude' => $project->longitude ? (float)$project->longitude : null,
                    'plots' => $plots->values()->all(),
                    'available_plots' => $plots->where('status', 'available')->count(),
                    'available_villas' => $plots->where('type', 'villa')->where('status', 'available')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load project details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Book a plot/villa (prebooking with payment)
     */
    public function bookPlot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plot_id' => 'required|exists:plots,id',
            'booking_amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Debug authentication
            \Log::info('Booking request - Auth check', [
                'has_token' => $request->bearerToken() !== null,
                'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null,
                'user' => $request->user() ? $request->user()->id : null,
            ]);
            
            $user = $request->user();
            if (!$user) {
                \Log::warning('Booking failed - No authenticated user', [
                    'bearer_token' => $request->bearerToken() ? 'present' : 'missing',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login again.',
                    'error' => 'authentication_required'
                ], 401);
            }

            $plot = Plot::with('project')->find($request->plot_id);
            
            if (!$plot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plot/Villa not found'
                ], 404);
            }

            if ($plot->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'This unit is not available for booking'
                ], 400);
            }

            $bookingAmount = $request->booking_amount;
            $project = $plot->project;
            
            // Use plot-level minimum booking amount, fallback to project-level
            $minimumBookingAmount = (float)($plot->minimum_booking_amount ?? $project->minimum_booking_amount ?? 0);

            // Validate booking amount against minimum
            if ($minimumBookingAmount > 0 && $bookingAmount < $minimumBookingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Booking amount must be at least ₹{$minimumBookingAmount}",
                    'minimum_booking_amount' => $minimumBookingAmount,
                    'provided_amount' => $bookingAmount
                ], 400);
            }

            // Check wallet balance
            $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
            if (!$wallet || $wallet->balance < $bookingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance. Please add funds to your wallet.',
                    'required_amount' => $bookingAmount,
                    'available_balance' => $wallet ? (float)$wallet->balance : 0
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Create sale/booking record
                $sale = Sale::create([
                    'plot_id' => $plot->id,
                    'sold_by_user_id' => $user->id, // Broker who is booking
                    'customer_id' => $user->id, // Same user as customer
                    'customer_name' => $user->name,
                    'customer_phone' => $user->phone_number,
                    'customer_email' => $user->email,
                    'sale_price' => $bookingAmount, // Booking amount
                    'booking_amount' => $bookingAmount,
                    'commission_amount' => 0, // Will be calculated by commission service
                    'status' => 'confirmed', // Confirmed since payment is done
                    'notes' => "Booking payment of ₹{$bookingAmount} for {$plot->type} {$plot->plot_number}",
                    'sale_date' => now(),
                ]);

                // Update plot status
                $plot->update(['status' => 'booked']);

                // Deduct booking amount from wallet
                DB::table('wallets')
                    ->where('user_id', $user->id)
                    ->decrement('balance', $bookingAmount);
                
                // Increment total withdrawn (as it's a payment)
                DB::table('wallets')
                    ->where('user_id', $user->id)
                    ->increment('total_withdrawn', $bookingAmount);

                // Create transaction record for booking payment
                $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
                $transactionId = 'TXN' . strtoupper(uniqid()) . time();
                
                // Check if transaction_id already exists (very unlikely but safe)
                while (DB::table('transactions')->where('transaction_id', $transactionId)->exists()) {
                    $transactionId = 'TXN' . strtoupper(uniqid()) . time() . rand(1000, 9999);
                }
                
                DB::table('transactions')->insert([
                    'user_id' => $user->id,
                    'transaction_id' => $transactionId,
                    'type' => 'booking', // Booking type for plot/villa bookings
                    'amount' => $bookingAmount,
                    'status' => 'completed',
                    'description' => "Booking payment for {$plot->type} {$plot->plot_number} - {$plot->project->name}",
                    'balance_before' => $wallet->balance + $bookingAmount,
                    'balance_after' => $wallet->balance,
                    'reference_id' => $sale->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Distribute commissions to booking user and referral chain
                $commissionService = new CommissionDistributionService();
                $commissionDistribution = $commissionService->distributeCommission($sale, $plot->project, $user);

                DB::commit();

                $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Plot/Villa booked successfully! Commission distributed.',
                    'data' => [
                        'sale_id' => $sale->id,
                        'plot_number' => $plot->plot_number,
                        'plot_type' => $plot->type,
                        'booking_amount' => $bookingAmount,
                        'commission_distributed' => $sale->commission_amount,
                        'remaining_balance' => $wallet->balance,
                        'commission_details' => $commissionDistribution,
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

