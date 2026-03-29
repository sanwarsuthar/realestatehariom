@extends('admin.layouts.app')

@section('title', 'Payment Request Details')
@section('page-title', 'Payment Request Details')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <!-- Request Details -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">Payment Request #{{ $paymentRequest->id }}</h3>
            <a href="{{ route('admin.payment-requests.index') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                <i class="fas fa-arrow-left mr-1"></i>Back to List
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- User Information -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-3">User Information</h4>
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-600">Name:</span> <span class="font-medium">{{ $paymentRequest->user->name ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Referral Code:</span> <span class="font-medium">{{ $paymentRequest->user->referral_code ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Phone:</span> <span class="font-medium">{{ $paymentRequest->user->phone_number ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Email:</span> <span class="font-medium">{{ $paymentRequest->user->email ?? 'N/A' }}</span></div>
                </div>
            </div>

            <!-- Booking Information -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-3">Booking Information</h4>
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-600">Project:</span> <span class="font-medium">{{ $paymentRequest->plot->project->name ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Location:</span> <span class="font-medium">{{ $paymentRequest->plot->project->location ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Plot/Villa:</span> <span class="font-medium">{{ ucfirst($paymentRequest->plot->type ?? '') }} {{ $paymentRequest->plot->plot_number ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Status:</span> 
                        <span class="px-2 py-1 text-xs rounded-full 
                            @if($paymentRequest->plot->status === 'available') bg-green-100 text-green-800
                            @elseif($paymentRequest->plot->status === 'pending_booking') bg-purple-100 text-purple-800
                            @elseif($paymentRequest->plot->status === 'booked') bg-yellow-100 text-yellow-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $paymentRequest->plot->status === 'pending_booking' ? 'Pending Booking' : ucfirst($paymentRequest->plot->status ?? 'N/A') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-3">Payment Information</h4>
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-600">Payment Method:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->name ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Type:</span> <span class="font-medium capitalize">{{ $paymentRequest->paymentMethod->type ?? 'N/A' }}</span></div>
                    <div><span class="text-gray-600">Amount:</span> <span class="font-medium text-green-600 text-lg">₹{{ number_format($paymentRequest->amount, 2) }}</span></div>
                    <div><span class="text-gray-600">Payment Proof:</span> 
                        @if($paymentRequest->payment_proof)
                            <span class="font-medium">{{ $paymentRequest->payment_proof }}</span>
                        @else
                            <span class="text-gray-400">Not provided</span>
                        @endif
                    </div>
                    @if($paymentRequest->payment_screenshot)
                    <div class="mt-3">
                        <span class="text-gray-600 block mb-2">Payment Screenshot:</span>
                        <a href="{{ asset('storage/app/public/' . $paymentRequest->payment_screenshot) }}" target="_blank" class="inline-block">
                            <img src="{{ asset('storage/app/public/' . $paymentRequest->payment_screenshot) }}" 
                                 alt="Payment Screenshot" 
                                 class="max-w-md h-auto border rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer">
                        </a>
                        <p class="text-xs text-gray-500 mt-1">Click image to view full size</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Request Status -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-3">Request Status</h4>
                <div class="space-y-2 text-sm">
                    <div><span class="text-gray-600">Status:</span> 
                        <span class="px-2 py-1 text-xs rounded-full 
                            @if($paymentRequest->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($paymentRequest->status === 'approved') bg-green-100 text-green-800
                            @elseif($paymentRequest->status === 'booked_by_other' || $paymentRequest->status === 'rejected') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            @if($paymentRequest->status === 'booked_by_other')
                                Booked by Other
                            @elseif($paymentRequest->status === 'rejected')
                                Rejected
                            @else
                                {{ ucfirst(str_replace('_', ' ', $paymentRequest->status)) }}
                            @endif
                        </span>
                    </div>
                    <div><span class="text-gray-600">Requested At:</span> <span class="font-medium">{{ $paymentRequest->created_at->format('d M Y, h:i A') }}</span></div>
                    @if($paymentRequest->processed_at)
                    <div><span class="text-gray-600">Processed At:</span> <span class="font-medium">{{ $paymentRequest->processed_at->format('d M Y, h:i A') }}</span></div>
                    <div><span class="text-gray-600">Processed By:</span> <span class="font-medium">{{ $paymentRequest->processedBy->name ?? 'N/A' }}</span></div>
                    @endif
                    @if($paymentRequest->admin_notes)
                    <div><span class="text-gray-600">Admin Notes:</span> <span class="font-medium">{{ $paymentRequest->admin_notes }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Amount Calculation Section -->
        @if($totalPlotValue > 0)
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-calculator text-blue-700 text-xl mr-3"></i>
                <h4 class="text-lg font-semibold text-blue-900">Amount Calculation</h4>
            </div>
            <div class="bg-white rounded-lg p-4 space-y-3">
                @if($plotSize > 0 && $pricePerUnit > 0)
                <div class="grid grid-cols-2 gap-4 pb-3 border-b">
                    <div>
                        <span class="text-sm text-gray-600">Property Size:</span>
                        <div class="text-lg font-semibold text-gray-800">{{ number_format($plotSize, 2) }} {{ $measurementUnit }}</div>
                    </div>
                    <div>
                        <span class="text-sm text-gray-600">Price per Unit:</span>
                        <div class="text-lg font-semibold text-gray-800">₹{{ number_format($pricePerUnit, 2) }}</div>
                    </div>
                </div>
                @endif
                <div class="space-y-2">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700 font-medium">Total Property Value:</span>
                        <span class="text-xl font-bold text-blue-900">₹{{ number_format($totalPlotValue, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700">Total Paid So Far:</span>
                        <span class="text-lg font-semibold text-gray-800">₹{{ number_format($totalPaid, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700">This Request Amount:</span>
                        <span class="text-lg font-semibold text-primary-600">₹{{ number_format($paymentRequest->amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 bg-gray-50 rounded-lg px-4 mt-3">
                        <span class="text-gray-800 font-bold">Remaining After This Request:</span>
                        <span class="text-2xl font-bold {{ $remainingAfterThis > 0 ? 'text-orange-600' : 'text-green-600' }}">
                            ₹{{ number_format($remainingAfterThis, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Broker Commission Calculation Section -->
        @if($plotSize > 0 && $brokerCommission > 0)
        <div class="mt-6 bg-purple-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <i class="fas fa-hand-holding-usd text-purple-700 text-xl mr-3"></i>
                <h4 class="text-lg font-semibold text-purple-900">Broker Commission Calculation</h4>
            </div>
            <div class="bg-white rounded-lg p-4 space-y-3">
                <div class="grid grid-cols-2 gap-4 pb-3 border-b">
                    <div>
                        <span class="text-sm text-gray-600">Broker Name:</span>
                        <div class="text-lg font-semibold text-gray-800">{{ $paymentRequest->user->name ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <span class="text-sm text-gray-600">Current Slab:</span>
                        <div class="text-lg font-semibold text-purple-700">{{ $brokerSlabName }}</div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700">Property Type:</span>
                        <span class="font-semibold text-gray-800">{{ $propertyTypeName }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700">Property Size:</span>
                        <span class="font-semibold text-gray-800">{{ number_format($plotSize, 2) }} {{ $measurementUnit }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-700">Commission Rate ({{ $brokerSlabName }}):</span>
                        <span class="font-semibold text-gray-800">₹{{ number_format($fixedAmountPerUnit, 2) }} / {{ $measurementUnit }}</span>
                    </div>
                    @if(isset($progressiveBreakdown) && is_array($progressiveBreakdown) && count($progressiveBreakdown) > 0)
                        <!-- Progressive Commission Breakdown -->
                        <div class="mt-4 bg-white rounded-lg p-4 border border-purple-200">
                            <h5 class="font-semibold text-purple-900 mb-3">
                                <i class="fas fa-layer-group mr-2"></i>Progressive Commission Breakdown
                            </h5>
                            <div class="text-xs text-gray-600 mb-3 space-y-1">
                                <div>Total Volume Before This Sale: <strong>{{ number_format($totalVolumeBeforeSale ?? 0, 2) }} {{ $measurementUnit }}</strong></div>
                                @if(isset($allocatedAmountForDisplay) && $allocatedAmountForDisplay > 0)
                                <div>Allocated Amount Per Unit: <strong>₹{{ number_format($allocatedAmountForDisplay, 2) }}</strong></div>
                                @endif
                            </div>
                            <div class="space-y-2">
                                @foreach($progressiveBreakdown as $tier)
                                    <div class="bg-gray-50 rounded p-3 border-l-4 border-purple-400">
                                        <div class="flex justify-between items-start mb-1">
                                            <div>
                                                <span class="font-semibold text-purple-700">{{ $tier['slab_name'] ?? 'N/A' }}</span>
                                                <span class="text-xs text-gray-600 ml-2">({{ $tier['volume_range'] ?? 'N/A' }})</span>
                                            </div>
                                            <span class="font-bold text-purple-700">₹{{ number_format($tier['commission'] ?? 0, 2) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            {{ number_format($tier['area_in_tier'] ?? 0, 2) }} {{ $measurementUnit }} × ₹{{ number_format($tier['commission_per_unit'] ?? 0, 2) }} = ₹{{ number_format($tier['commission'] ?? 0, 2) }}
                                            <span class="text-gray-500 ml-2">({{ number_format($tier['commission_percentage'] ?? 0, 2) }}% of allocated amount)</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="bg-purple-50 rounded-lg p-4 mt-3 border-2 border-purple-300">
                        <div class="flex justify-between items-center">
                            <span class="text-purple-900 font-bold text-lg">Total Progressive Commission:</span>
                            <span class="text-3xl font-bold text-purple-700">
                                ₹{{ number_format($brokerCommission, 2) }}
                            </span>
                        </div>
                        @if(isset($progressiveBreakdown) && count($progressiveBreakdown) > 1)
                            <div class="mt-2 text-sm text-purple-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                Calculated using progressive tier system based on volume sold
                            </div>
                        @else
                            <div class="mt-2 text-sm text-purple-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                Formula: {{ number_format($plotSize, 2) }} {{ $measurementUnit }} × ₹{{ number_format($fixedAmountPerUnit, 2) }} = ₹{{ number_format($brokerCommission, 2) }}
                            </div>
                        @endif

                        @if($paymentRequest->status === 'approved' && ($earnedCommissionTotal ?? 0) > 0)
                            <div class="mt-4 bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-indigo-900 font-bold">Earned Commission (Released So Far):</span>
                                    <span class="text-3xl font-bold text-indigo-700">
                                        ₹{{ number_format($earnedCommissionReleased ?? 0, 2) }}
                                    </span>
                                </div>
                                <div class="text-xs text-indigo-700 mt-2">
                                    Released {{ number_format($earnedCommissionPercent ?? 0, 2) }}% of total ₹{{ number_format($earnedCommissionTotal, 2) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Referral Commission Breakdown Section -->
        @if(isset($commissionPreview) && is_array($commissionPreview) && !empty($commissionPreview) && count($commissionPreview) > 1)
        @php
            $referralCommissions = array_filter($commissionPreview, function($c) { return isset($c['level']) && $c['level'] > 1; });
            $totalReferralCommission = array_sum(array_column($referralCommissions, 'commission_amount'));
            $level1Commission = $commissionPreview[1]['commission_amount'] ?? 0;
            $allocatedAmount = $commissionPreview[1]['allocated_amount'] ?? 0;
            $areaSold = $commissionPreview[1]['area_sold'] ?? 0;
            $childSlabPercentage = $commissionPreview[1]['commission_percentage'] ?? 0;
            
            // Get pool info from commission preview (if available)
            $poolInfo = $commissionPreview['_pool_info'] ?? null;
            if ($poolInfo) {
                $totalReferralPool = $poolInfo['pool_total'] ?? 0;
                $referralPoolPerUnit = $poolInfo['pool_per_unit'] ?? 0;
            } else {
                // Fallback calculation (assumes per unit)
                $referralPoolPerUnit = $allocatedAmount - ($allocatedAmount * $childSlabPercentage / 100);
                $totalReferralPool = $referralPoolPerUnit * $areaSold;
            }
        @endphp
        <div class="mt-6 bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-300 rounded-lg p-6 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <i class="fas fa-users text-green-700 text-2xl mr-3"></i>
                    <h4 class="text-xl font-bold text-green-900">Referral Income Distribution Preview</h4>
                </div>
                <span class="px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-full">
                    {{ count($referralCommissions) }} Parent{{ count($referralCommissions) > 1 ? 's' : '' }} Will Receive Commission
                </span>
            </div>
            
            <!-- Summary Card -->
            <div class="bg-white rounded-lg p-5 mb-4 border-2 border-green-200 shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                        <div class="text-xs text-blue-600 font-medium mb-1">Direct Commission (Level 1)</div>
                        <div class="text-lg font-bold text-blue-900">₹{{ number_format($level1Commission, 2) }}</div>
                        <div class="text-xs text-blue-600 mt-1">{{ $commissionPreview[1]['user_name'] ?? 'N/A' }} ({{ $commissionPreview[1]['slab_name'] ?? 'N/A' }})</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                        <div class="text-xs text-green-600 font-medium mb-1">Total Referral Commission</div>
                        <div class="text-lg font-bold text-green-700">₹{{ number_format($totalReferralCommission, 2) }}</div>
                        <div class="text-xs text-green-600 mt-1">Distributed to {{ count($referralCommissions) }} parent(s)</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 border border-purple-200">
                        <div class="text-xs text-purple-600 font-medium mb-1">Referral Pool Available</div>
                        <div class="text-lg font-bold text-purple-700">₹{{ number_format($totalReferralPool, 2) }}</div>
                        <div class="text-xs text-purple-600 mt-1">₹{{ number_format($referralPoolPerUnit, 2) }} per {{ $measurementUnit }}</div>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-3">
                    <p class="text-sm text-yellow-800 mb-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>How it works:</strong> When you approve this payment, referral commission will be distributed to parents based on slab difference. 
                        Parent gets commission only if their slab is higher than child's slab.
                    </p>
                </div>
            </div>

            <!-- Detailed Breakdown -->
            <div class="bg-white rounded-lg p-4">
                <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-list-ul mr-2 text-green-600"></i>
                    Detailed Referral Commission Breakdown:
                </h5>
                <div class="space-y-3">
                    @foreach($commissionPreview as $level => $commission)
                        @if($level == 1)
                            @continue
                        @endif
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-4 border-l-4 border-green-500 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="px-3 py-1 bg-green-600 text-white text-sm font-bold rounded-full">Level {{ $level }}</span>
                                        <span class="text-base font-bold text-gray-800">{{ $commission['user_name'] ?? 'N/A' }}</span>
                                        <span class="text-xs text-gray-500">({{ $commission['broker_id'] ?? 'N/A' }})</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mt-2">
                                        <div><span class="font-semibold">Referral Code:</span> {{ $commission['referral_code'] ?? 'N/A' }}</div>
                                        <div><span class="font-semibold">Parent Slab:</span> <span class="text-green-700 font-bold">{{ $commission['slab_name'] ?? 'N/A' }}</span> ({{ number_format($commission['commission_percentage'] ?? 0, 2) }}%)</div>
                                        @if(isset($commission['child_slab_name']))
                                        <div><span class="font-semibold">Child Slab:</span> {{ $commission['child_slab_name'] }} ({{ number_format($commission['child_slab_percentage'] ?? 0, 2) }}%)</div>
                                        <div><span class="font-semibold">Slab Difference:</span> <span class="text-green-700 font-bold">{{ number_format($commission['slab_difference_percentage'] ?? 0, 2) }}%</span></div>
                                        @endif
                                        <div><span class="font-semibold">Area Sold:</span> {{ number_format($commission['area_sold'] ?? 0, 2) }} {{ $measurementUnit }}</div>
                                        @if(isset($commission['allocated_amount']))
                                        <div><span class="font-semibold">Allocated Amount:</span> ₹{{ number_format($commission['allocated_amount'], 2) }} / {{ $measurementUnit }}</div>
                                        @endif
                                    </div>
                                    @if(isset($commission['slab_difference_percentage']) && isset($commission['allocated_amount']) && isset($commission['area_sold']))
                                    <div class="mt-2 text-xs text-gray-500 bg-white rounded p-2 border border-gray-200">
                                        <i class="fas fa-calculator mr-1"></i>
                                        <strong>Calculation:</strong> ({{ number_format($commission['slab_difference_percentage'], 2) }}% × ₹{{ number_format($commission['allocated_amount'], 2) }} × {{ number_format($commission['area_sold'], 2) }} {{ $measurementUnit }}) = ₹{{ number_format($commission['commission_amount'] ?? 0, 2) }}
                                    </div>
                                    @endif
                                    @if(isset($commission['pool_remaining_per_unit']))
                                    <div class="mt-1 text-xs text-gray-500">
                                        <i class="fas fa-coins mr-1"></i>
                                        Pool Remaining: ₹{{ number_format($commission['pool_remaining_per_unit'], 2) }} per {{ $measurementUnit }}
                                    </div>
                                    @endif
                                </div>
                                <div class="text-right ml-4 flex-shrink-0">
                                    <div class="text-3xl font-bold text-green-700 mb-1">
                                        ₹{{ number_format($commission['commission_amount'] ?? 0, 2) }}
                                    </div>
                                    <div class="text-xs text-gray-600 bg-green-100 rounded px-2 py-1">
                                        @if(isset($commission['commission_per_unit']))
                                            ₹{{ number_format($commission['commission_per_unit'], 2) }} / {{ $measurementUnit }}
                                        @endif
                                    </div>
                                    @if(isset($commission['deserved_commission']) && abs($commission['deserved_commission'] - $commission['commission_amount']) > 0.01)
                                    <div class="mt-2 text-xs text-orange-600 bg-orange-50 rounded px-2 py-1 border border-orange-200">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Pool Limited
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t-2 border-green-300 bg-green-50 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-green-900 font-bold text-lg">Total Referral Commission to be Distributed:</span>
                        <span class="text-3xl font-bold text-green-700">
                            ₹{{ number_format($totalReferralCommission, 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @else
        @php
            // Calculate referral pool info even when no referral commissions
            $level1Commission = isset($commissionPreview[1]) ? ($commissionPreview[1]['commission_amount'] ?? 0) : $brokerCommission;
            $allocatedAmount = isset($commissionPreview[1]) ? ($commissionPreview[1]['allocated_amount'] ?? 0) : $allocatedAmountForDisplay;
            $areaSold = isset($commissionPreview[1]) ? ($commissionPreview[1]['area_sold'] ?? 0) : $plotSize;
            $childSlabPercentage = isset($commissionPreview[1]) ? ($commissionPreview[1]['commission_percentage'] ?? 0) : ($fixedAmountPerUnit > 0 && $allocatedAmount > 0 ? ($fixedAmountPerUnit / $allocatedAmount * 100) : 0);
            
            // Get pool info from commission preview (if available)
            $poolInfo = $commissionPreview['_pool_info'] ?? null;
            if ($poolInfo) {
                $totalReferralPool = $poolInfo['pool_total'] ?? 0;
                $referralPoolPerUnit = $poolInfo['pool_per_unit'] ?? 0;
            } else {
                // Fallback calculation (assumes per unit)
                $referralPoolPerUnit = $allocatedAmount > 0 ? ($allocatedAmount - ($allocatedAmount * $childSlabPercentage / 100)) : 0;
                $totalReferralPool = $referralPoolPerUnit * $areaSold;
            }
            
            $userName = isset($commissionPreview[1]) ? ($commissionPreview[1]['user_name'] ?? $paymentRequest->user->name) : $paymentRequest->user->name;
            $slabName = isset($commissionPreview[1]) ? ($commissionPreview[1]['slab_name'] ?? $brokerSlabName) : $brokerSlabName;
        @endphp
        <div class="mt-6 bg-blue-50 border-2 border-blue-300 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <i class="fas fa-users text-blue-700 text-xl mr-3"></i>
                    <h4 class="text-lg font-semibold text-blue-900">Referral Income Distribution Preview</h4>
                </div>
                <span class="px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-full">
                    No Referral Commission
                </span>
            </div>
            
            <!-- Summary Card -->
            <div class="bg-white rounded-lg p-5 mb-4 border-2 border-blue-200 shadow-md">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                        <div class="text-xs text-blue-600 font-medium mb-1">Direct Commission (Level 1)</div>
                        <div class="text-lg font-bold text-blue-900">₹{{ number_format($level1Commission, 2) }}</div>
                        <div class="text-xs text-blue-600 mt-1">{{ $userName }} ({{ $slabName }})</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                        <div class="text-xs text-green-600 font-medium mb-1">Total Referral Commission</div>
                        <div class="text-lg font-bold text-green-700">₹0.00</div>
                        <div class="text-xs text-green-600 mt-1">No parents eligible</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 border border-purple-200">
                        <div class="text-xs text-purple-600 font-medium mb-1">Referral Pool Available</div>
                        <div class="text-lg font-bold text-purple-700">₹{{ number_format($totalReferralPool, 2) }}</div>
                        <div class="text-xs text-purple-600 mt-1">₹{{ number_format($referralPoolPerUnit, 2) }} per {{ $measurementUnit }}</div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 border border-blue-200">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 text-lg mr-3 mt-1"></i>
                    <div>
                        <p class="text-sm font-semibold text-blue-900 mb-2">No referral commission will be distributed when this payment is approved.</p>
                        <p class="text-xs text-blue-700 mb-2">Possible reasons:</p>
                        <ul class="list-disc list-inside text-xs text-blue-700 space-y-1 ml-4">
                            <li>The booking user (<strong>{{ $userName }}</strong>) has no active referral chain (no parent referrer)</li>
                            <li>All parents in the referral chain are at the same or lower slab than the booking user's slab (<strong>{{ $slabName }}</strong>)</li>
                            <li>The referral pool (₹{{ number_format($totalReferralPool, 2) }}) will remain unused</li>
                        </ul>
                        <p class="text-xs text-blue-600 mt-3">
                            <strong>Note:</strong> Referral commission is only distributed when parent's slab is higher than child's slab (slab difference > 0).
                        </p>
                        @if($totalReferralPool > 0)
                        <div class="mt-3 bg-yellow-50 border border-yellow-300 rounded-lg p-3">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Referral Pool:</strong> ₹{{ number_format($totalReferralPool, 2) }} is available but will not be distributed as there are no eligible parents in the referral chain.
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
        
        @elseif($plotSize > 0 && $brokerCommission == 0)
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-triangle text-yellow-700 text-xl mr-3"></i>
                <h4 class="text-lg font-semibold text-yellow-900">Broker Commission</h4>
            </div>
            <div class="bg-white rounded-lg p-4">
                <p class="text-sm text-yellow-800 mb-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Commission calculation unavailable. Please check the following:
                </p>
                <ul class="list-disc list-inside text-sm text-yellow-800 space-y-1">
                    @if(!$userSlab ?? false)
                        <li>The broker does not have an active slab assigned.</li>
                    @endif
                    @if($propertyTypeName)
                        <li>Allocated amount is not configured for property type "{{ $propertyTypeName }}" in the project settings.</li>
                        <li>Commission rates may not be configured for "{{ $propertyTypeName }}" in Settings → MLM Commission Structure.</li>
                    @else
                        <li>Property type not found or not configured.</li>
                    @endif
                    <li>Slabs may not be associated with property type "{{ $propertyTypeName }}".</li>
                </ul>
                <p class="text-xs text-yellow-700 mt-3">
                    <strong>To fix:</strong> Go to Projects → Edit Project → Configure "Allocated Amount Configuration" for {{ $propertyTypeName ?? 'this property type' }}.
                </p>
            </div>
        </div>
        @endif

        <!-- Payment Method Details -->
        @if($paymentRequest->paymentMethod && is_array($paymentRequest->paymentMethod->details))
        <div class="mt-6 bg-blue-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-700 mb-3">Payment Method Details</h4>
            <div class="text-sm space-y-1">
                @if(isset($paymentRequest->paymentMethod->details['upi_id']))
                    <div><span class="text-gray-600">UPI ID:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['upi_id'] }}</span></div>
                @endif
                @if(isset($paymentRequest->paymentMethod->details['account_number']))
                    <div><span class="text-gray-600">Account Number:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['account_number'] }}</span></div>
                @endif
                @if(isset($paymentRequest->paymentMethod->details['ifsc']))
                    <div><span class="text-gray-600">IFSC:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['ifsc'] }}</span></div>
                @endif
                @if(isset($paymentRequest->paymentMethod->details['bank_name']))
                    <div><span class="text-gray-600">Bank Name:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['bank_name'] }}</span></div>
                @endif
                @if(isset($paymentRequest->paymentMethod->details['account_holder']))
                    <div><span class="text-gray-600">Account Holder:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['account_holder'] }}</span></div>
                @endif
                @if(isset($paymentRequest->paymentMethod->details['text']))
                    <div><span class="text-gray-600">Details:</span> <span class="font-medium">{{ $paymentRequest->paymentMethod->details['text'] }}</span></div>
                @endif
            </div>
        </div>
        @endif

        <!-- Booked by Other Message -->
        @if($paymentRequest->status === 'booked_by_other')
        <div class="mt-6 bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-lg font-semibold text-red-800 mb-2">Property Booked by Another User</h4>
                    <p class="text-red-700 mb-3">
                        This property was successfully booked by another user. The payment request for this user has been automatically updated.
                    </p>
                    @if($paymentRequest->admin_notes)
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <p class="text-sm font-medium text-red-800 mb-1">Refund Information:</p>
                        <p class="text-sm text-red-700">{{ $paymentRequest->admin_notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Rejected Message -->
        @if($paymentRequest->status === 'rejected')
        <div class="mt-6 bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-lg font-semibold text-red-800 mb-2">Payment Request Rejected</h4>
                    <p class="text-red-700 mb-3">
                        This payment request has been rejected by admin.
                    </p>
                    @if($paymentRequest->admin_notes)
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <p class="text-sm font-medium text-red-800 mb-1">Rejection Reason:</p>
                        <p class="text-sm text-red-700">{{ $paymentRequest->admin_notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Actions -->
        @if($paymentRequest->status === 'pending')
        <div class="mt-6 pt-6 border-t">
            <h4 class="font-semibold text-gray-700 mb-4">Actions</h4>
            <form id="approveForm" method="POST" action="{{ route('admin.payment-requests.approve', $paymentRequest->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Admin Notes (Optional)</label>
                    <textarea 
                        name="admin_notes" 
                        rows="3"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                        placeholder="Add any notes about this payment request..."
                    ></textarea>
                </div>
                <div class="flex space-x-4">
                    <button 
                        type="submit" 
                        onclick="return confirm('Are you sure you want to approve this payment request? This will book the plot and distribute commissions.');"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                    >
                        <i class="fas fa-check mr-2"></i>Approve Payment
                    </button>
                    <button 
                        type="button"
                        onclick="showRejectForm()"
                        class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    >
                        <i class="fas fa-times mr-2"></i>Reject Payment
                    </button>
                </div>
            </form>

            <form id="rejectForm" method="POST" action="{{ route('admin.payment-requests.reject', $paymentRequest->id) }}" class="hidden mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea 
                        name="admin_notes" 
                        rows="3"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" 
                        placeholder="Please provide a reason for rejection..."
                        required
                    ></textarea>
                </div>
                <div class="flex space-x-4">
                    <button 
                        type="submit" 
                        onclick="return confirm('Are you sure you want to reject this payment request?');"
                        class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    >
                        <i class="fas fa-times mr-2"></i>Confirm Rejection
                    </button>
                    <button 
                        type="button"
                        onclick="hideRejectForm()"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function showRejectForm() {
        const approveForm = document.getElementById('approveForm');
        const rejectForm = document.getElementById('rejectForm');
        if (approveForm && rejectForm) {
            approveForm.classList.add('hidden');
            rejectForm.classList.remove('hidden');
        }
    }

    function hideRejectForm() {
        const approveForm = document.getElementById('approveForm');
        const rejectForm = document.getElementById('rejectForm');
        if (approveForm && rejectForm) {
            approveForm.classList.remove('hidden');
            rejectForm.classList.add('hidden');
        }
    }
</script>
@endpush
@endsection

