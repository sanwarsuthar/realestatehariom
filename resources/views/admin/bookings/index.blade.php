@extends('admin.layouts.app')

@section('title', 'Bookings')
@section('page-title', 'Bookings')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-2xl shadow-3d p-6">
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="mb-6">
            <div class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by customer, project, plot, ID..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Search</button>
            </div>
        </form>

        <h3 class="text-lg font-semibold text-gray-800 mb-4">Confirmed Bookings</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project / Plot</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Value</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Received</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($bookings as $sale)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-900">#{{ $sale->id }}</td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium">{{ $sale->plot->project->name ?? 'N/A' }}</div>
                            <div class="text-xs text-gray-500">{{ ucfirst($sale->plot->type ?? '') }} {{ $sale->plot->plot_number ?? 'N/A' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $sale->customer->name ?? $sale->customer_name }}</td>
                        <td class="px-4 py-3 text-sm font-medium">₹{{ number_format($sale->total_sale_value ?? $sale->total_received ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-green-600">₹{{ number_format($sale->total_received ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-sm {{ ($sale->pending_amount ?? 0) > 0 ? 'text-orange-600' : 'text-green-600' }}">₹{{ number_format($sale->pending_amount ?? 0, 2) }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.bookings.show', $sale->id) }}" class="text-primary-600 hover:text-primary-800 text-sm font-medium">View &amp; Record payment</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No confirmed bookings found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bookings->hasPages())
        <div class="mt-4">{{ $bookings->links() }}</div>
        @endif
    </div>
</div>
@endsection
