@extends('admin.layouts.app')

@section('title', 'User Details')
@section('page-title', 'User Details')

@section('content')
<div class="user-details-page grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 min-w-0">
        <div class="bg-white rounded-2xl shadow-3d p-6 space-y-4">
            <div class="flex items-center space-x-4">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-2xl">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">{{ $user->name }}</h3>
                    <p class="text-sm text-gray-500">{{ ucfirst($user->user_type ?? 'user') }}</p>
                    <p class="text-xs text-gray-400">Status: <span class="uppercase">{{ $user->status }}</span></p>
                </div>
            </div>
            <div class="space-y-2 text-sm text-gray-700">
                <p><i class="fas fa-id-badge w-5 inline-block text-gray-400"></i> Broker ID: {{ $user->broker_id ?? '—' }}</p>
                <p><i class="fas fa-code w-5 inline-block text-gray-400"></i> Referral Code: <span class="font-semibold text-primary-600">{{ $user->referral_code ?? '—' }}</span></p>
                <p><i class="fas fa-envelope w-5 inline-block text-gray-400"></i> Email: {{ $user->email ?? '—' }}</p>
                <p><i class="fas fa-phone w-5 inline-block text-gray-400"></i> Phone: {{ $user->phone_number ?? '—' }}</p>
                @if($user->address)
                    <p><i class="fas fa-map-marker-alt w-5 inline-block text-gray-400"></i> Address: {{ $user->address }}</p>
                    @if($user->city || $user->state || $user->pincode)
                        <p class="ml-5 text-xs text-gray-500">{{ $user->city }}{{ $user->city && $user->state ? ', ' : '' }}{{ $user->state }}{{ ($user->city || $user->state) && $user->pincode ? ' - ' : '' }}{{ $user->pincode }}</p>
                    @endif
                @endif
                <p><i class="fas fa-layer-group w-5 inline-block text-gray-400"></i> Slab: <span class="font-semibold">{{ optional($user->slab)->name ?? 'Slab1' }}</span></p>
                <p><i class="fas fa-user-check w-5 inline-block text-gray-400"></i> KYC: 
                    @if($user->kyc_verified)
                        <span class="text-green-600 font-semibold">Verified</span>
                    @else
                        <span class="text-orange-600 font-semibold">Pending</span>
                    @endif
                </p>
                @if($user->referredBy)
                    @php
                        $referredById = is_object($user->referredBy) ? $user->referredBy->id : (is_array($user->referredBy) ? ($user->referredBy['id'] ?? $user->referredBy['referred_by_user_id'] ?? null) : $user->referred_by_user_id ?? null);
                        $referredByName = is_object($user->referredBy) ? $user->referredBy->name : (is_array($user->referredBy) ? ($user->referredBy['name'] ?? 'N/A') : 'N/A');
                        $referredByCode = is_object($user->referredBy) ? $user->referredBy->referral_code : (is_array($user->referredBy) ? ($user->referredBy['referral_code'] ?? 'N/A') : 'N/A');
                    @endphp
                    @if($referredById)
                        <p><i class="fas fa-user-tie w-5 inline-block text-gray-400"></i> Referred By: <a href="{{ route('admin.users.show', $referredById) }}" class="text-primary-600 hover:underline">{{ $referredByName }} ({{ $referredByCode }})</a></p>
                    @endif
                @else
                    <p><i class="fas fa-user-tie w-5 inline-block text-gray-400"></i> Referred By: <span class="text-gray-500">Not referred</span></p>
                @endif
                
                <!-- Change Referral Code Section -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-gray-700"><i class="fas fa-exchange-alt w-5 inline-block text-gray-400"></i> Change Sponsor</p>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" id="newReferralCode" placeholder="Enter new referral code" class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <button onclick="updateReferralCode({{ $user->id }})" class="px-4 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-sync-alt mr-1"></i> Update
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">This will shift this user and their entire downline to the new sponsor.</p>
                </div>
                <p><i class="fas fa-users w-5 inline-block text-gray-400"></i> Direct Referrals: <span class="font-semibold">{{ $user->referrals->count() }}</span></p>
                <p><i class="fas fa-calendar-alt w-5 inline-block text-gray-400"></i> Joined: {{ optional($user->created_at)->format('d M Y, h:i A') }}</p>
            </div>
            <div class="pt-4">
                <a href="{{ route('admin.users') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Users
                </a>
            </div>
        </div>
    </div>
    <div class="lg:col-span-2 space-y-6 min-w-0">
        <!-- Tabs -->
        <div class="bg-white rounded-2xl shadow-3d p-4">
            <div class="flex flex-wrap gap-2" role="tablist">
                <button class="tab-btn px-4 py-2 rounded-lg bg-primary-100 text-primary-700 font-medium" data-tab="#tab-overview">Overview</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-kyc">KYC</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-transactions">Transactions</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-commission">Commission</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-payments">Payments</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-downline">Downline</button>
                <button class="tab-btn px-4 py-2 rounded-lg bg-gray-100 text-gray-700" data-tab="#tab-slab-history">Slab History</button>
            </div>
        </div>

        <!-- Overview -->
        <div id="tab-overview" class="bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Wallet Overview</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="p-4 rounded-xl bg-primary-50">
                    <p class="text-gray-500">Balance</p>
                    <p class="text-xl font-bold text-primary-700">₹{{ number_format(optional($user->wallet)->balance ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-green-50">
                    <p class="text-gray-500">Total Deposits</p>
                    <p class="text-xl font-bold text-green-700">₹{{ number_format(optional($user->wallet)->total_deposited ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-yellow-50">
                    <p class="text-gray-500">Total Withdrawals</p>
                    <p class="text-xl font-bold text-yellow-700">₹{{ number_format(optional($user->wallet)->total_withdrawn ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-blue-50">
                    <p class="text-gray-500">Commission</p>
                    <p class="text-xl font-bold text-blue-700">₹{{ number_format($user->total_commission_earned ?? 0, 2) }}</p>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="p-4 rounded-xl bg-gray-50">
                    <p class="text-gray-500 mb-1">Current Slab</p>
                    <p class="text-lg font-semibold">{{ optional($user->slab)->name ?? 'Slab1' }}</p>
                    <p class="text-xs text-gray-500">Business Volume: ₹{{ number_format($user->total_business_volume ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-gray-50">
                    <p class="text-gray-500 mb-1">KYC Status</p>
                    <p class="text-lg font-semibold">{{ $user->kyc_verified ? 'Verified' : 'Pending' }}</p>
                </div>
            </div>
        </div>

        <!-- KYC -->
        <div id="tab-kyc" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">KYC Documents</h4>
            @php
                $kycDoc = $user->kycDocument;
            @endphp
            @if($kycDoc)
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 mb-1">Status</p>
                            <p class="font-semibold">
                                @if($kycDoc->status == 'verified')
                                    <span class="text-green-600">Verified</span>
                                @elseif($kycDoc->status == 'rejected')
                                    <span class="text-red-600">Rejected</span>
                                @else
                                    <span class="text-orange-600">Pending</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 mb-1">Submitted On</p>
                            <p class="font-semibold">{{ $kycDoc->created_at ? $kycDoc->created_at->format('d M Y, h:i A') : '—' }}</p>
                        </div>
                        @if($kycDoc->verified_at)
                            <div>
                                <p class="text-gray-500 mb-1">Verified On</p>
                                <p class="font-semibold">{{ $kycDoc->verified_at->format('d M Y, h:i A') }}</p>
                            </div>
                        @endif
                    </div>
                    @if($kycDoc->rejection_reason)
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm font-medium text-red-700 mb-1">Rejection Reason</p>
                            <p class="text-red-900">{{ $kycDoc->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- PAN Card -->
                    <div class="border rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">PAN Card</p>
                        <p class="text-sm text-gray-500 mb-2">PAN Number: <span class="font-semibold text-gray-900">{{ $kycDoc->pan_number ?? '—' }}</span></p>
                        @php
                            $panPath = $kycDoc->pan_image_path 
                                ? (str_starts_with($kycDoc->pan_image_path, 'http') 
                                    ? $kycDoc->pan_image_path 
                                    : url($kycDoc->pan_image_path))
                                : null;
                        @endphp
                        @if($panPath)
                            <a href="{{ $panPath }}" target="_blank" class="block">
                                <img src="{{ $panPath }}" alt="PAN Card" class="w-full h-48 object-contain rounded-lg border bg-gray-50">
                            </a>
                            <a href="{{ $panPath }}" target="_blank" class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                <i class="fas fa-external-link-alt mr-1"></i>View Full Size
                            </a>
                        @else
                            <div class="w-full h-48 bg-gray-100 rounded-lg border flex items-center justify-center">
                                <p class="text-gray-400">Not uploaded</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Aadhaar Front -->
                    <div class="border rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Aadhaar Front</p>
                        <p class="text-sm text-gray-500 mb-2">Aadhaar Number: <span class="font-semibold text-gray-900">{{ $kycDoc->aadhaar_number ?? '—' }}</span></p>
                        @php
                            $aadhaarFrontPath = $kycDoc->aadhaar_front_image_path 
                                ? (str_starts_with($kycDoc->aadhaar_front_image_path, 'http') 
                                    ? $kycDoc->aadhaar_front_image_path 
                                    : url($kycDoc->aadhaar_front_image_path))
                                : null;
                        @endphp
                        @if($aadhaarFrontPath)
                            <a href="{{ $aadhaarFrontPath }}" target="_blank" class="block">
                                <img src="{{ $aadhaarFrontPath }}" alt="Aadhaar Front" class="w-full h-48 object-contain rounded-lg border bg-gray-50">
                            </a>
                            <a href="{{ $aadhaarFrontPath }}" target="_blank" class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                <i class="fas fa-external-link-alt mr-1"></i>View Full Size
                            </a>
                        @else
                            <div class="w-full h-48 bg-gray-100 rounded-lg border flex items-center justify-center">
                                <p class="text-gray-400">Not uploaded</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Aadhaar Back -->
                    <div class="border rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Aadhaar Back</p>
                        @php
                            $aadhaarBackPath = $kycDoc->aadhaar_back_image_path 
                                ? (str_starts_with($kycDoc->aadhaar_back_image_path, 'http') 
                                    ? $kycDoc->aadhaar_back_image_path 
                                    : url($kycDoc->aadhaar_back_image_path))
                                : null;
                        @endphp
                        @if($aadhaarBackPath)
                            <a href="{{ $aadhaarBackPath }}" target="_blank" class="block">
                                <img src="{{ $aadhaarBackPath }}" alt="Aadhaar Back" class="w-full h-48 object-contain rounded-lg border bg-gray-50">
                            </a>
                            <a href="{{ $aadhaarBackPath }}" target="_blank" class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                <i class="fas fa-external-link-alt mr-1"></i>View Full Size
                            </a>
                        @else
                            <div class="w-full h-48 bg-gray-100 rounded-lg border flex items-center justify-center">
                                <p class="text-gray-400">Not uploaded</p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No KYC Documents Found</h3>
                    <p class="text-gray-500">This user has not submitted any KYC documents yet.</p>
                </div>
            @endif
        </div>

        <!-- Transactions -->
        <div id="tab-transactions" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">All Transactions</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Before</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            // Ensure paymentRequests is set
                            $paymentRequests = $paymentRequests ?? collect();
                            
                            // Combine transactions and payment requests
                            $allTransactions = collect($user->transactions ?? [])->map(function($txn) {
                                // Handle both object and array cases
                                if (is_array($txn)) {
                                    return [
                                        'id' => $txn['id'] ?? null,
                                        'transaction_id' => $txn['transaction_id'] ?? 'N/A',
                                        'type' => $txn['type'] ?? 'N/A',
                                        'amount' => $txn['amount'] ?? 0,
                                        'status' => $txn['status'] ?? 'N/A',
                                        'description' => $txn['description'] ?? '',
                                        'balance_before' => $txn['balance_before'] ?? null,
                                        'balance_after' => $txn['balance_after'] ?? null,
                                        'created_at' => isset($txn['created_at']) ? \Carbon\Carbon::parse($txn['created_at']) : null,
                                        'processed_at' => isset($txn['processed_at']) ? \Carbon\Carbon::parse($txn['processed_at']) : null,
                                        'is_payment_request' => false,
                                    ];
                                }
                                return [
                                    'id' => is_object($txn) ? ($txn->id ?? null) : null,
                                    'transaction_id' => is_object($txn) ? ($txn->transaction_id ?? 'N/A') : 'N/A',
                                    'type' => is_object($txn) ? ($txn->type ?? 'N/A') : 'N/A',
                                    'amount' => is_object($txn) ? ($txn->amount ?? 0) : 0,
                                    'status' => is_object($txn) ? ($txn->status ?? 'N/A') : 'N/A',
                                    'description' => is_object($txn) ? ($txn->description ?? '') : '',
                                    'balance_before' => is_object($txn) ? ($txn->balance_before ?? null) : null,
                                    'balance_after' => is_object($txn) ? ($txn->balance_after ?? null) : null,
                                    'created_at' => is_object($txn) ? ($txn->created_at ?? null) : null,
                                    'processed_at' => is_object($txn) ? ($txn->processed_at ?? null) : null,
                                    'is_payment_request' => false,
                                ];
                            })->filter(function($txn) {
                                return !is_null($txn['id']);
                            });
                            
                            // Add payment requests as transactions
                            $paymentRequestTransactions = collect($paymentRequests ?? [])->map(function($pr) {
                                // Handle both object and array cases
                                $prId = is_object($pr) ? ($pr->id ?? null) : (is_array($pr) ? ($pr['id'] ?? null) : null);
                                if (!$prId) return null;
                                
                                $plot = is_object($pr) ? ($pr->plot ?? null) : null;
                                $project = $plot && is_object($plot) ? ($plot->project ?? null) : null;
                                $plotType = $plot && is_object($plot) ? ($plot->type ?? 'N/A') : 'N/A';
                                $plotNumber = $plot && is_object($plot) ? ($plot->plot_number ?? 'N/A') : 'N/A';
                                $projectName = $project && is_object($project) ? ($project->name ?? 'N/A') : 'N/A';
                                
                                // Map payment request status to transaction-like status
                                $status = is_object($pr) ? ($pr->status ?? 'pending') : (is_array($pr) ? ($pr['status'] ?? 'pending') : 'pending');
                                
                                // Create description based on status
                                $description = "Payment request for {$plotType} {$plotNumber} - {$projectName}";
                                if ($status === 'rejected') {
                                    $description .= ' (Rejected)';
                                } elseif ($status === 'booked_by_other') {
                                    $description .= ' (Booked by Other)';
                                } elseif ($status === 'approved') {
                                    $description .= ' (Approved)';
                                }
                                
                                $adminNotes = is_object($pr) ? ($pr->admin_notes ?? null) : (is_array($pr) ? ($pr['admin_notes'] ?? null) : null);
                                if ($adminNotes) {
                                    $description .= ' - ' . $adminNotes;
                                }
                                
                                $amount = is_object($pr) ? ($pr->amount ?? 0) : (is_array($pr) ? ($pr['amount'] ?? 0) : 0);
                                $createdAt = is_object($pr) ? ($pr->created_at ?? null) : (is_array($pr) && isset($pr['created_at']) ? \Carbon\Carbon::parse($pr['created_at']) : null);
                                $processedAt = is_object($pr) ? ($pr->processed_at ?? null) : (is_array($pr) && isset($pr['processed_at']) ? \Carbon\Carbon::parse($pr['processed_at']) : null);
                                
                                return [
                                    'id' => 'pr_' . $prId,
                                    'transaction_id' => 'PR-' . str_pad($prId, 6, '0', STR_PAD_LEFT),
                                    'type' => 'payment_request',
                                    'amount' => $amount,
                                    'status' => $status,
                                    'description' => $description,
                                    'balance_before' => null,
                                    'balance_after' => null,
                                    'created_at' => $createdAt,
                                    'processed_at' => $processedAt,
                                    'is_payment_request' => true,
                                    'payment_request_id' => $prId,
                                ];
                            })->filter(function($item) {
                                return $item !== null;
                            });
                            
                            // Merge and sort by created_at descending
                            $allItems = $allTransactions->merge($paymentRequestTransactions)
                                ->sortByDesc(function($item) {
                                    return $item['created_at'];
                                })
                                ->values();
                        @endphp
                        @forelse($allItems as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $item['transaction_id'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @php
                                        $istDate = \Carbon\Carbon::parse($item['created_at'])->setTimezone(new \DateTimeZone('Asia/Kolkata'));
                                    @endphp
                                    <div>{{ $istDate->format('d M Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $istDate->format('h:i A') }} IST</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($item['type'] === 'deposit') bg-blue-100 text-blue-800
                                        @elseif($item['type'] === 'withdrawal') bg-red-100 text-red-800
                                        @elseif($item['type'] === 'commission') bg-green-100 text-green-800
                                        @elseif($item['type'] === 'bonus') bg-purple-100 text-purple-800
                                        @elseif($item['type'] === 'booking') bg-orange-100 text-orange-800
                                        @elseif($item['type'] === 'payment_request') bg-indigo-100 text-indigo-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        @if($item['type'] === 'payment_request')
                                            Payment Request
                                        @else
                                            {{ ucfirst($item['type'] ?? '-') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium 
                                    @if($item['type'] === 'deposit' || $item['type'] === 'commission' || $item['type'] === 'bonus') text-green-600
                                    @else text-red-600
                                    @endif">
                                    @if($item['type'] === 'withdrawal' || $item['type'] === 'booking' || $item['type'] === 'payment_request')-@endif₹{{ number_format($item['amount'] ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($item['balance_before'] !== null)
                                        ₹{{ number_format($item['balance_before'], 2) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($item['balance_after'] !== null)
                                        ₹{{ number_format($item['balance_after'], 2) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($item['status'] === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($item['status'] === 'completed' || $item['status'] === 'approved') bg-green-100 text-green-800
                                        @elseif($item['status'] === 'cancelled' || $item['status'] === 'rejected' || $item['status'] === 'booked_by_other') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        @if($item['status'] === 'booked_by_other')
                                            Booked by Other
                                        @else
                                            {{ ucfirst($item['status'] ?? '-') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $item['description'] ?? '-' }}
                                    @if($item['processed_at'])
                                        <div class="text-xs text-gray-400 mt-1">
                                            Processed: {{ \Carbon\Carbon::parse($item['processed_at'])->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A') }} IST
                                        </div>
                                    @endif
                                    @if($item['is_payment_request'] ?? false)
                                        <div class="text-xs text-indigo-600 mt-1">
                                            <a href="{{ route('admin.payment-requests.show', $item['payment_request_id']) }}" class="hover:underline">
                                                View Payment Request →
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Commission -->
        <div id="tab-commission" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Commission History</h4>
            @php
                // Ensure we have transactions loaded
                $allTransactions = $user->transactions ?? collect();
                $commissionTransactions = $allTransactions->filter(function($txn) {
                    return $txn->type === 'commission';
                });
                $monthStart = \Carbon\Carbon::now()->startOfMonth();
                $monthlyCommission = $commissionTransactions->filter(function($txn) use ($monthStart) {
                    return $txn->created_at >= $monthStart && ($txn->status ?? null) === 'completed';
                })->sum('amount');
                // Commission earned from downline (referral commissions) - from referral_commissions table
                $commissionFromDownline = (float) \App\Models\ReferralCommission::where('parent_user_id', $user->id)->sum('referral_commission_amount');
            @endphp
            <div class="mb-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div class="p-4 rounded-xl bg-blue-50">
                    <p class="text-gray-500">Total Commission</p>
                    <p class="text-xl font-bold text-blue-700">₹{{ number_format($user->total_commission_earned ?? 0, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-amber-50">
                    <p class="text-gray-500">From Downline</p>
                    <p class="text-xl font-bold text-amber-700">₹{{ number_format($commissionFromDownline, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-green-50">
                    <p class="text-gray-500">This Month</p>
                    <p class="text-xl font-bold text-green-700">₹{{ number_format($monthlyCommission, 2) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-purple-50">
                    <p class="text-gray-500">Total Transactions</p>
                    <p class="text-xl font-bold text-purple-700">{{ $commissionTransactions->count() }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time (IST)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($commissionTransactions->values() as $txn)
                            @php
                                // Parse metadata
                                $metadata = [];
                                if ($txn->metadata) {
                                    $metadata = is_string($txn->metadata) ? json_decode($txn->metadata, true) : $txn->metadata;
                                }
                                
                                // Convert to IST
                                $istDate = \Carbon\Carbon::parse($txn->created_at)->setTimezone(new \DateTimeZone('Asia/Kolkata'));
                                
                                $projectName = $metadata['project_name'] ?? null;
                                $plotNumber = $metadata['plot_number'] ?? null;
                                $plotType = $metadata['plot_type'] ?? null;
                                $location = $metadata['project_location'] ?? null;
                                $level = $metadata['level'] ?? null;
                                $bookingAmount = $metadata['booking_amount'] ?? null;
                                $customerName = $metadata['customer_name'] ?? null;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $istDate->format('d M Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $istDate->format('h:i A') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $txn->transaction_id ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    ₹{{ number_format($txn->amount ?? 0, 2) }}
                                    @if($level && $level > 1)
                                        <div class="text-xs text-purple-600">Level {{ $level }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($txn->status === 'completed') bg-green-100 text-green-800
                                        @elseif($txn->status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($txn->status ?? '-') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div class="font-medium">{{ $txn->description ?? '-' }}</div>
                                    @if($projectName)
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-building"></i> {{ $projectName }}
                                            @if($location) - {{ $location }} @endif
                                        </div>
                                    @endif
                                    @if($plotNumber || $plotType)
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-home"></i> {{ $plotType ?? '' }} {{ $plotNumber ?? '' }}
                                        </div>
                                    @endif
                                    @if($customerName)
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-user"></i> Customer: {{ $customerName }}
                                        </div>
                                    @endif
                                    @if($bookingAmount)
                                        <div class="text-xs text-gray-400 mt-1">
                                            <i class="fas fa-rupee-sign"></i> Booking: ₹{{ number_format($bookingAmount, 2) }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No commission transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments summary -->
        <div id="tab-payments" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Payments Summary</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6">
                @php
                    // Calculate incoming (deposit, commission, bonus)
                    $incoming = $user->transactions->filter(function($txn) {
                        return in_array($txn->type, ['deposit', 'commission', 'bonus']) && ($txn->status ?? null) === 'completed';
                    })->sum('amount');
                    
                    // Calculate outgoing (withdrawal, booking)
                    $outgoing = $user->transactions->filter(function($txn) {
                        return in_array($txn->type, ['withdrawal', 'booking']) && ($txn->status ?? null) === 'completed';
                    })->sum('amount');
                    
                    // Calculate bonus
                    $bonus = $user->transactions->where('type', 'bonus')->sum('amount');
                @endphp
                <div class="p-4 rounded-xl bg-green-50">
                    <p class="text-gray-500">Incoming</p>
                    <p class="text-xl font-bold text-green-700">₹{{ number_format($incoming, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Deposits + Commissions + Bonuses</p>
                </div>
                <div class="p-4 rounded-xl bg-yellow-50">
                    <p class="text-gray-500">Outgoing</p>
                    <p class="text-xl font-bold text-yellow-700">₹{{ number_format($outgoing, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Withdrawals + Bookings</p>
                </div>
                <div class="p-4 rounded-xl bg-blue-50">
                    <p class="text-gray-500">Bonus</p>
                    <p class="text-xl font-bold text-blue-700">₹{{ number_format($bonus, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total bonuses received</p>
                </div>
            </div>
            
            <!-- Detailed Payment List -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Direction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @php
                            $paymentTransactions = $user->transactions->sortByDesc('created_at')->values();
                        @endphp
                        @forelse($paymentTransactions as $txn)
                            @php
                                $isIncoming = in_array($txn->type, ['deposit', 'commission', 'bonus']);
                                $istDate = \Carbon\Carbon::parse($txn->created_at)->setTimezone(new \DateTimeZone('Asia/Kolkata'));
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $istDate->format('d M Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $istDate->format('h:i A') }} IST</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($txn->type === 'deposit') bg-blue-100 text-blue-800
                                        @elseif($txn->type === 'withdrawal') bg-red-100 text-red-800
                                        @elseif($txn->type === 'commission') bg-green-100 text-green-800
                                        @elseif($txn->type === 'bonus') bg-purple-100 text-purple-800
                                        @elseif($txn->type === 'booking') bg-orange-100 text-orange-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($txn->type ?? '-') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium 
                                    @if($isIncoming) text-green-600
                                    @else text-red-600
                                    @endif">
                                    @if($isIncoming)+@else-@endif₹{{ number_format($txn->amount ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($isIncoming) bg-green-100 text-green-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ $isIncoming ? 'In' : 'Out' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $txn->description ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No payment transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Downline -->
        <div id="tab-downline" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <div class="mb-6 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800">Downline Users</h4>
                <div class="flex items-center gap-4">
                    <label class="text-sm font-medium text-gray-700">Select Level:</label>
                    <select id="downline-level-select" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        @for($i = 1; $i <= 15; $i++)
                            <option value="{{ $i }}" {{ $i == 1 ? 'selected' : '' }}>Level {{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="mb-4 p-4 bg-primary-50 rounded-lg">
                <p class="text-sm text-gray-700">
                    <span class="font-semibold">Total Users at Level <span id="current-level-display">1</span>:</span> 
                    <span class="text-primary-700 text-lg" id="total-downline-count">{{ $user->referrals->count() }}</span>
                </p>
            </div>
            <div id="downline-loading" class="hidden text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-primary-600"></i>
                <p class="mt-2 text-gray-600">Loading downline...</p>
            </div>
            <div id="downline-table-container">
                @if($user->referrals->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slab</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="downline-table-body" class="bg-white divide-y divide-gray-200">
                                @foreach($user->referrals as $child)
                                    @php
                                        $childId = is_object($child) ? $child->id : (is_array($child) ? ($child['id'] ?? null) : null);
                                        $childName = is_object($child) ? ($child->name ?? 'N/A') : (is_array($child) ? ($child['name'] ?? 'N/A') : 'N/A');
                                        $childBrokerId = is_object($child) ? ($child->broker_id ?? '—') : (is_array($child) ? ($child['broker_id'] ?? '—') : '—');
                                        $childReferralCode = is_object($child) ? ($child->referral_code ?? '—') : (is_array($child) ? ($child['referral_code'] ?? '—') : '—');
                                        $childEmail = is_object($child) ? ($child->email ?? '—') : (is_array($child) ? ($child['email'] ?? '—') : '—');
                                        $childPhone = is_object($child) ? ($child->phone_number ?? '—') : (is_array($child) ? ($child['phone_number'] ?? '—') : '—');
                                        $childSlabName = is_object($child) ? (optional($child->slab)->name ?? 'Slab1') : (is_array($child) ? ($child['slab']['name'] ?? $child['slab_name'] ?? 'Slab1') : 'Slab1');
                                        $childCreatedAt = is_object($child) ? ($child->created_at ?? null) : (is_array($child) ? (isset($child['created_at']) ? \Carbon\Carbon::parse($child['created_at']) : null) : null);
                                        
                                        // Commission earned from this downline member (from referral_commissions table)
                                        $commissionFromMember = $childId
                                            ? (float) \App\Models\ReferralCommission::where('parent_user_id', $user->id)
                                                ->where('child_user_id', $childId)
                                                ->sum('referral_commission_amount')
                                            : 0;
                                    @endphp
                                    @if($childId)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-primary-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $childName }}</div>
                                                    <div class="text-xs text-gray-500">ID: {{ $childBrokerId }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-semibold text-primary-600">{{ $childReferralCode }}</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $childEmail }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $childPhone }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $childSlabName }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-semibold text-green-600">
                                                ₹{{ number_format($commissionFromMember, 2) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $childCreatedAt ? $childCreatedAt->format('d M Y') : '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.users.show', $childId) }}" 
                                               class="text-primary-600 hover:text-primary-900">
                                                <i class="fas fa-eye mr-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Users Found</h3>
                        <p class="text-gray-500">No users found at this level.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Slab History -->
        <div id="tab-slab-history" class="hidden bg-white rounded-2xl shadow-3d p-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-4">Slab Upgrade History</h4>
            
            @if($user->slabUpgrades && $user->slabUpgrades->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Slab</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Slab</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Area Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Triggered By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($user->slabUpgrades as $upgrade)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $upgrade->upgraded_at ? $upgrade->upgraded_at->format('d M Y, h:i A') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($upgrade->oldSlab)
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ $upgrade->oldSlab->name }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-500">
                                                None
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($upgrade->newSlab)
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 text-primary-800">
                                                {{ $upgrade->newSlab->name }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ number_format($upgrade->total_area_sold ?? 0, 2) }} sq units
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($upgrade->sale)
                                            <a href="{{ route('admin.projects.show', $upgrade->sale->plot->project_id ?? '#') }}" class="text-primary-600 hover:text-primary-900">
                                                Sale #{{ $upgrade->sale->id }}
                                            </a>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $upgrade->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-layer-group text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Slab Upgrades Yet</h3>
                    <p class="text-gray-500">This user hasn't upgraded their slab yet. Upgrades will appear here automatically when they cross slab thresholds.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Simple tabs
    document.addEventListener('DOMContentLoaded', function() {
        console.log('User details page loaded, initializing tabs...');
        
        const tabButtons = document.querySelectorAll('.tab-btn');
        console.log('Found tab buttons:', tabButtons.length);
        
        tabButtons.forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                
                const targetTab = this.getAttribute('data-tab');
                console.log('Tab clicked:', targetTab);
                
                // Remove active state from all buttons
                document.querySelectorAll('.tab-btn').forEach(b=>{ 
                    b.classList.remove('bg-primary-100','text-primary-700', 'font-medium'); 
                    b.classList.add('bg-gray-100','text-gray-700'); 
                });
                
                // Hide all tabs
                document.querySelectorAll('[id^="tab-"]').forEach(sec=>{
                    sec.classList.add('hidden');
                    console.log('Hiding tab:', sec.id);
                });
                
                // Show target tab
                const target = document.querySelector(targetTab);
                if (target) {
                    target.classList.remove('hidden');
                    console.log('Showing tab:', targetTab);
                } else {
                    console.error('Target tab not found:', targetTab);
                }
                
                // Add active state to clicked button
                this.classList.remove('bg-gray-100','text-gray-700');
                this.classList.add('bg-primary-100','text-primary-700', 'font-medium');
            });
        });
        
        // Level dropdown handler for downline
        const levelSelect = document.getElementById('downline-level-select');
        if (levelSelect) {
            levelSelect.addEventListener('change', function() {
                const level = this.value;
                loadDownlineByLevel(level);
            });
        }

        function loadDownlineByLevel(level) {
            const loadingEl = document.getElementById('downline-loading');
            const tableContainer = document.getElementById('downline-table-container');
            const tableBody = document.getElementById('downline-table-body');
            const totalCountEl = document.getElementById('total-downline-count');
            const currentLevelEl = document.getElementById('current-level-display');
            
            // Show loading
            loadingEl.classList.remove('hidden');
            tableContainer.classList.add('hidden');
            
            // Update current level display
            if (currentLevelEl) {
                currentLevelEl.textContent = level;
            }
            
            // Fetch data
            fetch(`{{ route('admin.users.downline', $user->id) }}?level=${level}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                loadingEl.classList.add('hidden');
                tableContainer.classList.remove('hidden');
                
                if (data.success) {
                    // Update total count
                    if (totalCountEl) {
                        totalCountEl.textContent = data.total_count || 0;
                    }
                    
                    // Update table
                    if (tableBody) {
                        if (data.data && data.data.length > 0) {
                            tableBody.innerHTML = data.data.map(user => `
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">${user.name || 'N/A'}</div>
                                                <div class="text-xs text-gray-500">ID: ${user.broker_id || '—'}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-primary-600">${user.referral_code || '—'}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${user.email || '—'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${user.phone_number || '—'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            ${user.slab || 'Slab1'}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-green-600">
                                            ₹${(user.commission_from_member || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${user.created_at ? new Date(user.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '—'}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="/admin/users/${user.id}" class="text-primary-600 hover:text-primary-900">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                            `).join('');
                        } else {
                            tableBody.innerHTML = `
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Users Found</h3>
                                        <p class="text-gray-500">No users found at level ${level}.</p>
                                    </td>
                                </tr>
                            `;
                        }
                    }
                } else {
                    alert('Failed to load downline: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                loadingEl.classList.add('hidden');
                tableContainer.classList.remove('hidden');
                console.error('Error loading downline:', error);
                alert('Error loading downline. Please try again.');
            });
        }
        
        // Make sure overview tab is visible by default
        const overviewTab = document.querySelector('#tab-overview');
        if (overviewTab) {
            overviewTab.classList.remove('hidden');
        }
    });
    function updateReferralCode(userId) {
        const newReferralCode = document.getElementById('newReferralCode').value.trim();
        
        if (!newReferralCode) {
            alert('Please enter a referral code');
            return;
        }
        
        // Password protection
        const password = prompt('⚠️ This action requires authorization.\n\nEnter password to continue:');
        if (password !== '8875634554') {
            if (password !== null) { // User didn't cancel
                alert('❌ Invalid password. Operation cancelled.');
            }
            return;
        }
        
        if (!confirm(`Are you sure you want to change the sponsor referral code?\n\nThis will shift this user and their ENTIRE DOWNLINE to the new sponsor.\n\nAll users below this user will be moved to the new sponsor's network.`)) {
            return;
        }
        
        // Build the URL using Laravel route() (route expects {user} param)
        const url = @json(route('admin.users.referral-code.update', ['user' => '__USER__']))
            .replace('__USER__', userId);

        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ new_referral_code: newReferralCode })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Referral code updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to update referral code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating referral code');
        });
    }
</script>
@endpush
