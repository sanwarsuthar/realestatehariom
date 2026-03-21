@extends('admin.layouts.app')

@section('title', $project->name)
@section('page-title', $project->name)

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

@section('content')
<div class="admin-page-content space-y-6 pb-28 min-w-0">
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">{{ $project->name }}</h3>
                <p class="text-sm text-gray-500">{{ $project->location }} • {{ ucfirst(str_replace('_',' ', $project->status)) }}</p>
            </div>
            <div class="space-x-2">
                <a href="{{ route('admin.projects.export', ['project' => $project->id]) }}" class="px-3 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700" title="Export Project"><i class="fas fa-download mr-1"></i>Export</a>
                <a href="{{ route('admin.projects.edit', ['project' => $project->id]) }}" class="px-3 py-2 rounded-lg bg-gray-100">Edit</a>
                <form action="{{ route('admin.projects.destroy', ['project' => $project->id]) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this project and all its units? This cannot be undone.')" class="px-3 py-2 rounded-lg bg-red-600 text-white">Delete</button>
                </form>
            </div>
        </div>
        <div class="mt-4 text-sm text-gray-700">{{ $project->description }}</div>
        <div class="mt-4 text-xs text-gray-500">Facilities: {{ implode(', ', (array) $project->facilities) }}</div>
    </div>

    <!-- Existing Grids Section -->
    @if(isset($gridBatches) && $gridBatches->isNotEmpty())
    <div class="bg-white rounded-2xl shadow-3d p-6">
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
                            onclick="editGridBatch('{{ $batch['batch_id'] }}', '{{ addslashes($batch['batch_name']) }}')"
                            class="px-3 py-1 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit Name
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

    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">Create New Properties Grid</h4>

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
                <h5 class="text-sm font-semibold text-gray-800 mb-3">Property Type Configuration</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($propertyTypes as $type)
                        @php
                            $slug = Str::slug($type->name);
                            $measurementLabel = optional($type->measurementUnit)->name;
                        @endphp
                        <div class="p-4 bg-white border rounded-lg space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $type->name }}
                                    @if($measurementLabel)
                                        <span class="text-xs text-gray-500">({{ $measurementLabel }})</span>
                                    @endif
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
                                    Property Size
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
                                <p class="text-xs text-gray-500 mt-1">Size in {{ $measurementLabel ?? 'units' }}</p>
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
                                <p class="text-xs text-gray-500 mt-1">Price per {{ $measurementLabel ?? 'unit' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-gray-500">Click "Generate Grid" to preview the layout. You can adjust counts and regenerate at any time.</p>
                <button type="button" id="generate-grid" class="px-4 py-2 rounded-lg bg-primary-600 text-white">Generate Grid</button>
            </div>

            <div id="grid-container" class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>

            <input type="hidden" name="grid" id="grid-input">
            <div class="flex items-center justify-between pt-4 border-t">
                <p class="text-xs text-gray-500">Click a box to cycle through the property types you have defined.</p>
                <button type="submit" id="save-grid-btn" class="px-6 py-2 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-colors">
                    Save This Grid
                </button>
            </div>
        </form>
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
            <strong>💡 Tip:</strong> After saving a grid, you can create another grid with different property sizes and prices. Each grid will be displayed above, and commissions will be calculated based on the specific property's size and price from its grid.
        </div>
        @endif
    </div>


    <div class="flex items-center justify-end">
        <a href="{{ route('admin.projects') }}" class="px-4 py-3 rounded-lg bg-black text-white">Finish</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const propertyTypes = @json($propertyTypeConfig);
    const propertyTypeColors = @json($propertyTypeColorMap);
    const propertyTypeLabels = @json($propertyTypeLabelMap);
    const propertyTypeMeasurements = @json($propertyTypeMeasurementMap);

    const container = document.getElementById('grid-container');
    const input = document.getElementById('grid-input');
    const generateBtn = document.getElementById('generate-grid');
    const typeInputs = document.querySelectorAll('.property-type-input');
    const priceInputs = document.querySelectorAll('.property-price-input');
    const sizeInputs = document.querySelectorAll('.property-size-input');
    
    // Get project's booking amount from backend
    const projectBookingAmount = {{ $project->minimum_booking_amount ?? 0 }};

    if (!container || !input) {
        console.warn('Grid container or hidden input not found.');
        return;
    }
    
    // Add event listeners to price and size inputs to sync when changed
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
            
            // Get default price and size from inputs
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
            
            // Create editable property card
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

            // Add event listeners for inputs (booking is readonly, so no listener needed)
            const nameInput = cell.querySelector('.property-name-input');
            const sizeInputEl = cell.querySelector('.property-size-input');
            const priceInputEl = cell.querySelector('.property-price-input');
            
            [nameInput, sizeInputEl, priceInputEl].forEach(input => {
                if (input) {
                    input.addEventListener('input', sync);
                    input.addEventListener('blur', sync);
                }
            });

            container.appendChild(cell);
        });
        
        // Add change property type handler
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
            
            // Update the label badge
            const badge = cell.querySelector('.px-2');
            if (badge) {
                badge.className = `px-2 py-1 rounded text-xs font-semibold ${nextColor}`;
                badge.textContent = nextLabel;
            }
            
            // Update measurement unit in size label
            const sizeLabel = cell.querySelector('.property-size-input')?.previousElementSibling;
            if (sizeLabel && sizeLabel.tagName === 'LABEL') {
                sizeLabel.textContent = `Size (${nextMeasurement})`;
            }
            
            sync();
        };
        
        // Add remove property handler
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
            
            // Get values from individual property inputs
            const nameInput = el.querySelector('.property-name-input');
            const sizeInput = el.querySelector('.property-size-input');
            const priceInput = el.querySelector('.property-price-input');
            
            const plotNumber = nameInput ? (nameInput.value.trim() || `${el.dataset.label} #${el.dataset.number}`) : `${el.dataset.label} #${el.dataset.number}`;
            const size = sizeInput ? parseFloat(sizeInput.value) || 0 : 0;
            const pricePerUnit = priceInput ? parseFloat(priceInput.value) || 0 : 0;
            // Use project's booking amount
            const minBooking = projectBookingAmount || 0;
            
            return {
                plot_number: plotNumber,
                type: typeSlug,
                size: size,
                price_per_unit: pricePerUnit,
                minimum_booking_amount: minBooking,
            };
        });
        input.value = JSON.stringify(payload);
    }

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
});
</script>
@endpush
