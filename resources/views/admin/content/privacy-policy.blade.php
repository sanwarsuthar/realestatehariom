@extends('admin.layouts.app')

@section('title', 'Privacy Policy')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Privacy Policy</h1>
                <p class="text-primary-100">Manage the Privacy Policy page content</p>
            </div>
        </div>
    </div>

    <!-- Content Form -->
    <div class="bg-white rounded-2xl shadow-3d overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Privacy Policy Content</h3>
        </div>
        
        <div class="p-6">
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.content.privacy-policy.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <!-- HTML File Upload Option -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="text-sm font-semibold text-blue-800 mb-3">
                        <i class="fas fa-upload mr-2"></i>Option 1: Upload HTML File
                    </h4>
                    <div class="flex items-center space-x-4">
                        <input type="file" name="html_file" id="html_file" accept=".html,.htm"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700">
                        <p class="text-xs text-gray-600">Upload an HTML file (max 10MB)</p>
                    </div>
                    @error('html_file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4 text-center text-gray-500 font-semibold">OR</div>

                <!-- HTML Content Editor -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-code mr-2"></i>Option 2: Paste HTML Content
                    </label>
                    <textarea name="content" id="content" rows="20"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
                              placeholder="Paste your HTML content here... You can include HTML tags, CSS styles, etc.">{{ old('content', $content) }}</textarea>
                    <p class="mt-2 text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        You can paste HTML content directly here. The HTML will be displayed as-is on the website and app.
                    </p>
                    @error('content')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Preview Section -->
                @if($content)
                <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">
                        <i class="fas fa-eye mr-2"></i>Current Content Preview
                    </h4>
                    <div class="max-h-60 overflow-y-auto border border-gray-300 rounded p-3 bg-white">
                        {!! $content !!}
                    </div>
                </div>
                @endif

                <!-- JavaScript to load HTML file content into textarea -->
                <script>
                    document.getElementById('html_file').addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                document.getElementById('content').value = e.target.result;
                            };
                            reader.readAsText(file);
                        }
                    });
                </script>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.dashboard') }}" 
                       class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

