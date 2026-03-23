@extends('admin.layouts.app')

@section('title', 'Wallet Management')
@section('page-title', 'Wallet Management')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Withdrawable</p>
                    <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['total_withdrawable'] ?? 0, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">From deals marked done only; users withdraw from this</p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-wallet text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Earned</p>
                    <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['total_earned'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-arrow-up text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Deposits</p>
                    <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['pending_deposits'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-arrow-down text-white text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="card-3d bg-white rounded-2xl p-6 shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Withdrawals</p>
                    <p class="text-3xl font-bold text-gray-900">₹{{ number_format($stats['pending_withdrawals'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl flex items-center justify-center shadow-glow">
                    <i class="fas fa-arrow-up text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-2xl shadow-3d p-4">
        <div class="flex flex-wrap gap-2">
            <button class="tab-btn px-6 py-3 rounded-lg bg-primary-100 text-primary-700 font-medium transition-all" data-tab="deposits">
                <i class="fas fa-arrow-down mr-2"></i>Deposit Requests
            </button>
            <button class="tab-btn px-6 py-3 rounded-lg bg-gray-100 text-gray-700 transition-all" data-tab="withdrawals">
                <i class="fas fa-arrow-up mr-2"></i>Withdrawal Requests
            </button>
        </div>
    </div>

    <!-- Deposits Tab -->
    <div id="deposits-tab" class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">Deposit Requests</h3>
            <a href="{{ route('admin.wallet.deposits') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="deposits-table-body">
                    @forelse($deposits ?? [] as $deposit)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $deposit->user->name ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ $deposit->user->broker_id ?? 'N/A' }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-green-600">₹{{ number_format($deposit->amount, 2) }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $deposit->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                @if($deposit->status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($deposit->status === 'completed') bg-green-100 text-green-800
                                @elseif($deposit->status === 'cancelled') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($deposit->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            @if($deposit->status === 'pending')
                            <div class="flex space-x-2">
                                <button onclick="approveDeposit({{ $deposit->id }})" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="rejectDeposit({{ $deposit->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            @else
                            <span class="text-gray-400">Processed</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No pending deposit requests</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Withdrawals Tab -->
    <div id="withdrawals-tab" class="hidden bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">Withdrawal Requests</h3>
            <a href="{{ route('admin.wallet.withdrawals') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="withdrawals-table-body">
                    @forelse($withdrawals ?? [] as $withdrawal)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $withdrawal->user->name ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ $withdrawal->user->broker_id ?? 'N/A' }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-red-600">₹{{ number_format($withdrawal->amount, 2) }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $withdrawal->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                @if($withdrawal->status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($withdrawal->status === 'completed') bg-green-100 text-green-800
                                @elseif($withdrawal->status === 'cancelled') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($withdrawal->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            @if($withdrawal->status === 'pending')
                            <div class="flex space-x-2">
                                <button onclick="approveWithdrawal({{ $withdrawal->id }})" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="rejectWithdrawal({{ $withdrawal->id }})" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            @else
                            <span class="text-gray-400">Processed</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No pending withdrawal requests</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Tab switching
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const tab = this.dataset.tab;
                
                // Update button styles
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('bg-primary-100', 'text-primary-700', 'font-medium');
                    b.classList.add('bg-gray-100', 'text-gray-700');
                });
                this.classList.remove('bg-gray-100', 'text-gray-700');
                this.classList.add('bg-primary-100', 'text-primary-700', 'font-medium');
                
                // Show/hide tabs
                document.getElementById('deposits-tab').classList.add('hidden');
                document.getElementById('withdrawals-tab').classList.add('hidden');
                document.getElementById(tab + '-tab').classList.remove('hidden');
            });
        });
        
        // Show deposits tab by default
        const depositsTab = document.getElementById('deposits-tab');
        if (depositsTab) {
            depositsTab.classList.remove('hidden');
        }
    });

    // Route URLs for JavaScript
    const depositApproveUrl = '{{ route("admin.wallet.deposits.approve", ":id") }}';
    const depositRejectUrl = '{{ route("admin.wallet.deposits.reject", ":id") }}';
    const withdrawalApproveUrl = '{{ route("admin.wallet.withdrawals.approve", ":id") }}';
    const withdrawalRejectUrl = '{{ route("admin.wallet.withdrawals.reject", ":id") }}';
    
    // Approve Deposit
    function approveDeposit(id) {
        if (!confirm('Are you sure you want to approve this deposit?')) return;
        
        const url = depositApproveUrl.replace(':id', id);
        fetch(url, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Failed to approve deposit');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Deposit approved successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to approve deposit'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    }

    // Reject Deposit
    function rejectDeposit(id) {
        if (!confirm('Are you sure you want to reject this deposit?')) return;
        
        const url = depositRejectUrl.replace(':id', id);
        fetch(url, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Failed to reject deposit');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Deposit rejected successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to reject deposit'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    }
    
    // Approve Withdrawal
    function approveWithdrawal(id) {
        if (!confirm('Are you sure you want to approve this withdrawal?')) return;
        
        const url = withdrawalApproveUrl.replace(':id', id);
        fetch(url, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Failed to approve withdrawal');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Withdrawal approved successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to approve withdrawal'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    }

    // Reject Withdrawal
    function rejectWithdrawal(id) {
        if (!confirm('Are you sure you want to reject this withdrawal?')) return;
        
        const url = withdrawalRejectUrl.replace(':id', id);
        fetch(url, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Failed to reject withdrawal');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Withdrawal rejected successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to reject withdrawal'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    }
</script>
@endpush
