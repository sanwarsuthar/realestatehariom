@extends('admin.layouts.app')

@section('title', 'Import Project')
@section('page-title', 'Import Project')

@section('content')
<div class="admin-page-content max-w-2xl mx-auto min-w-0">
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Import Project from JSON</h2>
            <p class="text-gray-600">Upload a previously exported project JSON file to import it. All plots/flats will be imported as available (raw data, no bookings).</p>
        </div>

        @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.projects.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label for="import_file" class="block text-sm font-medium text-gray-700 mb-2">
                    Select JSON File <span class="text-red-500">*</span>
                </label>
                <input type="file" 
                       id="import_file" 
                       name="import_file" 
                       accept=".json,application/json"
                       required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <p class="mt-1 text-sm text-gray-500">Maximum file size: 10MB. File must be a valid JSON export from this system.</p>
            </div>

            <div class="flex items-center">
                <input type="checkbox" 
                       id="overwrite" 
                       name="overwrite" 
                       value="1"
                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                <label for="overwrite" class="ml-2 block text-sm text-gray-700">
                    Overwrite existing project if name matches
                </label>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Important Notes:</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>All plots/flats will be imported as <strong>available</strong> (no booking data)</li>
                                <li>If a project with the same name exists, you must check "Overwrite" to replace it</li>
                                <li>Overwriting will delete all existing plots/flats in that project</li>
                                <li>Images and videos references will be preserved but files must exist in storage</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.projects') }}" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-upload mr-2"></i>Import Project
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

