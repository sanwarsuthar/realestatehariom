@extends('admin.layouts.app')

@section('title', 'Create Slabs')
@section('page-title', 'Create Slabs')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <strong class="block mb-2">Please fix the following errors:</strong>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-3d p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Create New Slab</h3>

            <form method="POST" action="{{ route('admin.slabs.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slab Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Slab1" value="{{ old('name') }}" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Criteria <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="minimum_target" class="w-full px-4 py-2 border rounded-lg" placeholder="0" value="{{ old('minimum_target') }}" required>
                        <p class="text-xs text-gray-500 mt-1">Based on area sold / business volume</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Criteria</label>
                        <input type="number" step="0.01" min="0" name="maximum_target" class="w-full px-4 py-2 border rounded-lg" placeholder="250" value="{{ old('maximum_target') }}">
                        <p class="text-xs text-gray-500 mt-1">Leave empty for no upper limit</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Measurement Unit <span class="text-red-500">*</span></label>
                    <select name="measurement_unit_id" class="w-full px-4 py-2 border rounded-lg" required>
                        <option value="">Select unit</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('measurement_unit_id') == $unit->id)>
                                {{ $unit->name }} @if($unit->symbol) ({{ $unit->symbol }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property Types <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-500 mb-2">Select which property types this slab applies to</p>
                    <div class="space-y-2 border border-gray-300 rounded-lg p-3 max-h-48 overflow-y-auto">
                        @foreach($propertyTypes as $propertyType)
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                <input 
                                    type="checkbox" 
                                    name="property_types[]" 
                                    value="{{ $propertyType->id }}"
                                    @checked(in_array($propertyType->id, old('property_types', [])))
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                >
                                <span class="text-sm text-gray-700">
                                    {{ $propertyType->name }}
                                    <span class="text-gray-500">({{ $propertyType->measurementUnit->symbol ?? $propertyType->measurementUnit->name ?? 'N/A' }})</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('property_types')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Note:</strong> Commission percentages are configured in <strong>Settings → MLM Commission Structure</strong>. 
                        You can set different commission rates for each property type and slab combination there.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bonus %</label>
                    <input type="number" step="0.01" min="0" max="100" name="bonus_percentage" class="w-full px-4 py-2 border rounded-lg" value="{{ old('bonus_percentage', 0) }}">
                    <p class="text-xs text-gray-500 mt-1">Optional bonus percentage for this slab</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                        <input type="color" name="color_code" class="w-full" value="{{ old('color_code', '#8B5CF6') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" class="w-full px-4 py-2 border rounded-lg" value="{{ old('sort_order', 0) }}">
                    </div>
                </div>

                <button type="submit" class="px-6 py-3 bg-primary-600 text-white rounded-lg w-full">Create Slab</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-3d p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Existing Slabs</h3>
            
            @if($propertyTypes->count() > 0)
                <!-- Tabs for Property Types -->
                <div class="mb-4 border-b border-gray-200">
                    <div class="flex flex-wrap gap-2 -mb-px">
                        <button 
                            onclick="showPropertyTypeTab('all')" 
                            id="tab-all"
                            class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-primary-500 text-primary-600 bg-primary-50 rounded-t-lg transition-colors"
                        >
                            All Slabs
                        </button>
                        @foreach($propertyTypes as $propertyType)
                            <button 
                                onclick="showPropertyTypeTab('{{ $propertyType->id }}')" 
                                id="tab-{{ $propertyType->id }}"
                                class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 transition-colors"
                            >
                                {{ $propertyType->name }}
                                <span class="ml-1 text-xs text-gray-500">({{ $slabs->filter(function($slab) use ($propertyType) { return $slab->propertyTypes->contains('id', $propertyType->id); })->count() }})</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- All Slabs Tab -->
                <div id="container-all" class="tab-container">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Criteria</th>
                                    <th class="px-4 py-2 text-left">Property Types</th>
                                    <th class="px-4 py-2 text-left">Bonus %</th>
                                    <th class="px-4 py-2 text-left">Sort Order</th>
                                    <th class="px-4 py-2 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($slabs as $slab)
                                    <tr>
                                        <td class="px-4 py-2 font-semibold">{{ $slab->name }}</td>
                                        <td class="px-4 py-2">
                                            <div class="text-sm text-gray-700">
                                                {{ number_format($slab->minimum_target, 2) }}
                                                -
                                                {{ $slab->maximum_target ? number_format($slab->maximum_target, 2) : '∞' }}
                                                {{ $slab->measurementUnit?->symbol ?? $slab->measurementUnit?->name }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if($slab->propertyTypes->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($slab->propertyTypes as $propertyType)
                                                        <span class="px-2 py-1 text-xs bg-primary-100 text-primary-700 rounded">
                                                            {{ $propertyType->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">No property types</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ number_format($slab->bonus_percentage ?? 0, 2) }}%
                                        </td>
                                        <td class="px-4 py-2">
                                            <span class="text-sm font-semibold text-gray-700">{{ $slab->sort_order ?? 0 }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="{{ route('admin.slabs.edit', $slab) }}" class="px-3 py-1 text-xs rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <form method="POST" action="{{ route('admin.slabs.destroy', $slab) }}" onsubmit="return confirm('Delete this slab?')" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="px-3 py-1 text-xs rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                                                        <i class="fas fa-trash mr-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-gray-500">No slabs created yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Property Type Specific Tabs -->
                @foreach($propertyTypes as $propertyType)
                    <div id="container-{{ $propertyType->id }}" class="tab-container hidden">
                        <div class="mb-3 p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600">
                                <span class="font-semibold">{{ $propertyType->name }}</span> slabs - 
                                Measurement Unit: <span class="font-medium">{{ $propertyType->measurementUnit->symbol ?? $propertyType->measurementUnit->name ?? 'N/A' }}</span>
                            </p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Name</th>
                                        <th class="px-4 py-2 text-left">Criteria</th>
                                        <th class="px-4 py-2 text-left">Unit</th>
                                        <th class="px-4 py-2 text-left">Bonus %</th>
                                        <th class="px-4 py-2 text-left">Sort Order</th>
                                        <th class="px-4 py-2 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php
                                        $propertyTypeSlabs = $slabs->filter(function($slab) use ($propertyType) {
                                            return $slab->propertyTypes->contains('id', $propertyType->id);
                                        });
                                    @endphp
                                    @forelse($propertyTypeSlabs as $slab)
                                        <tr>
                                            <td class="px-4 py-2 font-semibold">{{ $slab->name }}</td>
                                            <td class="px-4 py-2">
                                                <div class="text-sm text-gray-700">
                                                    {{ number_format($slab->minimum_target, 2) }}
                                                    -
                                                    {{ $slab->maximum_target ? number_format($slab->maximum_target, 2) : '∞' }}
                                                    {{ $slab->measurementUnit?->symbol ?? $slab->measurementUnit?->name }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="text-sm text-gray-600">{{ $slab->measurementUnit?->symbol ?? $slab->measurementUnit?->name ?? 'N/A' }}</span>
                                            </td>
                                            <td class="px-4 py-2">
                                                {{ number_format($slab->bonus_percentage ?? 0, 2) }}%
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="text-sm font-semibold text-gray-700">{{ $slab->sort_order ?? 0 }}</span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="{{ route('admin.slabs.edit', $slab) }}" class="px-3 py-1 text-xs rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                                        <i class="fas fa-edit mr-1"></i>Edit
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.slabs.destroy', $slab) }}" onsubmit="return confirm('Delete this slab?')" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="px-3 py-1 text-xs rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                                                            <i class="fas fa-trash mr-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-4 text-center text-gray-500">No slabs found for {{ $propertyType->name }}.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-8 text-gray-500">
                    <p>No property types found. Please create property types first.</p>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function showPropertyTypeTab(propertyTypeId) {
    // Hide all containers
    document.querySelectorAll('.tab-container').forEach(container => {
        container.classList.add('hidden');
    });
    
    // Remove active styles from all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-primary-500', 'text-primary-600', 'bg-primary-50');
        btn.classList.add('border-transparent', 'text-gray-600');
    });
    
    // Show selected container
    const container = document.getElementById('container-' + propertyTypeId);
    if (container) {
        container.classList.remove('hidden');
    }
    
    // Add active styles to selected tab
    const tab = document.getElementById('tab-' + propertyTypeId);
    if (tab) {
        tab.classList.remove('border-transparent', 'text-gray-600');
        tab.classList.add('border-primary-500', 'text-primary-600', 'bg-primary-50');
    }
}

// Initialize: Show 'all' tab by default
document.addEventListener('DOMContentLoaded', function() {
    showPropertyTypeTab('all');
});
</script>
@endpush
@endsection

