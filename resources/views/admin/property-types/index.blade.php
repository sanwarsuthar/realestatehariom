@extends('admin.layouts.app')

@section('title', 'Property Types')
@section('page-title', 'Property Types')

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
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Create Property Type</h3>

            <form method="POST" action="{{ route('admin.property-types.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="w-full px-4 py-2 border rounded-lg" placeholder="Plot / Villa" value="{{ old('name') }}" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Measurement Type</label>
                    <select name="measurement_unit_id" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select measurement</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('measurement_unit_id') == $unit->id)>
                                {{ $unit->name }} @if($unit->symbol) ({{ $unit->symbol }}) @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Measurement unit is used when defining project areas for this category.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional notes">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" value="1" class="w-5 h-5 text-primary-500 rounded" @checked(old('is_active', true))>
                    <span class="text-sm text-gray-700">Active</span>
                </div>

                <button type="submit" class="px-6 py-3 bg-primary-600 text-white rounded-lg w-full">Create Property Type</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-3d p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Existing Property Types</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Measurement</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($propertyTypes as $type)
                            <tr>
                                <td class="px-4 py-2 font-semibold">{{ $type->name }}</td>
                                <td class="px-4 py-2">
                                    {{ $type->measurementUnit?->name ?? '—' }}
                                    @if($type->measurementUnit?->symbol)
                                        <span class="text-xs text-gray-500">({{ $type->measurementUnit->symbol }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $type->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $type->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <form method="POST" action="{{ route('admin.property-types.destroy', $type) }}" onsubmit="return confirm('Delete this property type?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="px-3 py-1 text-xs rounded-lg bg-red-50 text-red-600 hover:bg-red-100">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-gray-500">No property types created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

