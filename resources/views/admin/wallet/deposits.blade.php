@extends('admin.layouts.app')

@section('title', 'Deposit Requests')
@section('page-title', 'Deposit Requests')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Filter Section -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <form method="GET" action="{{ route('admin.wallet.deposits') }}" class="flex items-center space-x-4">
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
                <a href="{{ route('admin.wallet.deposits') }}" class="ml-2 px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Deposits Table -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-800">All Deposit Requests</h3>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($deposits as $deposit)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deposit->transaction_id }}
                        </td>
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
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $deposit->description ?? '-' }}
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
                            @if($deposit->processed_at)
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $deposit->processed_at->format('d M Y, h:i A') }}
                            </div>
                            @endif
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No deposit requests found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($deposits->hasPages())
        <div class="mt-6">
            {{ $deposits->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Route URLs for JavaScript
    const depositApproveUrl = '{{ route("admin.wallet.deposits.approve", ":id") }}';
    const depositRejectUrl = '{{ route("admin.wallet.deposits.reject", ":id") }}';
    
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
        .then(response => response.json())
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
            alert('An error occurred. Please try again.');
        });
    }

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
        .then(response => response.json())
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
            alert('An error occurred. Please try again.');
        });
    }
</script>
@endsection

