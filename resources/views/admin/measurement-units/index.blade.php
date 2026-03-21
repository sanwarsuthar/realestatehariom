@extends('admin.layouts.app')

@section('title', 'Measurement Units')
@section('page-title', 'Measurement Units')

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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create Form -->
        <div class="bg-white rounded-2xl shadow-3d p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Create Measurement Unit</h3>

            <form method="POST" action="{{ route('admin.measurement-units.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Square Yard" value="{{ old('name') }}" required>
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Symbol</label>
                    <input type="text" name="symbol" class="w-full px-4 py-2 border rounded-lg" placeholder="sqyd" value="{{ old('symbol') }}" maxlength="10">
                    <p class="text-xs text-gray-500 mt-1">Short symbol for this unit (e.g., sqyd, sqft)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional description">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-plus mr-2"></i>Create Measurement Unit
                </button>
            </form>
        </div>

        <!-- List -->
        <div class="bg-white rounded-2xl shadow-3d p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Existing Measurement Units</h3>

            @if($units->isEmpty())
                <p class="text-gray-500 text-center py-8">No measurement units found. Create one to get started.</p>
            @else
                <div class="space-y-3">
                    @foreach($units as $unit)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <form method="POST" action="{{ route('admin.measurement-units.update', $unit) }}" class="space-y-2">
                                @csrf
                                @method('PUT')
                                
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 space-y-2">
                                        <div>
                                            <input type="text" name="name" value="{{ $unit->name }}" class="w-full px-3 py-1 border rounded text-sm font-medium" required>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="text" name="symbol" value="{{ $unit->symbol }}" placeholder="Symbol" class="px-3 py-1 border rounded text-sm flex-1" maxlength="10">
                                            @if($unit->symbol)
                                                <span class="text-xs text-gray-500">({{ $unit->symbol }})</span>
                                            @endif
                                        </div>
                                        @if($unit->description)
                                            <textarea name="description" rows="2" class="w-full px-3 py-1 border rounded text-xs text-gray-600">{{ $unit->description }}</textarea>
                                        @else
                                            <textarea name="description" rows="2" class="w-full px-3 py-1 border rounded text-xs text-gray-400" placeholder="Add description..."></textarea>
                                        @endif
                                    </div>
                                    
                                    <div class="flex flex-col gap-2">
                                        <button type="submit" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                                            <i class="fas fa-save"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.measurement-units.destroy', $unit) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this measurement unit?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="w-full px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- Usage Info -->
                            <div class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500">
                                @php
                                    $slabCount = $unit->slabs()->count();
                                    $propertyTypeCount = $unit->propertyTypes()->count();
                                @endphp
                                @if($slabCount > 0 || $propertyTypeCount > 0)
                                    Used by: 
                                    @if($slabCount > 0)
                                        <span class="font-medium">{{ $slabCount }} slab(s)</span>
                                    @endif
                                    @if($slabCount > 0 && $propertyTypeCount > 0), @endif
                                    @if($propertyTypeCount > 0)
                                        <span class="font-medium">{{ $propertyTypeCount }} property type(s)</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">Not in use</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

