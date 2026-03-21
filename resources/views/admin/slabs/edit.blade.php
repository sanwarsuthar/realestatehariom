@extends('admin.layouts.app')

@section('title', 'Edit Slab')
@section('page-title', 'Edit Slab')

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

    <div class="bg-white rounded-2xl shadow-3d p-6 max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-800">Edit Slab: {{ $slab->name }}</h3>
            <a href="{{ route('admin.slabs.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Slabs
            </a>
        </div>

        <form method="POST" action="{{ route('admin.slabs.update', $slab) }}" class="space-y-4">
            @csrf
            @method('PUT')
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slab Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Slab1" value="{{ old('name', $slab->name) }}" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Criteria <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="minimum_target" class="w-full px-4 py-2 border rounded-lg" placeholder="0" value="{{ old('minimum_target', $slab->minimum_target) }}" required>
                    <p class="text-xs text-gray-500 mt-1">Based on area sold / business volume</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Criteria</label>
                    <input type="number" step="0.01" min="0" name="maximum_target" class="w-full px-4 py-2 border rounded-lg" placeholder="250" value="{{ old('maximum_target', $slab->maximum_target) }}">
                    <p class="text-xs text-gray-500 mt-1">Leave empty for no upper limit</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Measurement Unit <span class="text-red-500">*</span></label>
                <select name="measurement_unit_id" class="w-full px-4 py-2 border rounded-lg" required>
                    <option value="">Select unit</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('measurement_unit_id', $slab->measurement_unit_id) == $unit->id)>
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
                                @checked(in_array($propertyType->id, old('property_types', $slab->propertyTypes->pluck('id')->toArray())))
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
                <input type="number" step="0.01" min="0" max="100" name="bonus_percentage" class="w-full px-4 py-2 border rounded-lg" value="{{ old('bonus_percentage', $slab->bonus_percentage ?? 0) }}">
                <p class="text-xs text-gray-500 mt-1">Optional bonus percentage for this slab</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg">{{ old('description', $slab->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                    <input type="color" name="color_code" class="w-full h-10" value="{{ old('color_code', $slab->color_code ?? '#8B5CF6') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" class="w-full px-4 py-2 border rounded-lg" value="{{ old('sort_order', $slab->sort_order ?? 0) }}">
                </div>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit" class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Update Slab
                </button>
                <a href="{{ route('admin.slabs.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

