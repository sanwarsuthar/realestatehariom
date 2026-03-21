<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionCalculationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\ContactInquiryController;
use App\Services\CommissionDistributionService;

// Health check endpoint (for connection testing)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Server is running',
        'timestamp' => now()->toIso8601String(),
    ])->header('Access-Control-Allow-Origin', '*')
      ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-password-reset-otp', [AuthController::class, 'verifyPasswordResetOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::put('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

// Public API Routes (for Flutter app)
Route::get('/dashboard', function () {
    try {
        $user = auth('sanctum')->user();
    } catch (\Exception $e) {
        $user = null;
    }
    
    // Get recent projects
    $baseUrl = request()->getSchemeAndHttpHost();
    // For production, ALWAYS use: https://superadmin.shrihariomgroup.com/
    if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
        $baseUrl = 'https://superadmin.shrihariomgroup.com';
    }
    $recentProjects = \App\Models\Project::where('is_active', true)
        ->withCount(['plots' => function($query) {
            $query->where('status', 'available');
        }])
        ->latest()
        ->limit(5)
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
            
            return [
                'id' => $project->id,
                'name' => $project->name,
                'location' => $project->location,
                'type' => $project->type,
                'commission_per_slab' => $project->commission_per_slab ?? [],
                'images' => $images,
                'available_units' => $project->plots_count,
            ];
        });
    
    // Get booked properties for user (approved bookings)
    $bookedProperties = [];
    // Get pending bookings (payment requests with status 'pending')
    $pendingBookings = [];
    if ($user) {
        $bookedProperties = \App\Models\Sale::where('customer_id', $user->id)
            ->where('status', 'confirmed')
            ->with(['plot.project', 'soldByUser.slab'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($sale) use ($user) {
                $plot = $sale->plot;
                $plotSize = $plot ? (float)($plot->size ?? 0) : 0;
                $pricePerUnit = $plot ? (float)($plot->price_per_unit ?? 0) : 0;
                $totalPlotValue = $plotSize > 0 && $pricePerUnit > 0 ? $plotSize * $pricePerUnit : 0;
                
                // Calculate total paid amount (sum of all approved payment requests for this plot by this user)
                $totalPaid = \App\Models\PaymentRequest::where('plot_id', $sale->plot_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->sum('amount');
                
                // If no payment requests found, use booking_amount from sale as total paid
                if ($totalPaid == 0 && $sale->booking_amount > 0) {
                    $totalPaid = (float)$sale->booking_amount;
                }
                
                // Calculate remaining amount
                $remainingAfterThis = $totalPlotValue > 0 
                    ? max(0, $totalPlotValue - $totalPaid)
                    : 0;
                
                // Calculate broker commission using centralized service
                $brokerCommission = 0;
                $brokerSlabName = 'N/A';
                $fixedAmountPerUnit = 0;
                $propertyTypeName = 'N/A';
                $measurementUnit = 'sqft';
                
                if ($plot && $plotSize > 0) {
                    $soldByUser = $sale->soldByUser;
                    if ($soldByUser) {
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
                        
                        if ($propertyTypeModel) {
                            $propertyTypeName = $propertyTypeModel->name;
                            $measurementUnit = $propertyTypeModel->measurementUnit->symbol ?? 'sqft';
                            
                            // Get user's current slab
                            $userSlab = $soldByUser->slab;
                            if ($userSlab) {
                                $brokerSlabName = $userSlab->name;
                                
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
                                
                                // Calculate progressive commission (based on total volume before this sale)
                                $commissionService = new CommissionDistributionService();
                                
                                // Get total volume sold before this sale (for this property type)
                                // Note: For booked properties, we use the sale's commission_distribution if available
                                $totalVolumeBeforeSale = 0;
                                if ($sale->commission_distribution) {
                                    $commDist = is_string($sale->commission_distribution) 
                                        ? json_decode($sale->commission_distribution, true) 
                                        : $sale->commission_distribution;
                                    if (isset($commDist[1]['total_volume_before_sale'])) {
                                        $totalVolumeBeforeSale = (float)$commDist[1]['total_volume_before_sale'];
                                    } else {
                                        $totalVolumeBeforeSale = $commissionService->calculateTotalAreaSoldForPropertyType($soldByUser, $propertyTypeModel, $sale->id, true);
                                    }
                                } else {
                                    $totalVolumeBeforeSale = $commissionService->calculateTotalAreaSoldForPropertyType($soldByUser, $propertyTypeModel, $sale->id, true);
                                }
                                
                                // Calculate progressive commission breakdown with allocated amount
                                $progressiveCommission = $commissionService->calculateProgressiveCommission(
                                    $soldByUser,
                                    $propertyTypeModel,
                                    $plotTypeSlug,
                                    $totalVolumeBeforeSale,
                                    $plotSize,
                                    $allocatedAmount
                                );
                                
                                $brokerCommission = $progressiveCommission['total_commission'] ?? 0;
                                $commissionPercentage = $progressiveCommission['weighted_average_percentage'] ?? 0;
                                $progressiveBreakdown = $progressiveCommission['breakdown'] ?? [];
                                
                                // Calculate commission per unit for display
                                $fixedAmountPerUnit = $allocatedAmount > 0 ? ($allocatedAmount * $commissionPercentage / 100) : 0;
                            }
                        }
                    }
                }
                
                return [
                    'id' => $sale->id,
                    'plot_number' => $plot->plot_number ?? 'N/A',
                    'plot_type' => $plot->type ?? 'N/A',
                    'project_name' => $plot->project->name ?? 'N/A',
                    'project_id' => $plot->project->id ?? null,
                    'amount' => (float)$sale->booking_amount ?? (float)$sale->sale_price, // Use booking_amount as the amount paid
                    'status' => 'confirmed',
                    'created_at' => $sale->created_at->toIso8601String(),
                    // Calculation fields
                    'plot_size' => $plotSize,
                    'price_per_unit' => $pricePerUnit,
                    'total_plot_value' => $totalPlotValue,
                    'total_paid' => (float)$totalPaid,
                    'remaining_after_this' => $remainingAfterThis,
                    // Broker commission data (calculated using progressive commission)
                    'broker_commission' => $brokerCommission,
                    'broker_slab_name' => $brokerSlabName,
                    'fixed_amount_per_unit' => $fixedAmountPerUnit,
                    'property_type_name' => $propertyTypeName,
                    'measurement_unit' => $measurementUnit,
                    'progressive_breakdown' => $progressiveBreakdown ?? [],
                    'total_volume_before_sale' => $totalVolumeBeforeSale ?? 0,
                ];
            });
        
        // Get pending bookings from payment requests (including booked_by_other and rejected)
        $pendingBookings = \App\Models\PaymentRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'booked_by_other', 'rejected'])
            ->with(['plot.project', 'paymentMethod', 'user.slab'])
            ->latest()
            ->get()
            ->map(function($request) use ($user) {
                $plot = $request->plot;
                $plotSize = $plot ? (float)($plot->size ?? 0) : 0;
                $pricePerUnit = $plot ? (float)($plot->price_per_unit ?? 0) : 0;
                $totalPlotValue = $plotSize > 0 && $pricePerUnit > 0 ? $plotSize * $pricePerUnit : 0;
                
                // Calculate total paid amount (sum of all approved payment requests for this plot by this user)
                $totalPaid = \App\Models\PaymentRequest::where('plot_id', $request->plot_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->where('id', '!=', $request->id) // Exclude current request
                    ->sum('amount');
                
                // Calculate remaining amount after this request is approved
                // Always include current request amount to show what will remain after approval
                $currentRequestAmount = (float)$request->amount;
                $totalPaidIncludingThis = $totalPaid + $currentRequestAmount;
                $remainingAfterThis = $totalPlotValue > 0 
                    ? max(0, $totalPlotValue - $totalPaidIncludingThis)
                    : 0;
                
                // Calculate broker commission using centralized service
                $brokerCommission = 0;
                $brokerSlabName = 'N/A';
                $fixedAmountPerUnit = 0;
                $propertyTypeName = 'N/A';
                $measurementUnit = 'sqft';
                
                if ($plot && $plotSize > 0) {
                    $requestUser = $request->user;
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
                    
                        if ($propertyTypeModel && $requestUser) {
                            $propertyTypeName = $propertyTypeModel->name;
                            $measurementUnit = $propertyTypeModel->measurementUnit->symbol ?? 'sqft';
                            
                            // Get user's current slab
                            $userSlab = $requestUser->slab;
                            if ($userSlab) {
                                $brokerSlabName = $userSlab->name;
                                
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
                                
                                // Calculate progressive commission (based on total volume before this sale)
                                $commissionService = new CommissionDistributionService();
                                
                                // Get total volume sold before this sale (for this property type)
                                // Include team volume (own sales + team sales) for accurate slab calculation
                                $totalVolumeBeforeSale = $commissionService->calculateTotalAreaSoldForPropertyType($requestUser, $propertyTypeModel, null, true);
                                
                                // Calculate progressive commission breakdown with allocated amount
                                $progressiveCommission = $commissionService->calculateProgressiveCommission(
                                    $requestUser,
                                    $propertyTypeModel,
                                    $plotTypeSlug,
                                    $totalVolumeBeforeSale,
                                    $plotSize,
                                    $allocatedAmount
                                );
                                
                                $brokerCommission = $progressiveCommission['total_commission'] ?? 0;
                                $commissionPercentage = $progressiveCommission['weighted_average_percentage'] ?? 0;
                                $progressiveBreakdown = $progressiveCommission['breakdown'] ?? [];
                                
                                // Calculate commission per unit for display
                                $fixedAmountPerUnit = $allocatedAmount > 0 ? ($allocatedAmount * $commissionPercentage / 100) : 0;
                            }
                        }
                }
                
                return [
                    'id' => $request->id,
                    'plot_number' => $plot->plot_number ?? 'N/A',
                    'plot_type' => $plot->type ?? 'N/A',
                    'project_name' => $plot->project->name ?? 'N/A',
                    'project_id' => $plot->project->id ?? null,
                    'amount' => (float)$request->amount,
                    'status' => $request->status, // 'pending', 'booked_by_other', or 'rejected'
                    'payment_method' => $request->paymentMethod->name ?? 'N/A',
                    'payment_method_name' => $request->paymentMethod->name ?? 'N/A', // For consistency with PaymentController
                    'admin_notes' => $request->admin_notes ?? null,
                    'created_at' => $request->created_at->toIso8601String(),
                    // Calculation fields
                    'plot_size' => $plotSize,
                    'price_per_unit' => $pricePerUnit,
                    'total_plot_value' => $totalPlotValue,
                    'total_paid' => (float)$totalPaid,
                    'remaining_after_this' => $remainingAfterThis,
                    // Broker commission data (calculated using centralized service)
                    'broker_commission' => $brokerCommission,
                    'broker_slab_name' => $brokerSlabName,
                    'fixed_amount_per_unit' => $fixedAmountPerUnit,
                    'property_type_name' => $propertyTypeName,
                    'measurement_unit' => $measurementUnit,
                ];
            });
    }
    
    // Get slider images from settings
    $sliderImages = json_decode(\App\Models\Setting::get('home_slider_images', '[]'), true) ?? [];
    
    // Convert image paths - ALWAYS use: https://superadmin.shrihariomgroup.com/storage/app/public/
    $baseUrl = 'https://superadmin.shrihariomgroup.com/';
    
    $sliderImages = array_map(function($imagePath) use ($baseUrl) {
        // If it's a full URL (from old data), convert it
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            // Extract file path from any format
            $filePath = '';
            
            // Handle /public/storage/ format
            if (strpos($imagePath, '/public/storage/') !== false) {
                $pathParts = explode('/public/storage/', $imagePath, 2);
                $filePath = $pathParts[1] ?? '';
            }
            // Handle /storage/ format
            elseif (strpos($imagePath, '/storage/') !== false) {
                $pathParts = explode('/storage/', $imagePath, 2);
                $filePath = $pathParts[1] ?? '';
            }
            // Handle /sliders/ format (from public/sliders)
            elseif (strpos($imagePath, '/sliders/') !== false) {
                $pathParts = explode('/sliders/', $imagePath, 2);
                $filePath = 'sliders/' . ($pathParts[1] ?? '');
            }
            // Extract from URL path
            else {
                $parsedUrl = parse_url($imagePath);
                $path = $parsedUrl['path'] ?? '';
                if (strpos($path, '/sliders/') !== false) {
                    $pathParts = explode('/sliders/', $path, 2);
                    $filePath = 'sliders/' . ($pathParts[1] ?? '');
                } elseif (!empty($path)) {
                    $filePath = ltrim($path, '/');
                }
            }
            
            // Always return shrihariomgroup.com/superadmin/storage/app/public/ format
            if (!empty($filePath)) {
                return rtrim($baseUrl, '/') . '/storage/app/public/' . $filePath;
            }
            
            return $imagePath;
        }
        
        // If it's a relative path (starts with /), make it absolute
        if (strpos($imagePath, '/') === 0) {
            // If path is /sliders/..., convert to /storage/app/public/sliders/...
            if (strpos($imagePath, '/sliders/') === 0) {
                $filePath = substr($imagePath, 1); // Remove leading /
                return rtrim($baseUrl, '/') . '/storage/app/public/' . $filePath;
            }
            // If path already has /storage/, ensure it's /storage/app/public/
            if (strpos($imagePath, '/storage/') === 0 && strpos($imagePath, '/storage/app/public/') !== 0) {
                $filePath = substr($imagePath, 9); // Remove /storage/
                return rtrim($baseUrl, '/') . '/storage/app/public/' . $filePath;
            }
            return rtrim($baseUrl, '/') . $imagePath;
        }
        
        // Fallback: assume it's a relative path (like "sliders/filename.jpg")
        $fullPath = '/' . ltrim($imagePath, '/');
        // If it's a slider path, ensure it uses storage/app/public
        if (strpos($fullPath, '/sliders/') === 0) {
            return rtrim($baseUrl, '/') . '/storage/app/public' . $fullPath;
        }
        return rtrim($baseUrl, '/') . '/storage/app/public' . $fullPath;
    }, $sliderImages);
    
    return response()->json([
        'success' => true,
        'data' => [
            'current_slab' => $user && $user->slab ? $user->slab->name : 'Slab1',
            'slab_progress' => 0.4,
            'next_slab_target' => '₹50,000 remaining',
            'booked_properties' => $bookedProperties,
            'pending_bookings' => $pendingBookings,
            'recent_projects' => $recentProjects,
            'recent_activities' => [],
            'top_performers' => [],
            'slider_images' => $sliderImages, // Add slider images to dashboard response
        ]
    ])->header('Access-Control-Allow-Origin', '*')
      ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
});

// Property Types Route (Public - for app to fetch available property types)
Route::get('/property-types', function () {
    // Color palette matching admin (same order and colors)
    // Using Tailwind CSS color values (800 shade for main color, 100 for background)
    $colorPalette = [
        ['bg' => 'bg-primary-100', 'text' => 'text-primary-800', 'hex' => '#9333EA'], // Purple (primary)
        ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'hex' => '#166534'], // Green
        ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'hex' => '#854D0E'], // Yellow
        ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'hex' => '#1E40AF'], // Blue
        ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'hex' => '#9A3412'], // Orange
        ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'hex' => '#6B21A8'], // Purple
        ['bg' => 'bg-pink-100', 'text' => 'text-pink-800', 'hex' => '#9F1239'], // Pink
    ];
    
    $propertyTypes = \App\Models\PropertyType::where('is_active', true)
        ->with('measurementUnit')
        ->orderBy('name')
        ->get()
        ->map(function ($type, $index) use ($colorPalette) {
            $colorIndex = $index % count($colorPalette);
            $color = $colorPalette[$colorIndex];
            
            return [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => \Illuminate\Support\Str::slug($type->name), // Generate slug using Laravel's slug method (matches admin)
                'description' => $type->description,
                'color' => [
                    'hex' => $color['hex'],
                    'bg_class' => $color['bg'],
                    'text_class' => $color['text'],
                ],
                'measurement_unit' => $type->measurementUnit ? [
                    'id' => $type->measurementUnit->id,
                    'name' => $type->measurementUnit->name,
                    'symbol' => $type->measurementUnit->symbol,
                ] : null,
            ];
        });
    
    return response()->json([
        'success' => true,
        'data' => $propertyTypes
    ]);
});

// Project Routes
Route::get('/projects', [ProjectController::class, 'index']);
Route::get('/projects/{id}', [ProjectController::class, 'show']);
Route::post('/projects/book', [ProjectController::class, 'bookPlot'])->middleware('auth:sanctum');

Route::get('/team', function (Request $request) {
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $level = (int) $request->input('level', 1); // Default to level 1
        $level = max(1, min(15, $level)); // Clamp between 1 and 15

        // Function to get users at a specific level
        $getUsersAtLevel = function($userId, $targetLevel, $currentLevel = 1) use (&$getUsersAtLevel) {
            if ($currentLevel > $targetLevel) {
                return collect([]);
            }

            if ($currentLevel == $targetLevel) {
                // Get direct referrals at this level
                return \App\Models\User::where('referred_by_user_id', $userId)
                    ->with('slab')
                    ->get();
            }

            // Get direct referrals and recursively get their downline
            $directReferrals = \App\Models\User::where('referred_by_user_id', $userId)->pluck('id');
            $usersAtLevel = collect([]);
            
            foreach ($directReferrals as $referralId) {
                $usersAtLevel = $usersAtLevel->merge($getUsersAtLevel($referralId, $targetLevel, $currentLevel + 1));
            }
            
            return $usersAtLevel;
        };

        $downline = $getUsersAtLevel($user->id, $level);
        
        $mappedDownline = $downline->map(function ($member) use ($level) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'broker_id' => $member->broker_id ?? '',
                'referral_code' => $member->referral_code,
                'slab' => $member->slab ? $member->slab->name : 'Slab1',
                'status' => $member->status ?? 'active',
                'level' => $level,
                'total_commission_earned' => (float)($member->total_commission_earned ?? 0),
                'total_business_volume' => (float)($member->total_business_volume ?? 0),
                'created_at' => $member->created_at ? $member->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mappedDownline,
            'level' => $level,
            'total_count' => $mappedDownline->count()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch team members: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// Get team member details
Route::get('/team/{userId}', function (Request $request, $userId) {
    try {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Verify that the requested user is in current user's downline
        $getAllDownline = function($userId, $maxLevel = 15, $currentLevel = 1) use (&$getAllDownline) {
            if ($currentLevel > $maxLevel) {
                return collect([]);
            }

            $directReferrals = \App\Models\User::where('referred_by_user_id', $userId)->get();
            $allDownline = collect();
            
            foreach ($directReferrals as $referral) {
                $allDownline->push($referral);
                $allDownline = $allDownline->merge($getAllDownline($referral->id, $maxLevel, $currentLevel + 1));
            }
            
            return $allDownline;
        };

        $downline = $getAllDownline($currentUser->id);
        $downlineIds = $downline->pluck('id')->toArray();
        
        if (!in_array((int)$userId, $downlineIds) && (int)$userId != $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'User not found in your team'
            ], 403);
        }

        // Get user details
        $user = \App\Models\User::with(['slab', 'wallet'])->find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Calculate total sold volume by this user
        $salesByUser = \App\Models\Sale::where('sold_by_user_id', $userId)
            ->where('status', 'confirmed')
            ->with('plot')
            ->get();
        
        $totalSoldVolumeByUser = 0;
        foreach ($salesByUser as $sale) {
            if ($sale->plot && $sale->plot->size) {
                $totalSoldVolumeByUser += (float)$sale->plot->size;
            }
        }

        // Calculate total sold volume by team (all downline users)
        $getTeamDownline = function($userId) use (&$getTeamDownline) {
            $downline = collect();
            $directReferrals = \App\Models\User::where('referred_by_user_id', $userId)->get();
            
            foreach ($directReferrals as $referral) {
                $downline->push($referral);
                $downline = $downline->merge($getTeamDownline($referral->id));
            }
            
            return $downline;
        };

        $teamDownline = $getTeamDownline($userId);
        $teamDownlineIds = $teamDownline->pluck('id')->toArray();
        
        $totalSoldVolumeByTeam = 0;
        if (!empty($teamDownlineIds)) {
            $teamSales = \App\Models\Sale::whereIn('sold_by_user_id', $teamDownlineIds)
                ->where('status', 'confirmed')
                ->with('plot')
                ->get();
            
            foreach ($teamSales as $sale) {
                if ($sale->plot && $sale->plot->size) {
                    $totalSoldVolumeByTeam += (float)$sale->plot->size;
                }
            }
        }

        // Get total earned commission
        $totalCommissionEarned = (float)($user->total_commission_earned ?? 0);
        
        // Mask phone number (hide last 4 digits)
        $phoneNumber = $user->phone_number ?? '';
        $maskedPhone = '';
        if (strlen($phoneNumber) > 4) {
            $maskedPhone = substr($phoneNumber, 0, -4) . '****';
        } else {
            $maskedPhone = '****';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $maskedPhone,
                'broker_id' => $user->broker_id ?? '',
                'referral_code' => $user->referral_code ?? '',
                'slab' => $user->slab ? $user->slab->name : 'N/A',
                'slab_color' => $user->slab ? $user->slab->color_code : null,
                'status' => $user->status ?? 'active',
                'total_sold_volume_by_user' => round($totalSoldVolumeByUser, 2),
                'total_sold_volume_by_team' => round($totalSoldVolumeByTeam, 2),
                'total_commission_earned' => round($totalCommissionEarned, 2),
                'total_downline_count' => $user->total_downline_count ?? 0,
                'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch team member details: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// Wallet Routes (Protected)
Route::prefix('wallet')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [WalletController::class, 'getWallet']);
    Route::get('/transactions', [WalletController::class, 'getTransactions']);
    Route::post('/deposit', [WalletController::class, 'requestDeposit']);
    Route::post('/withdraw', [WalletController::class, 'requestWithdrawal']);
});

// Commission Routes (Protected)
Route::prefix('commission')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CommissionController::class, 'getCommissionData']);
    Route::get('/history', [CommissionController::class, 'getCommissionHistory']);
    Route::post('/calculate', [CommissionCalculationController::class, 'calculateCommission']);
});

// Slab Routes (Protected)
Route::prefix('slabs')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', [App\Http\Controllers\Api\SlabController::class, 'getUserSlabs']);
});

// Payment Routes (Protected)
Route::prefix('payment')->middleware('auth:sanctum')->group(function () {
    Route::get('/methods', [App\Http\Controllers\Api\PaymentController::class, 'getPaymentMethods']);
    Route::post('/request', [App\Http\Controllers\Api\PaymentController::class, 'createPaymentRequest']);
    Route::get('/requests', [App\Http\Controllers\Api\PaymentController::class, 'getMyPaymentRequests']);
});

// Profile Routes (Protected)
Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\ProfileController::class, 'getProfile']);
    Route::put('/', [App\Http\Controllers\Api\ProfileController::class, 'updateProfile']);
    Route::put('/change-password', [App\Http\Controllers\Api\ProfileController::class, 'changePassword']);
    Route::post('/change-password', [App\Http\Controllers\Api\ProfileController::class, 'changePassword']);
});

// KYC Routes (Protected)
Route::prefix('kyc')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\KycController::class, 'getKycStatus']);
    Route::post('/upload', [App\Http\Controllers\Api\KycController::class, 'uploadKycDocument']);
    Route::post('/submit', [App\Http\Controllers\Api\KycController::class, 'submitKyc']);
});

Route::get('/settings', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'notifications_enabled' => true,
            'dark_mode_enabled' => false,
            'language' => 'English',
            'app_name' => \App\Models\Setting::get('app_name', 'Shree Hari Om'),
            'android_store_url' => \App\Models\Setting::get('android_store_url', ''),
            'ios_store_url' => \App\Models\Setting::get('ios_store_url', ''),
        ]
    ]);
});

// Maintenance Mode Check
Route::get('/maintenance-mode', function () {
    $maintenanceMode = \App\Models\Setting::get('maintenance_mode', '0') === '1';
    $maintenanceMessage = \App\Models\Setting::get('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.');
    
    return response()->json([
        'success' => true,
        'data' => [
            'maintenance_mode' => $maintenanceMode,
            'message' => $maintenanceMessage,
        ]
    ]);
});

// Public content routes
Route::get('/about-us', [ContentController::class, 'getAboutUs']);
Route::get('/contact-us', [ContentController::class, 'getContactUs']);
Route::get('/privacy-policy', [ContentController::class, 'getPrivacyPolicy']);
Route::get('/terms-conditions', [ContentController::class, 'getTermsConditions']);
Route::post('/contact-inquiry', [ContactInquiryController::class, 'store']);
Route::get('/app-version', [ContentController::class, 'checkAppVersion']);

Route::get('/users', function () {
    $users = \App\Models\User::where('user_type', 'broker')->with('slab')->get();
    return response()->json([
        'success' => true,
        'data' => $users
    ]);
});

Route::put('/settings', function (Request $request) {
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:6|confirmed',
            'notifications_enabled' => 'sometimes|boolean',
            'dark_mode_enabled' => 'sometimes|boolean',
            'language' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle password change
        if ($request->filled('password')) {
            // Verify current password
            if (!$request->filled('current_password') || !\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => ['current_password' => ['Current password is incorrect']]
                ], 422);
            }

            // Update password
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
            $user->save();
        }

        // Handle other settings (if needed in future)
        // For now, just return success
        
    return response()->json([
        'success' => true,
            'message' => $request->filled('password') ? 'Password changed successfully' : 'Settings updated successfully'
    ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update settings: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// Admin Routes (Protected)
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    
    // User Management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
});

// User Routes (Protected)
Route::prefix('user')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});