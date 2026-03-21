@extends('admin.layouts.app')

@section('title', 'Project Details')
@section('page-title', 'Project Details: ' . $project->name)

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Project Overview -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $project->name }}</h2>
                <p class="text-gray-600 mt-1">{{ $project->location }}, {{ $project->city }}, {{ $project->state }}</p>
            </div>
            @php($badge = ['available'=>'bg-green-100 text-green-700','upcoming'=>'bg-yellow-100 text-yellow-700','sold_out'=>'bg-red-100 text-red-700'][$project->status] ?? 'bg-gray-100 text-gray-700')
            <span class="px-3 py-1 text-sm rounded {{ $badge }} capitalize">{{ str_replace('_',' ', $project->status) }}</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Total Units</div>
                <div class="text-2xl font-bold text-blue-700">{{ $totalPlots }}</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Available</div>
                <div class="text-2xl font-bold text-green-700">{{ $availablePlots }}</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Booked</div>
                <div class="text-2xl font-bold text-yellow-700">{{ $bookedPlots }}</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Sold</div>
                <div class="text-2xl font-bold text-red-700">{{ $soldPlots }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Total Sales</div>
                <div class="text-2xl font-bold text-purple-700">{{ $totalSales }}</div>
            </div>
            <div class="bg-indigo-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Total Revenue</div>
                <div class="text-2xl font-bold text-indigo-700">₹{{ number_format($totalRevenue, 2) }}</div>
            </div>
            <div class="bg-pink-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">Total Commission</div>
                <div class="text-2xl font-bold text-pink-700">₹{{ number_format($totalCommission, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Project Details -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Project Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">Description</label>
                <p class="text-gray-800 mt-1">{{ $project->description ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Type</label>
                <p class="text-gray-800 mt-1 capitalize">{{ $project->type ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Pincode</label>
                <p class="text-gray-800 mt-1">{{ $project->pincode ?? 'N/A' }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Price per Sqft</label>
                <p class="text-gray-800 mt-1">₹{{ number_format($project->price_per_sqft ?? 0, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Minimum Booking Amount</label>
                <p class="text-gray-800 mt-1">₹{{ number_format($project->minimum_booking_amount ?? 0, 2) }}</p>
            </div>
            <div>
                <label class="text-sm text-gray-600">Created At</label>
                <p class="text-gray-800 mt-1">{{ $project->created_at ? $project->created_at->format('d M Y, h:i A') : 'N/A' }}</p>
            </div>
        </div>
    </div>

    <!-- Booked Properties -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-800">Booked Properties ({{ $bookedProperties->count() }})</h3>
        </div>

        @if($bookedPropertiesByBatch->count() > 0)
            @foreach($bookedPropertiesByBatch as $batch)
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">{{ $batch['batch_name'] }} ({{ $batch['count'] }} units)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                     
                                   <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plot Number</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booked By</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking Date</th>
                               
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($batch['plots'] as $plot)
                                    @php($sale = $plot->sales->first())
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $plot->plot_number }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 capitalize">{{ $plot->type }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $plot->size ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @php($statusBadge = ['available'=>'bg-green-100 text-green-700','pending_booking'=>'bg-purple-100 text-purple-700','booked'=>'bg-yellow-100 text-yellow-700','sold'=>'bg-red-100 text-red-700'][$plot->status] ?? 'bg-gray-100 text-gray-700')
                                            <span class="px-2 py-1 text-xs rounded {{ $statusBadge }} capitalize">{{ $plot->status }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            @if($sale && $sale->customer)
                                                {{ $sale->customer->name }}<br>
                                                <span class="text-xs text-gray-500">{{ $sale->customer->phone_number ?? 'N/A' }}</span>
                                            @elseif($sale)
                                                {{ $sale->customer_name ?? 'N/A' }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            @if($sale)
                                                ₹{{ number_format($sale->sale_price ?? 0, 2) }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            @if($sale && $sale->created_at)
                                                {{ $sale->created_at->format('d M Y') }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @else
            <p class="text-gray-500 text-center py-8">No booked properties found.</p>
        @endif
    </div>

    <!-- Booked Users -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Booked Users ({{ $bookedUsers->count() }})</h3>
        
        @if($bookedUsers->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Properties Booked</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($bookedUsers as $user)
                            @php($userSales = $user->project_sales ?? collect())
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->serial_id ?? $user->id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $user->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $user->phone_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $user->email ?? 'N/A' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $userSales->count() }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">₹{{ number_format($userSales->sum('sale_price'), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No booked users found.</p>
        @endif
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.projects') }}" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">Back to Projects</a>
        <div class="space-x-2">
            <a href="{{ route('admin.projects.export', ['project' => $project->id]) }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"><i class="fas fa-download mr-1"></i>Export Project</a>
            <a href="{{ route('admin.projects.show', ['project' => $project->id]) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Manage Properties</a>
            <a href="{{ route('admin.projects.edit', ['project' => $project->id]) }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Edit Project</a>
        </div>
    </div>
</div>
@endsection

