@extends('admin.layouts.app')

@section('title', 'Slab Upgrade History')
@section('page-title', 'Slab Upgrade History')

@section('content')
<div class="admin-page-content bg-white rounded-2xl shadow-3d p-6 min-w-0">
    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <form method="GET" action="{{ route('admin.slab-upgrades') }}" class="flex gap-2">
            <input type="text" 
                   name="search" 
                   value="{{ request('search') }}" 
                   placeholder="Search by user name, email, or broker ID..." 
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-search"></i> Search
            </button>
            @if(request('search') || request('user_id') || request('slab_id'))
                <a href="{{ route('admin.slab-upgrades') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-times"></i> Clear
                </a>
            @endif
        </form>
        
        <form method="GET" action="{{ route('admin.slab-upgrades') }}" class="flex gap-2">
            <input type="hidden" name="search" value="{{ request('search') }}">
            <select name="slab_id" 
                    onchange="this.form.submit()" 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="">All Slabs</option>
                @foreach($slabs as $slab)
                    <option value="{{ $slab->id }}" {{ request('slab_id') == $slab->id ? 'selected' : '' }}>
                        {{ $slab->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <!-- Stats -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 rounded-xl bg-primary-50">
            <p class="text-sm text-gray-600">Total Upgrades</p>
            <p class="text-2xl font-bold text-primary-700">{{ $upgrades->total() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-green-50">
            <p class="text-sm text-gray-600">This Month</p>
            <p class="text-2xl font-bold text-green-700">
                {{ \App\Models\SlabUpgrade::whereMonth('upgraded_at', now()->month)->whereYear('upgraded_at', now()->year)->count() }}
            </p>
        </div>
        <div class="p-4 rounded-xl bg-blue-50">
            <p class="text-sm text-gray-600">This Week</p>
            <p class="text-2xl font-bold text-blue-700">
                {{ \App\Models\SlabUpgrade::whereBetween('upgraded_at', [now()->startOfWeek(), now()->endOfWeek()])->count() }}
            </p>
        </div>
    </div>

    <!-- Table -->
    @if($upgrades->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Slab</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Slab</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Area Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Triggered By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($upgrades as $upgrade)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $upgrade->upgraded_at ? $upgrade->upgraded_at->format('d M Y, h:i A') : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-primary-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="{{ route('admin.users.show', $upgrade->user_id) }}" class="text-primary-600 hover:text-primary-900">
                                                {{ $upgrade->user->name ?? 'N/A' }}
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ $upgrade->user->broker_id ?? '—' }}</div>
                                    </div>
                                </div>
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
                                @if($upgrade->sale && $upgrade->sale->plot && $upgrade->sale->plot->project)
                                    <a href="{{ route('admin.projects.show', $upgrade->sale->plot->project_id) }}" class="text-primary-600 hover:text-primary-900">
                                        Sale #{{ $upgrade->sale->id }}
                                    </a>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.users.show', $upgrade->user_id) }}" class="text-primary-600 hover:text-primary-900">
                                    <i class="fas fa-eye mr-1"></i>View User
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $upgrades->appends(request()->query())->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <i class="fas fa-layer-group text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Slab Upgrades Found</h3>
            <p class="text-gray-500">
                @if(request('search') || request('user_id') || request('slab_id'))
                    No upgrades match your filters. Try adjusting your search criteria.
                @else
                    No slab upgrades have been recorded yet. Upgrades will appear here automatically when users cross slab thresholds.
                @endif
            </p>
        </div>
    @endif
</div>
@endsection
