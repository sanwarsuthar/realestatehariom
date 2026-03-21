@extends('admin.layouts.app')

@section('title', 'Edit Project')
@section('page-title', 'Edit Project')

@section('content')
<div class="admin-page-content min-w-0">
<form method="POST" action="{{ route('admin.projects.update', $project->id) }}" id="projectForm" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-3d p-6 space-y-6">
    @csrf
    @method('PUT')
    
    <!-- Basic Information Section -->
    <div class="space-y-6">
        <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Basic Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                <input name="name" value="{{ $project->name }}" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Location <span class="text-red-500">*</span></label>
                <input name="location" value="{{ trim($project->location . ($project->city ? ', ' . $project->city : '') . ($project->state ? ', ' . $project->state : '')) }}" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., Infront of Muhana mandi, Mansarowar 332022, Jaipur, Rajasthan" required>
                <p class="text-xs text-gray-500 mt-1">Enter full address including area, city, and state</p>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Pincode <span class="text-red-500">*</span></label>
                <input name="pincode" value="{{ $project->pincode }}" class="w-full px-3 py-2 border rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="residential" @selected($project->type==='residential')>Residential</option>
                    <option value="commercial" @selected($project->type==='commercial')>Commercial</option>
                    <option value="mixed" @selected($project->type==='mixed')>Mixed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                <select name="status" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="available" @selected($project->status==='available')>Available</option>
                    <option value="upcoming" @selected($project->status==='upcoming')>Upcoming (view only)</option>
                    <option value="sold_out" @selected($project->status==='sold_out')>Sold Out</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Latitude</label>
                <input type="number" step="0.000001" name="latitude" value="{{ $project->latitude }}" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Longitude</label>
                <input type="number" step="0.000001" name="longitude" value="{{ $project->longitude }}" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Facilities (comma separated)</label>
                <input name="facilities" value="{{ is_array($project->facilities) ? implode(',', $project->facilities) : $project->facilities }}" class="w-full px-3 py-2 border rounded-lg" placeholder="Swimming Pool,Gym,Security">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Minimum Booking Amount (₹) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="minimum_booking_amount" value="{{ $project->minimum_booking_amount ?? 0 }}" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 10000" required>
                <p class="text-xs text-gray-500 mt-1">This amount will be used for all plots/properties in this project</p>
            </div>
        </div>

        <!-- Allocated Amount Configuration Section -->
        <div class="space-y-4 border-t pt-6 mt-6">
            <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Allocated Amount Configuration</h3>
            <p class="text-sm text-gray-600 mb-4">Configure fixed allocated amount separately for each property type. This amount will be distributed as commission using the same distribution logic.</p>
            
            @php
                $allocatedConfig = $project->allocated_amount_config ?? [];
            @endphp

            <!-- Property Type Allocated Amounts -->
            @if(isset($propertyTypes) && $propertyTypes->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($propertyTypes as $propertyType)
                        @php
                            $slug = \Illuminate\Support\Str::slug($propertyType->name);
                            $measurementUnit = $propertyType->measurementUnit;
                            $unitSymbol = $measurementUnit ? $measurementUnit->symbol : '';
                            $config = $allocatedConfig[$propertyType->name] ?? ['type' => 'fixed', 'value' => 0];
                            $configType = $config['type'] ?? 'fixed';
                            $configValue = $config['value'] ?? 0;
                            // If old config has percentage type, convert to fixed (use 0 as default)
                            if ($configType === 'percentage') {
                                $configValue = 0;
                            }
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
                                        value="{{ $configType === 'fixed' ? $configValue : 0 }}"
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
            <label class="block text-sm text-gray-600 mb-1">Project Images</label>
            <p class="text-xs text-gray-500 mb-2">Upload new images to add to existing ones, or keep existing images.</p>
            
            <!-- Existing Images -->
            @if(is_array($project->images) && count($project->images) > 0)
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Images</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    @foreach($project->images as $index => $imageUrl)
                    <div class="relative border border-gray-200 rounded-lg overflow-hidden existing-image-container">
                        <img src="{{ $imageUrl }}" alt="Image {{ $index + 1 }}" class="w-full h-32 object-cover">
                        <input type="hidden" name="existing_images[]" value="{{ $imageUrl }}" class="existing-image-input">
                        <button type="button" onclick="removeExistingImage(this)" class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- New Image Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Add New Images</label>
                <input type="file" name="images[]" id="projectImages" accept="image/*" multiple class="w-full px-3 py-2 border rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Select multiple images to add. Supported formats: JPG, PNG, GIF, WebP (Max 5MB each)</p>
                <div id="imagePreview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            </div>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Videos (comma separated URLs)</label>
            <input name="videos" value="{{ is_array($project->videos) ? implode(',', $project->videos) : $project->videos }}" class="w-full px-3 py-2 border rounded-lg" placeholder="https://youtube.com/watch?v=..., https://vimeo.com/...">
            <p class="text-xs text-gray-500 mt-1">Enter video URLs separated by commas (e.g., YouTube, Vimeo links)</p>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Floor Plan PDF</label>
            @if($project->floor_plan_pdf)
                <div class="mb-2 p-3 bg-gray-50 border rounded-lg">
                    <p class="text-sm text-gray-700 mb-2">Current Floor Plan:</p>
                    <a href="{{ url($project->floor_plan_pdf) }}" target="_blank" class="text-primary-600 hover:underline inline-flex items-center">
                        <i class="fas fa-file-pdf mr-2"></i>View Current PDF
                    </a>
                </div>
            @endif
            <input type="file" name="floor_plan_pdf" accept="application/pdf" class="w-full px-3 py-2 border rounded-lg">
            <p class="text-xs text-gray-500 mt-1">Upload new floor plan PDF to replace existing one (Max 10MB)</p>
        </div>

        <div>
            <label class="block text-sm text-gray-600 mb-1">Description</label>
            <textarea name="description" class="w-full px-3 py-2 border rounded-lg" rows="4">{{ $project->description }}</textarea>
        </div>
    </div>

  

    <!-- Properties Grid Section -->
    @php
        use Illuminate\Support\Str;

        $propertyTypeConfig = isset($propertyTypes)
            ? $propertyTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => Str::slug($type->name),
                    'measurement' => optional($type->measurementUnit)->name,
                ];
            })
            : collect();

        $propertyTypeLabelMap = $propertyTypeConfig->pluck('name', 'slug');
        $propertyTypeMeasurementMap = $propertyTypeConfig->pluck('measurement', 'slug');
        $colorPalette = [
            'bg-primary-100 text-primary-800',
            'bg-green-100 text-green-800',
            'bg-yellow-100 text-yellow-800',
            'bg-blue-100 text-blue-800',
            'bg-orange-100 text-orange-800',
            'bg-purple-100 text-purple-800',
            'bg-pink-100 text-pink-800',
        ];
        $propertyTypeColorMap = [];
        foreach ($propertyTypeConfig as $index => $config) {
            $propertyTypeColorMap[$config['slug']] = $colorPalette[$index % count($colorPalette)];
        }
    @endphp

    <!-- Existing Grids Section -->
    @if(isset($gridBatches) && $gridBatches->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-3d p-6 mt-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">Existing Property Grids</h4>
        <div class="space-y-6">
            @foreach($gridBatches as $batch)
            <div class="border-2 border-gray-200 rounded-lg p-4" id="grid-batch-{{ $batch['batch_id'] }}">
                <div class="flex items-center justify-between mb-3">
                    <h5 class="text-md font-semibold text-gray-700" id="batch-name-{{ $batch['batch_id'] }}">
                        <span class="batch-name-display">{{ $batch['batch_name'] }}</span>
                        <span class="text-sm text-gray-500 font-normal">({{ $batch['count'] }} units)</span>
                    </h5>
                    <div class="flex items-center gap-2">
                        <button 
                            type="button" 
                            onclick="loadGridForEditing('{{ $batch['batch_id'] }}', {{ json_encode($batch['plots']->map(function($plot) use ($batch, $propertyTypeColorMap, $propertyTypeLabelMap, $propertyTypeMeasurementMap) {
                                $slug = Str::slug($plot->type);
                                return [
                                    'id' => (int)$plot->id, // Ensure ID is integer
                                    'plot_number' => $plot->plot_number,
                                    'type' => $plot->type,
                                    'type_slug' => $slug,
                                    'size' => (float)$plot->size,
                                    'price_per_unit' => (float)($plot->price_per_unit ?? 0),
                                    'status' => $plot->status,
                                    'grid_batch_id' => $batch['batch_id'], // Include batch ID for filtering
                                ];
                            })->values()->all()) }})"
                            class="px-3 py-1 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit Grid
                        </button>
                        <button 
                            type="button" 
                            onclick="editGridBatch('{{ $batch['batch_id'] }}', '{{ addslashes($batch['batch_name']) }}')"
                            class="px-3 py-1 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                            <i class="fas fa-tag mr-1"></i> Edit Name
                        </button>
                        <form 
                            method="POST" 
                            action="{{ route('admin.projects.grid-batches.delete', ['project' => $project->id, 'gridBatchId' => $batch['batch_id']]) }}"
                            class="inline"
                            onsubmit="return confirm('Are you sure you want to delete this grid? All {{ $batch['count'] }} units in this grid will be permanently deleted. This cannot be undone!');">
                            @csrf
                            @method('DELETE')
                            <button 
                                type="submit"
                                class="px-3 py-1 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach($batch['plots'] as $unit)
                        @php
                            $slug = Str::slug($unit->type);
                            $colorClass = $propertyTypeColorMap[$slug] ?? 'bg-gray-100 text-gray-800';
                            $displayName = $propertyTypeLabelMap[$slug] ?? Str::title(str_replace(['-', '_'], ' ', $unit->type));
                            $measurementLabel = $propertyTypeMeasurementMap[$slug] ?? null;
                            $sale = $unit->sales->first();
                            $statusBadge = [
                                'available' => 'bg-green-100 text-green-700 border-green-300',
                                'pending_booking' => 'bg-purple-100 text-purple-700 border-purple-300',
                                'booked' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                                'sold' => 'bg-red-100 text-red-700 border-red-300'
                            ][$unit->status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                        @endphp
                        <div class="border-2 {{ $statusBadge }} rounded-lg p-3 hover:shadow-md transition-shadow">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold text-sm">#{{ $unit->plot_number }}</span>
                                <span class="px-2 py-1 text-xs rounded {{ $statusBadge }} capitalize">{{ ucfirst($unit->status) }}</span>
                            </div>
                            
                            <!-- Property Type -->
                            <div class="mb-2">
                                <div class="text-xs text-gray-600">Type</div>
                                <div class="text-sm font-medium {{ $colorClass }} px-2 py-1 rounded inline-block">
                                    {{ $displayName }}
                                    @if($measurementLabel)
                                        <span class="text-xs">({{ $measurementLabel }})</span>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Size -->
                            @if($unit->size)
                            <div class="mb-2">
                                <div class="text-xs text-gray-600">Size</div>
                                <div class="text-sm font-medium text-gray-800">
                                    {{ number_format($unit->size, 2) }} 
                                    @if($measurementLabel)
                                        <span class="text-xs text-gray-600">{{ $measurementLabel }}</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            <!-- Price -->
                            @if($unit->price_per_unit)
                            <div class="mb-2">
                                <div class="text-xs text-gray-600">Price per Unit</div>
                                <div class="text-sm font-medium text-gray-800">
                                    ₹{{ number_format($unit->price_per_unit, 2) }}
                                    @if($measurementLabel)
                                        <span class="text-xs text-gray-600">/ {{ $measurementLabel }}</span>
                                    @endif
                                </div>
                            </div>
                            @endif
                            
                            <!-- Total Value -->
                            @if($unit->size && $unit->price_per_unit)
                            <div class="mb-2">
                                <div class="text-xs text-gray-600">Total Value</div>
                                <div class="text-sm font-bold text-gray-900">
                                    ₹{{ number_format($unit->size * $unit->price_per_unit, 2) }}
                                </div>
                            </div>
                            @endif
                            
                            <!-- Booking Amount -->
                            @if($unit->minimum_booking_amount)
                            <div class="mb-2">
                                <div class="text-xs text-gray-600">Min. Booking</div>
                                <div class="text-sm font-medium text-gray-800">
                                    ₹{{ number_format($unit->minimum_booking_amount, 2) }}
                                </div>
                            </div>
                            @endif
                            
                            <!-- Booking Status -->
                            @if($sale)
                            <div class="mt-2 pt-2 border-t border-gray-300">
                                <div class="text-xs text-gray-600">Booked By</div>
                                <div class="text-sm font-medium text-gray-800">
                                    @if($sale->customer)
                                        {{ $sale->customer->name }}
                                        @if($sale->customer->phone_number)
                                            <br><span class="text-xs text-gray-600">{{ $sale->customer->phone_number }}</span>
                                        @endif
                                    @elseif($sale->customer_name)
                                        {{ $sale->customer_name }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                                @if($sale->booking_amount)
                                <div class="text-xs text-gray-600 mt-1">Booking Amount</div>
                                <div class="text-xs font-medium text-gray-800">₹{{ number_format($sale->booking_amount, 2) }}</div>
                                @endif
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-3d p-6 mt-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">
            <span id="grid-section-title">Create New Properties Grid</span>
        </h4>

        @if($propertyTypeConfig->isEmpty())
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                No property types found. Please create property types first from the
                <a href="{{ route('admin.property-types.index') }}" class="font-semibold text-yellow-900 underline">Property Types</a>
                section, then return here to build the project grid.
            </div>
        @else
        <form id="grid-form" method="POST" action="{{ route('admin.projects.plots.store', ['project' => $project->id]) }}" class="space-y-4">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Grid Name (Optional)
                </label>
                <input
                    type="text"
                    name="grid_batch_name"
                    id="grid_batch_name"
                    class="w-full px-3 py-2 border rounded-lg"
                    placeholder="e.g., Phase 1 - Small Plots, Phase 2 - Large Villas"
                    value=""
                >
                <p class="text-xs text-gray-500 mt-1">Give this grid a name to identify it later (e.g., Phase 1, Block A, etc.)</p>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                Set how many units of each property type you want to generate for this grid. Measurements for each type come from the Property Types setup. You can create multiple grids with different sizes and prices.
            </p>

            <!-- Property Type Configuration Section -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-3">Default Values (Applied to New Properties)</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($propertyTypeConfig as $type)
                        @php
                            $slug = $type['slug'];
                            $measurementLabel = $type['measurement'];
                        @endphp
                        <div class="p-4 bg-white border rounded-lg space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $type['name'] }} Quantity
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value="0"
                                    class="w-full px-3 py-2 border rounded-lg property-type-input"
                                    data-type-input="{{ $slug }}"
                                    id="type-input-{{ $slug }}"
                                    placeholder="Quantity"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Property Size @if($measurementLabel) ({{ $measurementLabel }}) @endif
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="0"
                                    class="w-full px-3 py-2 border rounded-lg property-size-input"
                                    data-type-size="{{ $slug }}"
                                    id="size-input-{{ $slug }}"
                                    placeholder="e.g., 1000"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Price per Unit (₹)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value="0"
                                    class="w-full px-3 py-2 border rounded-lg property-price-input"
                                    data-type-price="{{ $slug }}"
                                    id="price-input-{{ $slug }}"
                                    placeholder="e.g., 5000"
                                >
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-gray-500">Click "Generate Grid" to preview the layout. You can adjust counts and regenerate at any time.</p>
                <button type="button" id="generate-grid" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Generate Grid</button>
            </div>

            <!-- Grid Editing Banner (shown when editing existing grid) -->
            <div id="grid-editing-banner" class="hidden mt-4 mb-4 p-4 bg-blue-50 border-2 border-blue-300 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                        <div>
                            <p class="text-sm font-semibold text-blue-900">
                                Editing Grid: <span id="editing-grid-name-display" class="font-bold"></span>
                            </p>
                            <p class="text-xs text-blue-700 mt-1">
                                You can modify plots below. Click "Update & Save" button to save your changes.
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        onclick="cancelGridEditing()"
                        class="px-3 py-1 text-sm rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-1"></i> Cancel Editing
                    </button>
                </div>
            </div>
            
            <div id="grid-container" class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>

            <input type="hidden" name="grid" id="grid-input">
            <input type="hidden" name="editing_grid_batch_id" id="grid-editing-batch-id" value="">
            <div class="flex items-center justify-between pt-4 border-t">
                <p class="text-xs text-gray-500">Click a box to cycle through the property types you have defined.</p>
                <!-- Save button for new grids -->
                <button type="submit" id="save-grid-btn" class="px-6 py-2 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors">
                    <span id="save-grid-btn-text">Save This Grid</span>
                </button>
                <!-- Update & Save button for editing existing grids (hidden by default) -->
                <button type="button" id="update-save-grid-btn" class="hidden px-6 py-2 rounded-lg bg-green-600 text-white font-semibold hover:bg-green-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Update & Save
                </button>
            </div>
        </form>
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
            <strong>💡 Tip:</strong> After saving a grid, you can create another grid with different property sizes and prices. Each grid will be displayed above, and commissions will be calculated based on the specific property's size and price from its grid.
        </div>
        @endif
    </div>

    <div class="flex items-center justify-end gap-2 pt-4 border-t">
        <a href="{{ route('admin.projects.show', $project->id) }}" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Cancel</a>
        <button type="button" id="finishButton" class="px-6 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 font-semibold">
            <i class="fas fa-check mr-2"></i>Finish
        </button>
    </div>
</form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('projectImages');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(e) {
            imagePreview.innerHTML = ''; // Clear previous previews
            
            const files = Array.from(e.target.files);
            if (files.length === 0) {
                return;
            }
            
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'relative border border-gray-200 rounded-lg overflow-hidden';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-32 object-cover">
                            <div class="absolute top-2 right-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                ${file.name}
                            </div>
                        `;
                        imagePreview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    }

    // Property Grid Scripts (similar to show.blade.php)
    const propertyTypes = @json($propertyTypeConfig);
    const propertyTypeColors = @json($propertyTypeColorMap);
    const propertyTypeLabels = @json($propertyTypeLabelMap);
    const propertyTypeMeasurements = @json($propertyTypeMeasurementMap);
    const existingPlots = @json($existingPlots ?? []);

    const container = document.getElementById('grid-container');
    const input = document.getElementById('grid-input');
    const generateBtn = document.getElementById('generate-grid');
    const typeInputs = document.querySelectorAll('.property-type-input');
    const priceInputs = document.querySelectorAll('.property-price-input');
    const sizeInputs = document.querySelectorAll('.property-size-input');
    
    // Get project's booking amount from backend
    const projectBookingAmount = {{ $project->minimum_booking_amount ?? 0 }};

    if (!container || !input) {
        return;
    }
    
    priceInputs.forEach(inputEl => {
        inputEl.addEventListener('input', sync);
    });
    
    sizeInputs.forEach(inputEl => {
        inputEl.addEventListener('input', sync);
    });

    if (!propertyTypes.length) {
        if (generateBtn) {
            generateBtn.setAttribute('disabled', 'disabled');
            generateBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        return;
    }

    const typeOrder = propertyTypes.map(pt => pt.slug);
    const typeMap = {};

    propertyTypes.forEach(pt => {
        typeMap[pt.slug] = pt;
    });

    function buildSequenceFromInputs() {
        const sequence = [];
        typeInputs.forEach(inputEl => {
            const slug = inputEl.getAttribute('data-type-input');
            const count = parseInt(inputEl.value || '0', 10);
            if (!isNaN(count) && count > 0) {
                for (let i = 0; i < count; i++) {
                    sequence.push(slug);
                }
            }
        });
        return sequence;
    }

    function buildGrid(sequence) {
        container.innerHTML = '';
        
        // Clear editing state when generating new grid
        const editingBanner = document.getElementById('grid-editing-banner');
        if (editingBanner) {
            editingBanner.classList.add('hidden');
        }
        const gridEditingInput = document.getElementById('grid-editing-batch-id');
        if (gridEditingInput) gridEditingInput.value = '';
        const titleElement = document.getElementById('grid-section-title');
        if (titleElement) titleElement.textContent = 'Create New Properties Grid';
        const saveBtnText = document.getElementById('save-grid-btn-text');
        if (saveBtnText) saveBtnText.textContent = 'Save This Grid';

        if (!sequence.length) {
            container.innerHTML = '<p class="col-span-full text-sm text-gray-500">Set at least one property type quantity, then click "Generate Grid".</p>';
            input.value = JSON.stringify([]);
            return;
        }

        sequence.forEach((slug, index) => {
            const definition = typeMap[slug] || {};
            const label = propertyTypeLabels[slug] || definition.name || slug;
            const measurement = propertyTypeMeasurements[slug] || '';
            const colorClass = propertyTypeColors[slug] || 'bg-gray-100 text-gray-800';
            
            const priceInput = document.getElementById(`price-input-${slug}`);
            const sizeInput = document.getElementById(`size-input-${slug}`);
            const defaultPrice = priceInput ? parseFloat(priceInput.value) || 0 : 0;
            const defaultSize = sizeInput ? parseFloat(sizeInput.value) || 0 : 0;
            // Use project's booking amount
            const defaultBooking = projectBookingAmount || 0;

            const cell = document.createElement('div');
            cell.className = `border-2 border-gray-200 rounded-xl p-3 bg-white hover:border-primary-500 transition-colors`;
            cell.dataset.type = slug;
            cell.dataset.index = index;
            cell.dataset.label = label;
            cell.dataset.number = index + 1;
            
            cell.innerHTML = `
                <div class="space-y-2">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-1 rounded text-xs font-semibold ${colorClass}">${label}</span>
                        <button type="button" onclick="removeProperty(this)" class="text-red-500 hover:text-red-700 text-xs">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Property Name</label>
                        <input type="text" 
                               class="property-name-input w-full px-2 py-1 text-sm border rounded" 
                               value="${label} #${index + 1}"
                               placeholder="e.g., Plot A-101"
                               data-property-index="${index}">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Size (${measurement})</label>
                        <input type="number" 
                               step="0.01"
                               class="property-size-input w-full px-2 py-1 text-sm border rounded" 
                               value="${defaultSize}"
                               placeholder="Size"
                               data-property-index="${index}">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Price per Unit (₹)</label>
                        <input type="number" 
                               step="0.01"
                               class="property-price-input w-full px-2 py-1 text-sm border rounded" 
                               value="${defaultPrice}"
                               placeholder="Price"
                               data-property-index="${index}">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Min. Booking (₹)</label>
                        <input type="number" 
                               step="0.01"
                               class="property-booking-input w-full px-2 py-1 text-sm border rounded bg-gray-100" 
                               value="${defaultBooking}"
                               placeholder="Booking"
                               data-property-index="${index}"
                               readonly
                               title="Booking amount is set at project level">
                    </div>
                    <div class="pt-2">
                        <button type="button" onclick="changePropertyType(this)" class="w-full px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
                            Change Type
                        </button>
                    </div>
                </div>
            `;

            const nameInput = cell.querySelector('.property-name-input');
            const sizeInputEl = cell.querySelector('.property-size-input');
            const priceInputEl = cell.querySelector('.property-price-input');
            const bookingInputEl = cell.querySelector('.property-booking-input');
            
            [nameInput, sizeInputEl, priceInputEl, bookingInputEl].forEach(input => {
                if (input) {
                    input.addEventListener('input', sync);
                    input.addEventListener('blur', sync);
                }
            });

            container.appendChild(cell);
        });
        
        window.changePropertyType = function(button) {
            const cell = button.closest('.border-2');
            if (!cell || !typeOrder.length) return;
            
            const current = cell.dataset.type;
            const currentIndex = typeOrder.indexOf(current);
            const nextIndex = currentIndex === -1 ? 0 : (currentIndex + 1) % typeOrder.length;
            const nextSlug = typeOrder[nextIndex];
            const nextDefinition = typeMap[nextSlug] || {};
            const nextLabel = propertyTypeLabels[nextSlug] || nextDefinition.name || nextSlug;
            const nextMeasurement = propertyTypeMeasurements[nextSlug] || '';
            const nextColor = propertyTypeColors[nextSlug] || 'bg-gray-100 text-gray-800';
            
            cell.dataset.type = nextSlug;
            cell.dataset.label = nextLabel;
            
            const badge = cell.querySelector('.px-2');
            if (badge) {
                badge.className = `px-2 py-1 rounded text-xs font-semibold ${nextColor}`;
                badge.textContent = nextLabel;
            }
            
            const sizeLabel = cell.querySelector('.property-size-input')?.previousElementSibling;
            if (sizeLabel && sizeLabel.tagName === 'LABEL') {
                sizeLabel.textContent = `Size (${nextMeasurement})`;
            }
            
            sync();
        };
        
        window.removeProperty = function(button) {
            if (confirm('Are you sure you want to remove this property?')) {
                const cell = button.closest('.border-2');
                if (cell) {
                    cell.remove();
                    sync();
                }
            }
        };

        sync();
    }

    function sync() {
        const payload = Array.from(container.children).map((el, index) => {
            const typeSlug = el.dataset.type;
            const plotId = el.dataset.plotId || null; // Include plot ID if editing existing plot
            
            const nameInput = el.querySelector('.property-name-input');
            const sizeInput = el.querySelector('.property-size-input');
            const priceInput = el.querySelector('.property-price-input');
            
            const plotNumber = nameInput ? (nameInput.value.trim() || `${el.dataset.label} #${el.dataset.number}`) : `${el.dataset.label} #${el.dataset.number}`;
            const size = sizeInput ? parseFloat(sizeInput.value) || 0 : 0;
            const pricePerUnit = priceInput ? parseFloat(priceInput.value) || 0 : 0;
            // Use project's booking amount
            const minBooking = projectBookingAmount || 0;
            
            const plotData = {
                plot_number: plotNumber,
                type: typeSlug,
                size: size,
                price_per_unit: pricePerUnit,
                minimum_booking_amount: minBooking,
            };
            
            // Include plot ID if editing existing plot (convert to integer)
            if (plotId && plotId !== '') {
                plotData.id = parseInt(plotId, 10);
            }
            
            return plotData;
        });
        
        // Debug logging
        const editingBatchId = document.getElementById('grid-editing-batch-id')?.value;
        if (editingBatchId) {
            console.log('Syncing grid data (editing mode):', {
                editingBatchId: editingBatchId,
                plotCount: payload.length,
                firstPlot: payload[0],
                plotsWithIds: payload.filter(p => p.id).length,
            });
        }
        
        input.value = JSON.stringify(payload);
    }

    // Don't auto-load existing plots - they will be loaded when user clicks "Edit Grid"
    // This prevents loading all plots from all grids automatically
    // Plots are only loaded via loadGridForEditing() function when user clicks "Edit Grid" button

    if (generateBtn) {
        generateBtn.addEventListener('click', function () {
            const sequence = buildSequenceFromInputs();
            if (!sequence.length) {
                alert('Please enter at least one property type quantity before generating the grid.');
                return;
            }

            buildGrid(sequence);
        });
    }

    // Handle form submission - clear form after successful save
    const gridForm = document.getElementById('grid-form');
    if (gridForm) {
        gridForm.addEventListener('submit', function(e) {
            const gridInput = document.getElementById('grid-input');
            const gridData = gridInput ? JSON.parse(gridInput.value || '[]') : [];
            const editingBatchId = document.getElementById('grid-editing-batch-id')?.value;
            
            console.log('Form submission:', {
                editingBatchId: editingBatchId,
                gridDataLength: gridData.length,
                gridData: gridData,
                firstPlot: gridData[0],
            });
            
            if (!gridData || gridData.length === 0) {
                e.preventDefault();
                alert('Please generate a grid first before saving.');
                return false;
            }
        });
    }

    // Clear form after successful save (if page reloads with success message)
    @if(session('success'))
        // Clear form inputs after successful save
        setTimeout(function() {
            // Clear property type inputs
            typeInputs.forEach(input => input.value = '0');
            priceInputs.forEach(input => input.value = '0');
            sizeInputs.forEach(input => input.value = '0');
            
            // Clear grid container
            if (container) container.innerHTML = '';
            if (input) input.value = JSON.stringify([]);
            
            // Clear grid batch name
            const batchNameInput = document.getElementById('grid_batch_name');
            if (batchNameInput) batchNameInput.value = '';
            
            // Clear editing state
            const editingInput = document.getElementById('editing-grid-batch-id');
            if (editingInput) editingInput.value = '';
            const gridEditingInput = document.getElementById('grid-editing-batch-id');
            if (gridEditingInput) gridEditingInput.value = '';
            const titleElement = document.getElementById('grid-section-title');
            if (titleElement) titleElement.textContent = 'Create New Properties Grid';
            const saveBtnText = document.getElementById('save-grid-btn-text');
            if (saveBtnText) saveBtnText.textContent = 'Save This Grid';
            
            // Hide editing banner
            const editingBanner = document.getElementById('grid-editing-banner');
            if (editingBanner) {
                editingBanner.classList.add('hidden');
            }
        }, 1000);
    @endif

    // Edit Grid Batch Name
    window.editGridBatch = function(batchId, currentName) {
        const newName = prompt('Enter new name for this grid:', currentName);
        if (newName === null || newName.trim() === '') {
            return; // User cancelled or entered empty string
        }

        const projectId = {{ $project->id }};
        const url = `/admin/projects/${projectId}/grid-batches/${batchId}`;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value;
        
        // Use FormData for proper form submission
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('_token', csrfToken);
        formData.append('grid_batch_name', newName.trim());
        
        // Use fetch API for better error handling
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to update grid batch name');
                });
            }
            return response.json();
        })
        .then(data => {
            // Update the displayed name on the page
            const batchNameElement = document.getElementById(`batch-name-${batchId}`);
            if (batchNameElement) {
                const nameDisplay = batchNameElement.querySelector('.batch-name-display');
                if (nameDisplay) {
                    nameDisplay.textContent = newName.trim();
                }
            }
            // Show success message
            alert('Grid batch name updated successfully!');
            // Reload page to ensure consistency
            window.location.reload();
        })
        .catch(error => {
            console.error('Error updating grid batch:', error);
            alert('Error: ' + error.message);
        });
    };

    // Finish Button Handler - Save project changes via AJAX
    const finishButton = document.getElementById('finishButton');
    const projectForm = document.getElementById('projectForm');
    
    if (finishButton && projectForm) {
        finishButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Disable button to prevent double submission
            finishButton.disabled = true;
            const originalText = finishButton.innerHTML;
            finishButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            
            // Create FormData from the form
            const formData = new FormData(projectForm);
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value;
            
            // Add method override
            formData.append('_method', 'PUT');
            
            // Submit via AJAX
            fetch(projectForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
                credentials: 'same-origin'
            })
            .then(async response => {
                const contentType = response.headers.get('content-type');
                const isJson = contentType && contentType.includes('application/json');
                
                if (isJson) {
                    const data = await response.json();
                    
                    if (data.success) {
                        // Success - redirect to project page
                        window.location.href = data.redirect || '{{ route("admin.projects.show", $project->id) }}';
                        return;
                    } else {
                        // Show validation errors
                        let errorMsg = data.message || 'Failed to save project changes.';
                        if (data.errors) {
                            const errorList = Object.values(data.errors).flat();
                            if (errorList.length > 0) {
                                errorMsg = errorList.join('\\n');
                            }
                        }
                        throw new Error(errorMsg);
                    }
                }
                
                // Handle HTML redirects
                if (response.redirected || response.status === 302) {
                    window.location.href = response.url;
                    return;
                }
                
                if (!response.ok) {
                    throw new Error('Failed to save project changes. Please try again.');
                }
                
                // If response is OK, redirect to project page
                window.location.href = '{{ route("admin.projects.show", $project->id) }}';
            })
            .catch(error => {
                console.error('Error saving project:', error);
                alert('Error: ' + error.message);
                finishButton.disabled = false;
                finishButton.innerHTML = originalText;
            });
        });
    }
});

function removeExistingImage(button) {
    if (confirm('Are you sure you want to remove this image?')) {
        // Remove the entire image container (including the hidden input)
        const container = button.closest('.existing-image-container') || button.closest('.relative');
        if (container) {
            container.remove();
        }
    }
}

// Load existing grid for editing
function loadGridForEditing(batchId, plots) {
    if (!confirm('This will load the grid for editing. Any unsaved changes in the grid below will be lost. Continue?')) {
        return;
    }
    
    // Validate inputs
    if (!batchId || !plots || !Array.isArray(plots)) {
        console.error('Invalid parameters for loadGridForEditing:', { batchId, plots });
        alert('Error: Invalid grid data. Please try again.');
        return;
    }
    
    // Get batch name
    const batchName = document.querySelector(`#grid-batch-${batchId} .batch-name-display`)?.textContent || 'Grid';
    
    // Show editing banner
    const editingBanner = document.getElementById('grid-editing-banner');
    const editingGridNameDisplay = document.getElementById('editing-grid-name-display');
    if (editingBanner) {
        editingBanner.classList.remove('hidden');
    }
    if (editingGridNameDisplay) {
        editingGridNameDisplay.textContent = batchName;
    }
    
    // Update section title
    const titleElement = document.getElementById('grid-section-title');
    if (titleElement) {
        titleElement.textContent = `Editing Grid: ${batchName}`;
    }
    
    // Set editing batch ID (for form submission)
    const gridEditingInput = document.getElementById('grid-editing-batch-id');
    if (gridEditingInput) {
        gridEditingInput.value = batchId;
        console.log('Set editing_grid_batch_id to:', batchId);
    } else {
        console.error('grid-editing-batch-id input not found!');
    }
    
    // Hide save button and show update & save button
    const saveBtn = document.getElementById('save-grid-btn');
    const updateSaveBtn = document.getElementById('update-save-grid-btn');
    if (saveBtn) {
        saveBtn.classList.add('hidden');
    }
    if (updateSaveBtn) {
        updateSaveBtn.classList.remove('hidden');
    }
    
    // Set grid batch name input
    const batchNameInput = document.getElementById('grid_batch_name');
    if (batchNameInput) {
        batchNameInput.value = batchName;
    }
    
    // Clear existing grid completely
    const container = document.getElementById('grid-container');
    const input = document.getElementById('grid-input');
    
    if (!container) {
        console.error('Grid container not found!');
        alert('Error: Grid container not found. Please refresh the page.');
        return;
    }
    
    if (!input) {
        console.error('Grid input not found!');
        alert('Error: Grid input not found. Please refresh the page.');
        return;
    }
    
    container.innerHTML = '';
    input.value = JSON.stringify([]);
    
    // Load plots into grid - only the plots passed for this specific batch
    const propertyTypes = @json($propertyTypeConfig);
    const propertyTypeColors = @json($propertyTypeColorMap);
    const propertyTypeLabels = @json($propertyTypeLabelMap);
    const propertyTypeMeasurements = @json($propertyTypeMeasurementMap);
    const projectBookingAmount = {{ $project->minimum_booking_amount ?? 0 }};
    
    // Validate plots array
    if (!plots || plots.length === 0) {
        console.warn('No plots found for batch:', batchId);
        console.warn('Plots data:', plots);
        // Don't return - just show a message but allow the UI to update
        container.innerHTML = '<div class="p-4 text-center text-gray-500">No plots found for this grid.</div>';
        return;
    }
    
    console.log('Loading plots for batch:', batchId, 'Total plots:', plots.length);
    console.log('Sample plot data:', plots[0]);
    console.log('Container element:', container);
    
    // Use plots directly (already filtered by backend)
    try {
        plots.forEach((plot, index) => {
        const slug = plot.type_slug || plot.type;
        const definition = propertyTypes.find(pt => pt.slug === slug) || {};
        const label = propertyTypeLabels[slug] || definition.name || slug;
        const measurement = propertyTypeMeasurements[slug] || '';
        const colorClass = propertyTypeColors[slug] || 'bg-gray-100 text-gray-800';
        
        const cell = document.createElement('div');
        cell.className = `border-2 border-blue-300 rounded-xl p-3 bg-white hover:border-blue-500 transition-colors`;
        cell.dataset.type = slug;
        cell.dataset.index = index;
        cell.dataset.label = label;
        cell.dataset.number = index + 1;
        cell.dataset.plotId = plot.id ? String(plot.id) : ''; // Ensure plot ID is set as string
        cell.dataset.gridBatchId = batchId;
        cell.dataset.gridBatchName = batchName;
        
        console.log('Setting plot ID:', plot.id, 'for plot:', plot.plot_number);
        
        cell.innerHTML = `
            <div class="space-y-2">
                <div class="mb-2 px-2 py-1 bg-blue-100 border border-blue-300 rounded text-xs text-blue-900 font-semibold text-center">
                    <i class="fas fa-layer-group mr-1"></i>${batchName}
                </div>
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2 py-1 rounded text-xs font-semibold ${colorClass}">${label}</span>
                    <button type="button" onclick="removeProperty(this)" class="text-red-500 hover:text-red-700 text-xs">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Property Name</label>
                    <input type="text" 
                           class="property-name-input w-full px-2 py-1 text-sm border rounded" 
                           value="${plot.plot_number || ''}"
                           placeholder="e.g., Plot A-101"
                           data-property-index="${index}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Size (${measurement})</label>
                    <input type="number" 
                           step="0.01"
                           class="property-size-input w-full px-2 py-1 text-sm border rounded" 
                           value="${plot.size || 0}"
                           placeholder="Size"
                           data-property-index="${index}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Price per Unit (₹)</label>
                    <input type="number" 
                           step="0.01"
                           class="property-price-input w-full px-2 py-1 text-sm border rounded" 
                           value="${plot.price_per_unit || 0}"
                           placeholder="Price"
                           data-property-index="${index}">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Min. Booking (₹)</label>
                    <input type="number" 
                           step="0.01"
                           class="property-booking-input w-full px-2 py-1 text-sm border rounded bg-gray-100" 
                           value="${projectBookingAmount || 0}"
                           placeholder="Booking"
                           data-property-index="${index}"
                           readonly
                           title="Booking amount is set at project level">
                </div>
                <div class="pt-2">
                    <button type="button" onclick="changePropertyType(this)" class="w-full px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">
                        Change Type
                    </button>
                </div>
            </div>
        `;
        
        // Add event listeners
        const nameInput = cell.querySelector('.property-name-input');
        const sizeInputEl = cell.querySelector('.property-size-input');
        const priceInputEl = cell.querySelector('.property-price-input');
        
        [nameInput, sizeInputEl, priceInputEl].forEach(input => {
            if (input) {
                input.addEventListener('input', function() {
                    if (typeof sync === 'function') {
                        sync();
                    }
                });
                input.addEventListener('blur', function() {
                    if (typeof sync === 'function') {
                        sync();
                    }
                });
            }
        });
        
        container.appendChild(cell);
    });
    
        console.log('Finished loading plots. Container now has', container.children.length, 'children');
    } catch (error) {
        console.error('Error loading plots:', error);
        alert('Error loading plots: ' + error.message);
        container.innerHTML = '<div class="p-4 text-center text-red-500">Error loading plots. Please check the console for details.</div>';
        return;
    }
    
    // Scroll to grid section
    const gridSection = document.querySelector('.bg-white.rounded-2xl.shadow-3d');
    if (gridSection) {
        gridSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Sync to update hidden input
    // Get fresh references to container and input for sync
    const syncContainer = document.getElementById('grid-container');
    const syncInput = document.getElementById('grid-input');
    const syncProjectBookingAmount = {{ $project->minimum_booking_amount ?? 0 }};
    
    if (syncContainer && syncInput) {
        // Call sync function if it exists, or manually update the input
        if (typeof sync === 'function') {
            sync();
        } else {
            // Manual sync if function not available
            const payload = Array.from(syncContainer.children).map((el, index) => {
                const typeSlug = el.dataset.type;
                const plotId = el.dataset.plotId || null; // Include plot ID if editing existing plot
                const nameInput = el.querySelector('.property-name-input');
                const sizeInput = el.querySelector('.property-size-input');
                const priceInput = el.querySelector('.property-price-input');
                
                const plotNumber = nameInput ? (nameInput.value.trim() || `${el.dataset.label} #${el.dataset.number}`) : `${el.dataset.label} #${el.dataset.number}`;
                const size = sizeInput ? parseFloat(sizeInput.value) || 0 : 0;
                const pricePerUnit = priceInput ? parseFloat(priceInput.value) || 0 : 0;
                
                const plotData = {
                    plot_number: plotNumber,
                    type: typeSlug,
                    size: size,
                    price_per_unit: pricePerUnit,
                    minimum_booking_amount: syncProjectBookingAmount,
                };
                
                // Include plot ID if editing existing plot
                if (plotId) {
                    plotData.id = plotId;
                }
                
                return plotData;
            });
            syncInput.value = JSON.stringify(payload);
        }
    }
    
    console.log('loadGridForEditing completed. Plots loaded:', container.children.length);
}

// Cancel grid editing
function cancelGridEditing() {
    if (!confirm('Cancel editing this grid? All unsaved changes will be lost.')) {
        return;
    }
    
    // Hide editing banner
    const editingBanner = document.getElementById('grid-editing-banner');
    if (editingBanner) {
        editingBanner.classList.add('hidden');
    }
    
    // Reset section title
    const titleElement = document.getElementById('grid-section-title');
    if (titleElement) {
        titleElement.textContent = 'Create New Properties Grid';
    }
    
    // Clear editing state
    const gridEditingInput = document.getElementById('grid-editing-batch-id');
    if (gridEditingInput) {
        gridEditingInput.value = '';
        console.log('Cleared editing_grid_batch_id');
    }
    
    // Show save button and hide update & save button
    const saveBtn = document.getElementById('save-grid-btn');
    const updateSaveBtn = document.getElementById('update-save-grid-btn');
    if (saveBtn) {
        saveBtn.classList.remove('hidden');
    }
    if (updateSaveBtn) {
        updateSaveBtn.classList.add('hidden');
    }
    
    // Clear grid batch name
    const batchNameInput = document.getElementById('grid_batch_name');
    if (batchNameInput) batchNameInput.value = '';
    
    // Clear grid container
    const container = document.getElementById('grid-container');
    const input = document.getElementById('grid-input');
    if (container) container.innerHTML = '';
    if (input) input.value = JSON.stringify([]);
}


// New Update & Save button handler
document.addEventListener('DOMContentLoaded', function() {
    const updateSaveBtn = document.getElementById('update-save-grid-btn');
    if (updateSaveBtn) {
        updateSaveBtn.addEventListener('click', function() {
            updateAndSaveGrid();
        });
    }
});

function updateAndSaveGrid() {
    const container = document.getElementById('grid-container');
    const gridInput = document.getElementById('grid-input');
    const editingBatchId = document.getElementById('grid-editing-batch-id')?.value;
    const batchNameInput = document.getElementById('grid_batch_name');
    const batchName = batchNameInput ? batchNameInput.value.trim() : '';
    
    if (!editingBatchId) {
        alert('Error: No grid selected for editing. Please click "Edit Grid" first.');
        return;
    }
    
    // Get grid data
    const gridData = gridInput ? JSON.parse(gridInput.value || '[]') : [];
    
    if (!gridData || gridData.length === 0) {
        alert('No plots found in the grid. Please add plots before saving.');
        return;
    }
    
    // Validate that all plots have IDs when editing
    const plotsWithoutIds = gridData.filter(plot => !plot.id);
    if (plotsWithoutIds.length > 0) {
        console.warn('Some plots are missing IDs:', plotsWithoutIds);
        // This is okay for new plots added during editing
    }
    
    console.log('Updating grid:', {
        editingBatchId: editingBatchId,
        batchName: batchName,
        plotCount: gridData.length,
        plotsWithIds: gridData.filter(p => p.id).length,
        gridData: gridData,
    });
    
    // Disable button during submission
    const updateSaveBtn = document.getElementById('update-save-grid-btn');
    const originalText = updateSaveBtn.innerHTML;
    updateSaveBtn.disabled = true;
    updateSaveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                           document.querySelector('input[name="_token"]')?.value);
    formData.append('grid', JSON.stringify(gridData));
    formData.append('grid_batch_name', batchName);
    formData.append('editing_grid_batch_id', editingBatchId);
    
    // Submit via AJAX
    fetch('{{ route("admin.projects.plots.store", ["project" => $project->id]) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                           document.querySelector('input[name="_token"]')?.value,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to update grid');
            });
        }
        return response.json().catch(() => {
            // If response is not JSON, assume success
            return { success: true };
        });
    })
    .then(data => {
        console.log('Grid updated successfully:', data);
        alert('Grid updated successfully!');
        // Reload page to show updated data
        window.location.reload();
    })
    .catch(error => {
        console.error('Error updating grid:', error);
        alert('Error updating grid: ' + error.message);
        updateSaveBtn.disabled = false;
        updateSaveBtn.innerHTML = originalText;
    });
}
</script>
@endpush
@endsection
