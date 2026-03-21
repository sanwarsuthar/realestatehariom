@extends('admin.layouts.app')

@section('title', 'Create Project')
@section('page-title', 'Create Project')

@section('content')
<div class="admin-page-content min-w-0">
@if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
        <strong>Please fix the following errors:</strong>
        <ul class="list-disc list-inside mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('admin.projects.store') }}" id="projectForm" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-3d p-6 space-y-6">
    @csrf
    
    <!-- Basic Information Section -->
    <div class="space-y-6">
        <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Basic Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                <input name="name" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Location <span class="text-red-500">*</span></label>
                <input name="location" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., Infront of Muhana mandi, Mansarowar 332022, Jaipur, Rajasthan" required>
                <p class="text-xs text-gray-500 mt-1">Enter full address including area, city, and state</p>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Pincode <span class="text-red-500">*</span></label>
                <input name="pincode" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="residential">Residential</option>
                    <option value="commercial">Commercial</option>
                    <option value="mixed">Mixed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="available">Available</option>
                    <option value="upcoming">Upcoming (view only)</option>
                    <option value="sold_out">Sold Out</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Latitude</label>
                <input type="number" step="0.000001" name="latitude" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Longitude</label>
                <input type="number" step="0.000001" name="longitude" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Facilities (comma separated)</label>
                <input name="facilities" class="w-full px-3 py-2 border rounded-lg" placeholder="Swimming Pool,Gym,Security">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Minimum Booking Amount (₹) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="minimum_booking_amount" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 10000" required>
                <p class="text-xs text-gray-500 mt-1">This amount will be used for all plots/properties in this project</p>
            </div>
        </div>

        <!-- Allocated Amount Configuration Section -->
        <div class="space-y-4 border-t pt-6 mt-6">
            <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Allocated Amount Configuration</h3>
            <p class="text-sm text-gray-600 mb-4">Configure fixed allocated amount separately for each property type. This amount will be distributed as commission using the same distribution logic.</p>

            <!-- Property Type Allocated Amounts -->
            @if(isset($propertyTypes) && $propertyTypes->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($propertyTypes as $propertyType)
                        @php
                            $slug = \Illuminate\Support\Str::slug($propertyType->name);
                            $measurementUnit = $propertyType->measurementUnit;
                            $unitSymbol = $measurementUnit ? $measurementUnit->symbol : '';
                        @endphp
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                {{ $propertyType->name }}
                                @if($unitSymbol)
                                    <span class="text-xs text-gray-500">({{ $unitSymbol }})</span>
                                @endif
                            </label>
                            
                            <!-- Hidden input to always set type as fixed -->
                            <input type="hidden" name="allocated_amount_config[{{ $propertyType->name }}][type]" value="fixed">
                            
                            <!-- Fixed Amount Input -->
                            <div class="allocated-amount-input">
                                <label class="block text-xs text-gray-600 mb-1">Fixed Allocated Amount (₹)</label>
                                <div class="flex items-center">
                                    <span class="mr-2 text-gray-600 font-medium">₹</span>
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        name="allocated_amount_config[{{ $propertyType->name }}][value]" 
                                        class="w-full px-3 py-2 border rounded-lg" 
                                        placeholder="e.g., 1500"
                                        value="0"
                                        required
                                    >
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Fixed amount to be distributed as commission for this property type</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">No property types found. Please create property types first in Settings.</p>
                </div>
            @endif
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Project Images <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Add images one by one. Supported formats: JPG, PNG, GIF, WebP (Max 10MB each)</p>
            
            <!-- Hidden file input -->
            <input type="file" id="projectImageInput" accept="image/*" class="hidden">
            
            <!-- Add Image Button -->
            <button type="button" onclick="document.getElementById('projectImageInput').click()" 
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors mb-4">
                <i class="fas fa-plus mr-2"></i>Add Image
            </button>
            
            <!-- Image Preview Container -->
            <div id="imagePreview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            
            <!-- Image count indicator -->
            <p id="imageCount" class="text-xs text-gray-500 mt-2">
                <span id="imageCountNumber">0</span> image(s) selected
            </p>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Videos (comma separated URLs)</label>
            <input name="videos" class="w-full px-3 py-2 border rounded-lg" placeholder="https://youtube.com/watch?v=..., https://vimeo.com/...">
            <p class="text-xs text-gray-500 mt-1">Enter video URLs separated by commas (e.g., YouTube, Vimeo links)</p>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Floor Plan PDF</label>
            <input type="file" name="floor_plan_pdf" accept="application/pdf" class="w-full px-3 py-2 border rounded-lg">
            <p class="text-xs text-gray-500 mt-1">Upload floor plan PDF file (Max 10MB)</p>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Description <span class="text-red-500">*</span></label>
            <textarea name="description" class="w-full px-3 py-2 border rounded-lg" rows="4" required></textarea>
        </div>
    </div>

 
    <div class="flex items-center justify-end gap-2 pt-4 border-t">
        <a href="{{ route('admin.projects') }}" class="px-4 py-2 rounded-lg bg-gray-100">Cancel</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Create Project</button>
    </div>
</form>
</div>

@push('scripts')
<script>
let imageCounter = 0;
const selectedImages = [];


document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('projectImageInput');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) {
                return;
            }
            
            if (file.type.startsWith('image/')) {
                // Check file size (10MB max to match backend validation)
                if (file.size > 10 * 1024 * 1024) {
                    alert('Image size must be less than 10MB. Current size: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB');
                    this.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid image type. Please use JPG, PNG, GIF, or WebP format.');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageId = 'image_' + imageCounter++;
                    selectedImages.push({
                        id: imageId,
                        file: file,
                        preview: e.target.result
                    });
                    
                    // Create preview div
                    const div = document.createElement('div');
                    div.id = imageId;
                    div.className = 'relative border border-gray-200 rounded-lg overflow-hidden';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="w-full h-32 object-cover">
                        <div class="absolute top-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded truncate max-w-[80%]">
                            ${file.name}
                        </div>
                        <button type="button" onclick="removeImage('${imageId}')" 
                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition-colors">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    `;
                    imagePreview.appendChild(div);
                    
                    // Update image count
                    updateImageCount();
                };
                reader.readAsDataURL(file);
                
                // Reset the input so the same file can be selected again if needed
                this.value = '';
            } else {
                alert('Please select a valid image file');
                this.value = '';
            }
        });
    }
});

function removeImage(imageId) {
    if (confirm('Are you sure you want to remove this image?')) {
        // Remove from selectedImages array
        const index = selectedImages.findIndex(img => img.id === imageId);
        if (index > -1) {
            selectedImages.splice(index, 1);
        }
        
        // Remove preview div
        const previewDiv = document.getElementById(imageId);
        if (previewDiv) {
            previewDiv.remove();
        }
        
        // Update image count
        updateImageCount();
    }
}

function updateImageCount() {
    const countElement = document.getElementById('imageCountNumber');
    if (countElement) {
        countElement.textContent = selectedImages.length;
    }
}

// Handle form submission with FormData
document.getElementById('projectForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedImages.length === 0) {
        alert('Please add at least one project image');
        return false;
    }
    
    // Create FormData from the form
    const formData = new FormData(this);
    
    // Clear existing images[] entries
    formData.delete('images[]');
    
    // Add all selected images with proper validation
    selectedImages.forEach(function(img, index) {
        if (!img.file) {
            console.error('Image at index ' + index + ' has no file object');
            return;
        }
        
        // Validate file before appending
        if (!img.file.type || !img.file.type.startsWith('image/')) {
            console.error('Invalid file type for image at index ' + index);
            return;
        }
        
        formData.append('images[]', img.file, img.file.name);
    });
    
    // Show loading state
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    // Submit via AJAX
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        redirect: 'follow'
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        const isJson = contentType && contentType.includes('application/json');
        
        // Handle JSON responses (success or error)
        if (isJson) {
            const data = await response.json();
            
            if (data.success && data.redirect) {
                // Success - redirect to project page
                window.location.href = data.redirect;
                return;
            } else if (data.errors || data.message) {
                // Error - show detailed error message
                let errorMsg = data.message || 'Failed to create project';
                if (data.errors) {
                    const errorList = Object.values(data.errors).flat();
                    if (errorList.length > 0) {
                        errorMsg = errorList.join('\n');
                    }
                }
                
                // Show file info if available
                if (data.file_info) {
                    console.error('File upload errors:', data.file_info);
                    const fileErrors = [];
                    Object.keys(data.file_info).forEach(index => {
                        const file = data.file_info[index];
                        if (file.detailed_error || file.size_error) {
                            fileErrors.push(`Image ${parseInt(index) + 1} (${file.name}): ${file.detailed_error || file.size_error}`);
                        }
                    });
                    if (fileErrors.length > 0) {
                        errorMsg += '\n\nFile Issues:\n' + fileErrors.join('\n');
                    }
                }
                
                alert(errorMsg);
                throw new Error(errorMsg);
            }
        }
        
        // Handle HTML redirects (fallback for non-AJAX requests)
        if (response.redirected || response.status === 302) {
            window.location.href = response.url;
            return;
        }
        
        // If response is OK but no JSON, assume success
        if (response.ok) {
            window.location.href = this.action.replace('/create', '');
            return;
        }
        
        // If we get here, something went wrong
        throw new Error('Failed to create project. Please check your inputs.');
    })
    .catch(error => {
        alert('Error: ' + error.message);
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});
</script>
@endpush
@endsection
