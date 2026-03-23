<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Plot;
use App\Models\PropertyType;
use App\Services\CommissionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentRequest::with(['user', 'plot.project', 'paymentMethod']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                              ->orWhere('email', 'like', '%' . $search . '%')
                              ->orWhere('phone_number', 'like', '%' . $search . '%')
                              ->orWhere('broker_id', 'like', '%' . $search . '%')
                              ->orWhere('referral_code', 'like', '%' . $search . '%');
                })
                ->orWhereHas('plot', function($plotQuery) use ($search) {
                    $plotQuery->where('plot_number', 'like', '%' . $search . '%')
                              ->orWhere('type', 'like', '%' . $search . '%');
                })
                ->orWhereHas('plot.project', function($projectQuery) use ($search) {
                    $projectQuery->where('name', 'like', '%' . $search . '%');
                })
                ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        $paymentRequests = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->appends(request()->query());

        $stats = [
            'pending' => PaymentRequest::where('status', 'pending')->count(),
            'approved' => PaymentRequest::where('status', 'approved')->count(),
            'rejected' => PaymentRequest::where('status', 'rejected')->count(),
            'booked_by_other' => PaymentRequest::where('status', 'booked_by_other')->count(),
        ];

        $paymentMethods = PaymentMethod::where('is_active', true)->get();
        $statusOptions = ['pending', 'approved', 'rejected', 'booked_by_other'];

        return view('admin.payment-requests.index', compact('paymentRequests', 'stats', 'paymentMethods', 'statusOptions'));
    }

    public function approve(Request $request, $id)
    {
        $paymentRequest = PaymentRequest::with(['user', 'plot.project'])->findOrFail($id);

        if ($paymentRequest->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Payment request has already been processed.');
        }

        DB::beginTransaction();

        try {
            $plot = $paymentRequest->plot;
            $user = $paymentRequest->user;
            $project = $plot->project;

            // Instalment: plot already booked by this user
            if ($plot->status === 'booked') {
                $sale = Sale::where('plot_id', $plot->id)->where('customer_id', $user->id)->where('status', 'confirmed')->first();
                if (!$sale) {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'This plot is booked by another customer. Only the booking customer can submit instalment payments.');
                }
                $paymentRequest->update([
                    'status' => 'approved',
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                    'sale_id' => $sale->id,
                    'admin_notes' => $request->input('admin_notes'),
                ]);
                $commissionService = new CommissionDistributionService();
                $commissionService->releaseProportionalCommission($sale);
                DB::commit();
                return redirect()->back()
                    ->with('success', 'Instalment approved. Commission will be credited to broker wallet when the deal is marked as done.');
            }

            // Allow approval when plot is available or pending_booking (someone requested, awaiting approval)
            if (!in_array($plot->status, ['available', 'pending_booking'])) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Plot/Villa is no longer available for booking.');
            }

            // Total plot value for proportional commission release
            $plotSize = (float)($plot->size ?? 0);
            $pricePerUnit = (float)($plot->price_per_unit ?? 0) ?: (float)($project->price_per_sqft ?? 0);
            $totalSaleValue = ($plotSize > 0 && $pricePerUnit > 0) ? $plotSize * $pricePerUnit : $paymentRequest->amount;

            // Create sale/booking record
            $sale = Sale::create([
                'plot_id' => $plot->id,
                'sold_by_user_id' => $user->id,
                'customer_id' => $user->id,
                'customer_name' => $user->name,
                'customer_phone' => $user->phone_number,
                'customer_email' => $user->email,
                'sale_price' => $paymentRequest->amount,
                'total_sale_value' => $totalSaleValue,
                'booking_amount' => $paymentRequest->amount,
                'commission_amount' => 0, // Will be calculated by commission service
                'status' => 'confirmed',
                'notes' => "Booking payment of ₹{$paymentRequest->amount} for {$plot->type} {$plot->plot_number}",
                'sale_date' => now(),
            ]);

            // Update plot status
            $plot->update(['status' => 'booked']);

            // Mark all other pending payment requests for this plot as 'booked_by_other'
            PaymentRequest::where('plot_id', $plot->id)
                ->where('id', '!=', $paymentRequest->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'booked_by_other',
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                    'admin_notes' => 'This property was booked by another user. Refund will be processed within 5-7 business days if payment was made.',
                ]);

            // Link this payment request to the sale and mark approved BEFORE distributing commission
            // so that CommissionDistributionService uses actual approved total for gross (balance) ratio.
            $paymentRequest->update([
                'status' => 'approved',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'sale_id' => $sale->id,
                'admin_notes' => $request->input('admin_notes'),
            ]);

            // Distribute commissions: Main (projected) + Gross (proportional release)
            $commissionService = new CommissionDistributionService();
            $commissionDistribution = $commissionService->distributeCommission($sale, $project, $user);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Payment approved and booking confirmed. Commission distributed (Main + proportional Gross).');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve payment request: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to approve payment: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $paymentRequest = PaymentRequest::findOrFail($id);

        if ($paymentRequest->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'Payment request has already been processed.');
        }

        $paymentRequest->update([
            'status' => 'rejected',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'admin_notes' => $request->input('admin_notes'),
        ]);

        // Free the plot so others can book again (only if it was reserved by this pending request)
        $plot = $paymentRequest->plot;
        if ($plot && $plot->status === 'pending_booking') {
            $plot->update(['status' => 'available']);
        }

        return redirect()->back()
            ->with('success', 'Payment request rejected. Plot is available for booking again.');
    }

    public function show($id)
    {
        $paymentRequest = PaymentRequest::with(['user', 'plot.project', 'paymentMethod', 'processedBy'])
            ->findOrFail($id);

        // Calculate property value and payment details
        $plot = $paymentRequest->plot;
        $plotSize = $plot ? (float)($plot->size ?? 0) : 0;
        $pricePerUnit = $plot ? (float)($plot->price_per_unit ?? 0) : 0;
        $totalPlotValue = $plotSize > 0 && $pricePerUnit > 0 ? $plotSize * $pricePerUnit : 0;
        
        // Calculate total paid amount (sum of all approved payment requests for this plot by this user)
        $userId = $paymentRequest->user_id;
        $totalPaid = PaymentRequest::where('plot_id', $paymentRequest->plot_id)
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('id', '!=', $paymentRequest->id) // Exclude current request
            ->sum('amount');
        
        // Calculate remaining amount after this request is approved
        // Always include current request amount to show what will remain after approval
        $currentRequestAmount = (float)$paymentRequest->amount;
        $totalPaidIncludingThis = $totalPaid + $currentRequestAmount;
        $remainingAfterThis = $totalPlotValue > 0 
            ? max(0, $totalPlotValue - $totalPaidIncludingThis)
            : 0;

        // Get measurement unit symbol
        $measurementUnit = 'sqft';
        if ($plot && $plot->type) {
            $plotType = strtolower($plot->type);
            if (strpos($plotType, 'yard') !== false || $plotType === 'plote') {
                $measurementUnit = 'sqyd';
            }
        }

        // Calculate broker commission
        $brokerCommission = 0;
        $brokerSlabName = 'N/A';
        $fixedAmountPerUnit = 0;
        $propertyTypeName = 'N/A';
        $progressiveBreakdown = [];
        $totalVolumeBeforeSale = 0;
        $userSlab = null;
        $user = $paymentRequest->user;
        $propertyTypeModel = null;
        
        if ($plot && $plotSize > 0) {
          
            $plotTypeSlug = strtolower(trim($plot->type ?? 'plot'));
          
            // Get PropertyType model
            $propertyTypeModel = PropertyType::where('is_active', true)
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
                
                // Get user's current slab
                $userSlab = $user->slab;
                
                if (!$userSlab) {
                    // User has no slab assigned
                    $brokerSlabName = 'N/A';
                    $brokerCommission = 0;
                    $fixedAmountPerUnit = 0;
                    $progressiveBreakdown = [];
                    $totalVolumeBeforeSale = 0;
                } else {
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
                    
                    if ($allocatedAmount <= 0) {
                        // Allocated amount not configured
                        $brokerCommission = 0;
                        $fixedAmountPerUnit = 0;
                        $progressiveBreakdown = [];
                    } else {
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
                        $commissionPercentage = $progressiveCommission['weighted_average_percentage'] ?? 0;
                    $progressiveBreakdown = $progressiveCommission['breakdown'] ?? [];
                        
                        // Calculate commission per unit for display
                        $fixedAmountPerUnit = $allocatedAmount > 0 ? ($allocatedAmount * $commissionPercentage / 100) : 0;
                       
                    }
                   
                }
              
            } else {        
               
                // Property type not found
                $brokerCommission = 0;
                $fixedAmountPerUnit = 0;
                $progressiveBreakdown = [];
            }
        }

        // Get allocated amount for display
        $allocatedAmountForDisplay = 0;
        if ($plot && isset($project)) {
            $allocatedAmountConfig = $project->allocated_amount_config ?? [];
            $propertyTypeConfig = $allocatedAmountConfig[$propertyTypeName] ?? null;
            
            if ($propertyTypeConfig) {
                $configType = $propertyTypeConfig['type'] ?? 'percentage';
                $configValue = (float)($propertyTypeConfig['value'] ?? 0);
                
                if ($configType === 'percentage') {
                    $propertyRatePerUnit = (float)($plot->price_per_unit ?? 0);
                    if ($propertyRatePerUnit <= 0) {
                        $propertyRatePerUnit = (float)($project->price_per_sqft ?? 0);
                    }
                    
                    if ($propertyRatePerUnit > 0 && $configValue > 0) {
                        $allocatedAmountForDisplay = ($propertyRatePerUnit * $configValue / 100);
                    }
                }
            } else {
                $allocatedAmountForDisplay = (float)($project->allocated_amount ?? 0);
            }
        }
       
       

        // Preview commission distribution (for display only, before approval)
        $commissionPreview = [];
        $user = $paymentRequest->user;
        if ($plot && $plotSize > 0 && $user && isset($propertyTypeModel) && $allocatedAmountForDisplay > 0) {
            $commissionService = new CommissionDistributionService();
            $commissionPreview = $commissionService->previewCommissionDistribution(
                $user,
                $plot->project,
                $plotSize,
                $plot->type ?? 'plot'
            );
        }
        
          
       
        return view('admin.payment-requests.show', compact(
            'paymentRequest',
            'plotSize',
            'pricePerUnit',
            'totalPlotValue',
            'totalPaid',
            'remainingAfterThis',
            'measurementUnit',
            'brokerCommission',
            'brokerSlabName',
            'fixedAmountPerUnit',
            'propertyTypeName',
            'progressiveBreakdown',
            'totalVolumeBeforeSale',
            'userSlab',
            'allocatedAmountForDisplay',
            'commissionPreview'
        ));
    }
}
