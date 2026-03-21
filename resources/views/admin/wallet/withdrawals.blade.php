@extends('admin.layouts.app')

@section('title', 'Withdrawal Requests')
@section('page-title', 'Withdrawal Requests')

@section('content')
<style> 
.bg-white {
    background-color: #ffffff !important;
}
</style>
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Filter Section -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <form method="GET" action="{{ route('admin.wallet.withdrawals') }}" class="flex items-center space-x-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">All Status</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                @if(request('status'))
                <a href="{{ route('admin.wallet.withdrawals') }}" class="ml-2 px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Withdrawals Table -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">All Withdrawal Requests</h3>
            <a href="{{ route('admin.wallet') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                <i class="fas fa-arrow-left mr-1"></i>Back to Wallet
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($withdrawals as $withdrawal)
                    @php
                        $metadata = $withdrawal->metadata ? (is_string($withdrawal->metadata) ? json_decode($withdrawal->metadata, true) : $withdrawal->metadata) : [];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $withdrawal->transaction_id }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $withdrawal->user->name ?? 'N/A' }}</div>
                                <div class="text-sm text-gray-500">{{ $withdrawal->user->broker_id ?? 'N/A' }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-red-600">₹{{ number_format($withdrawal->amount, 2) }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if(!empty($metadata['bank_account_number']) || !empty($metadata['bank_name']))
                                <div class="space-y-1">
                                    <div class="font-semibold text-gray-900">
                                        <i class="fas fa-university mr-1 text-primary-600"></i>
                                        {{ $metadata['bank_name'] ?? 'N/A' }}
                                    </div>
                                    <div class="text-gray-600">
                                        <span class="font-medium">A/C:</span> {{ $metadata['bank_account_number'] ?? 'N/A' }}
                                    </div>
                                    <div class="text-gray-600">
                                        <span class="font-medium">Holder:</span> {{ $metadata['account_holder_name'] ?? 'N/A' }}
                                    </div>
                                    <div class="text-gray-600">
                                        <span class="font-medium">IFSC:</span> {{ $metadata['ifsc_code'] ?? 'N/A' }}
                                    </div>
                                    @if(!empty($metadata['branch_location']))
                                    <div class="text-gray-600 text-xs">
                                        <span class="font-medium">Branch:</span> {{ $metadata['branch_location'] }}
                                    </div>
                                    @endif
                                    @if(!empty($metadata['upi_id']))
                                    <div class="text-gray-600 text-xs">
                                        <span class="font-medium">UPI:</span> {{ $metadata['upi_id'] }}
                                    </div>
                                    @endif
                                    <button onclick="showWithdrawalDetails({{ $withdrawal->id }})" class="text-primary-600 hover:text-primary-900 text-xs mt-1 underline">
                                        <i class="fas fa-eye mr-1"></i>View Full Details
                                    </button>
                                </div>
                            @else
                                <span class="text-gray-400">No bank details</span>
                            @endif
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
                            <div class="flex items-center space-x-2">
                                @if(!empty($metadata['bank_account_number']) || !empty($metadata['bank_name']))
                                <button 
                                    onclick="showWithdrawalDetails({{ $withdrawal->id }})" 
                                    class="text-primary-600 hover:text-primary-900 p-2 rounded-lg hover:bg-primary-50 transition-colors"
                                    title="View Full Bank Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @endif
                                @if($withdrawal->status === 'pending')
                                <div class="flex flex-col space-y-1">
                                    <button onclick="approveWithdrawal({{ $withdrawal->id }})" class="text-green-600 hover:text-green-900 text-left">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                    <button onclick="rejectWithdrawal({{ $withdrawal->id }})" class="text-red-600 hover:text-red-900 text-left">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </div>
                                @else
                                <div class="flex flex-col">
                                    <span class="text-gray-400">Processed</span>
                                    @if($withdrawal->processed_at)
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $withdrawal->processed_at->format('d M Y, h:i A') }}
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No withdrawal requests found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($withdrawals->hasPages())
        <div class="mt-6">
            {{ $withdrawals->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Bank Details Modal -->
<div id="bankDetailsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">Bank Details</h3>
            <button onclick="closeBankDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="bankDetailsContent" class="space-y-4">
            <!-- Content will be loaded here -->
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeBankDetailsModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Close
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Route URLs for JavaScript
    const withdrawalShowUrl = '{{ route("admin.wallet.withdrawals.show", ":id") }}';
    const withdrawalApproveUrl = '{{ route("admin.wallet.withdrawals.approve", ":id") }}';
    const withdrawalRejectUrl = '{{ route("admin.wallet.withdrawals.reject", ":id") }}';
    
    function showWithdrawalDetails(id) {
        const url = withdrawalShowUrl.replace(':id', id);
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.transaction) {
                    const metadata = data.transaction.metadata || {};
                    const content = `
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                <p class="text-sm font-semibold text-blue-900">Transaction Information</p>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-600">Transaction ID:</span>
                                    <span class="font-semibold text-gray-900">${data.transaction.transaction_id}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Amount:</span>
                                    <span class="font-semibold text-red-600">₹${parseFloat(data.transaction.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">Account Number</p>
                                <p class="font-semibold text-gray-900 text-lg">${metadata.bank_account_number || 'N/A'}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">Bank Name</p>
                                <p class="font-semibold text-gray-900 text-lg">${metadata.bank_name || 'N/A'}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">Account Holder Name</p>
                                <p class="font-semibold text-gray-900 text-lg">${metadata.account_holder_name || 'N/A'}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">IFSC Code</p>
                                <p class="font-semibold text-gray-900 text-lg">${metadata.ifsc_code || 'N/A'}</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 md:col-span-2">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">Branch Location</p>
                                <p class="font-semibold text-gray-900">${metadata.branch_location || 'N/A'}</p>
                            </div>
                            ${metadata.upi_id ? `
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">UPI ID</p>
                                <p class="font-semibold text-gray-900">${metadata.upi_id}</p>
                            </div>
                            ` : ''}
                            ${metadata.comments ? `
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 ${metadata.upi_id ? '' : 'md:col-span-2'}">
                                <p class="text-gray-500 text-xs mb-1 font-medium uppercase">Additional Comments</p>
                                <p class="font-semibold text-gray-900">${metadata.comments}</p>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    document.getElementById('bankDetailsContent').innerHTML = content;
                    document.getElementById('bankDetailsModal').classList.remove('hidden');
                } else {
                    alert('Failed to load withdrawal details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading details');
            });
    }

    function closeBankDetailsModal() {
        document.getElementById('bankDetailsModal').classList.add('hidden');
    }

    function approveWithdrawal(id) {
        if (!confirm('Are you sure you want to approve this withdrawal? Please verify the bank details shown in the table before proceeding.')) return;
        
        const url = withdrawalApproveUrl.replace(':id', id);
        fetch(url, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
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
            alert('An error occurred. Please try again.');
        });
    }

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
        .then(response => response.json())
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
            alert('An error occurred. Please try again.');
        });
    }
</script>
@endpush

