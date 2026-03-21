<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Get all active payment methods
     */
    public function getPaymentMethods(Request $request)
    {
        try {
            $request = request();
            $baseUrl = $request->getSchemeAndHttpHost();
            
            // For production, ALWAYS use: https://superadmin.shrihariomgroup.com/
            if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                $baseUrl = 'https://superadmin.shrihariomgroup.com';
            }
            
            $paymentMethods = PaymentMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($method) use ($baseUrl) {
                    // Build scanner photo URL
                    $scannerPhotoUrl = null;
                    if ($method->scanner_photo) {
                        if (strpos($method->scanner_photo, 'http') === 0) {
                            $scannerPhotoUrl = $method->scanner_photo;
                        } else {
                            $scannerPhotoUrl = strpos($baseUrl, 'shrihariomgroup.com') !== false
                                ? 'https://superadmin.shrihariomgroup.com/storage/app/public/' . ltrim($method->scanner_photo, '/')
                                : rtrim($baseUrl, '/') . '/storage/' . ltrim($method->scanner_photo, '/');
                        }
                    }
                    
                    return [
                        'id' => $method->id,
                        'name' => $method->name,
                        'type' => $method->type,
                        'details' => $method->details ?? [],
                        'ifsc_code' => $method->ifsc_code,
                        'account_number' => $method->account_number,
                        'upi_ids' => $method->upi_ids ?? [],
                        'account_type' => $method->account_type,
                        'scanner_photo' => $scannerPhotoUrl,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $paymentMethods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a payment request for booking
     */
    public function createPaymentRequest(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login again.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'plot_id' => 'required|exists:plots,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
                'amount' => 'required|numeric|min:1',
                'payment_proof' => 'nullable|string', // Base64 image or URL
                'payment_screenshot' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Payment screenshot file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plot = \App\Models\Plot::with('project')->find($request->plot_id);

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

            $project = $plot->project;
            $minimumBookingAmount = (float)($project->minimum_booking_amount ?? 0);

            // Validate booking amount against minimum
            if ($request->amount < $minimumBookingAmount) {
                return response()->json([
                    'success' => false,
                    'message' => "Booking amount must be at least ₹{$minimumBookingAmount}",
                    'minimum_booking_amount' => $minimumBookingAmount,
                    'provided_amount' => $request->amount
                ], 400);
            }

            // Check if user already has a pending payment request for this plot
            $existingRequest = PaymentRequest::where('user_id', $user->id)
                ->where('plot_id', $plot->id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending payment request for this plot.'
                ], 400);
            }

            // Handle payment screenshot upload
            $screenshotPath = null;
            if ($request->hasFile('payment_screenshot')) {
                $screenshotPath = $request->file('payment_screenshot')->store('payment-requests/screenshots', 'public');
            }

            // Create payment request
            $paymentRequest = PaymentRequest::create([
                'user_id' => $user->id,
                'plot_id' => $plot->id,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $request->amount,
                'status' => 'pending',
                'payment_proof' => $request->payment_proof,
                'payment_screenshot' => $screenshotPath,
            ]);

            // Immediately reserve the plot (pending_booking) so others cannot book until admin approves or rejects
            $plot->update(['status' => 'pending_booking']);

            return response()->json([
                'success' => true,
                'message' => 'Payment request submitted successfully. Waiting for admin approval.',
                'data' => [
                    'payment_request_id' => $paymentRequest->id,
                    'plot_number' => $plot->plot_number,
                    'plot_type' => $plot->type,
                    'amount' => $paymentRequest->amount,
                    'status' => $paymentRequest->status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's payment requests
     */
    public function getMyPaymentRequests(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login again.',
                ], 401);
            }

            $userId = $user->id;
            $requestObj = request();
            $baseUrl = $requestObj->getSchemeAndHttpHost();
            
            // For production, ALWAYS use: https://superadmin.shrihariomgroup.com/
            if (strpos($baseUrl, 'shrihariomgroup.com') !== false) {
                $baseUrl = 'https://shrihariomgroup.com/superadmin';
            }
            
            $paymentRequests = PaymentRequest::with(['plot.project', 'paymentMethod'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($pr) use ($userId, $baseUrl) {
                    $plot = $pr->plot;
                    $plotSize = $plot ? (float)($plot->size ?? 0) : 0;
                    $pricePerUnit = $plot ? (float)($plot->price_per_unit ?? 0) : 0;
                    $totalPlotValue = $plotSize > 0 && $pricePerUnit > 0 ? $plotSize * $pricePerUnit : 0;
                    
                    // Calculate total paid amount (sum of all approved payment requests for this plot by this user)
                    $totalPaid = PaymentRequest::where('plot_id', $pr->plot_id)
                        ->where('user_id', $userId)
                        ->where('status', 'approved')
                        ->where('id', '!=', $pr->id) // Exclude current request
                        ->sum('amount');
                    
                    // Calculate remaining amount after this request is approved
                    // Always include current request amount to show what will remain after approval
                    $currentRequestAmount = (float)$pr->amount;
                    $totalPaidIncludingThis = $totalPaid + $currentRequestAmount;
                    $remainingAfterThis = $totalPlotValue > 0 
                        ? max(0, $totalPlotValue - $totalPaidIncludingThis)
                        : 0;
                    
                    // Calculate broker commission using progressive commission
                    $brokerCommission = 0;
                    $brokerSlabName = 'N/A';
                    $fixedAmountPerUnit = 0;
                    $propertyTypeName = 'N/A';
                    $measurementUnit = 'sqft';
                    $progressiveBreakdown = [];
                    $totalVolumeBeforeSale = 0;
                    $progressiveBreakdown = [];
                    $totalVolumeBeforeSale = 0;
                    
                    if ($plot && $plotSize > 0) {
                        $user = $pr->user;
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
                            $userSlab = $user->slab;
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
                                $commissionService = new \App\Services\CommissionDistributionService();
                                
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
                                $commissionPercentage = $progressiveCommission['weighted_average_percentage'] ?? 0;
                                $progressiveBreakdown = $progressiveCommission['breakdown'] ?? [];
                                
                                // Calculate commission per unit for display
                                $fixedAmountPerUnit = $allocatedAmount > 0 ? ($allocatedAmount * $commissionPercentage / 100) : 0;
                            }
                        }
                    }
                    
                    // Build payment screenshot URL
                    $screenshotUrl = null;
                    if ($pr->payment_screenshot) {
                        if (strpos($pr->payment_screenshot, 'http') === 0) {
                            $screenshotUrl = $pr->payment_screenshot;
                        } else {
                            $screenshotUrl = strpos($baseUrl, 'shrihariomgroup.com') !== false
                                ? 'https://superadmin.shrihariomgroup.com/storage/app/public/' . ltrim($pr->payment_screenshot, '/')
                                : rtrim($baseUrl, '/') . '/storage/' . ltrim($pr->payment_screenshot, '/');
                        }
                    }
                    
                    return [
                        'id' => $pr->id,
                        'plot_id' => $pr->plot_id,
                        'plot_number' => $plot->plot_number ?? null,
                        'plot_type' => $plot->type ?? null,
                        'plot_size' => $plotSize,
                        'price_per_unit' => $pricePerUnit,
                        'total_plot_value' => $totalPlotValue,
                        'total_paid' => (float)$totalPaid,
                        'remaining_after_this' => $remainingAfterThis,
                        'project_id' => $plot && $plot->project ? $plot->project->id : null,
                        'project_name' => $plot && $plot->project ? $plot->project->name : null,
                        'payment_method_name' => $pr->paymentMethod->name ?? null,
                        'amount' => (float)$pr->amount,
                        'status' => $pr->status,
                        'payment_screenshot' => $screenshotUrl,
                        'created_at' => $pr->created_at->format('Y-m-d H:i:s'),
                        'processed_at' => $pr->processed_at ? $pr->processed_at->format('Y-m-d H:i:s') : null,
                        // Broker commission data (calculated using progressive commission)
                        'broker_commission' => $brokerCommission,
                        'broker_slab_name' => $brokerSlabName,
                        'fixed_amount_per_unit' => $fixedAmountPerUnit,
                        'property_type_name' => $propertyTypeName,
                        'measurement_unit' => $measurementUnit,
                        'progressive_breakdown' => $progressiveBreakdown,
                        'total_volume_before_sale' => $totalVolumeBeforeSale,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $paymentRequests
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment requests: ' . $e->getMessage()
            ], 500);
        }
    }
}
