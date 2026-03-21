@extends('admin.layouts.app')

@section('title', 'Settings')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Flash Messages -->
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Header Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">System Settings</h1>
                <p class="text-primary-100">Configure system settings and preferences for your Real Estate MLM platform.</p>
            </div>
            <div class="text-right">
                <i class="fas fa-cog text-6xl text-primary-200"></i>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6" onsubmit="return false;">
        @csrf
        @method('PUT')
        
        <!-- General Settings -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">General Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Application Name</label>
                    <input type="text" name="app_name" value="{{ $settings['app_name'] }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">OTP Expiry (Minutes)</label>
                    <input type="number" name="otp_expiry_minutes" value="{{ $settings['otp_expiry_minutes'] }}" min="1" max="60" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">KYC Required</label>
                    <select name="kyc_required" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="1" {{ $settings['kyc_required'] ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ !$settings['kyc_required'] ? 'selected' : '' }}>No</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end border-t pt-4 mt-6">
                <button type="button" onclick="saveSection('general', this)" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Save General Settings
                </button>
            </div>
        </div>

        <!-- Financial Settings -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">Financial Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Withdrawal Amount (₹)</label>
                    <input type="number" name="min_withdrawal_amount" value="{{ $settings['min_withdrawal_amount'] }}" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Maximum Withdrawal Amount (₹)</label>
                    <input type="number" name="max_withdrawal_amount" value="{{ $settings['max_withdrawal_amount'] }}" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>
            <div class="flex justify-end border-t pt-4 mt-6">
                <button type="button" onclick="saveSection('financial', this)" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Save Financial Settings
                </button>
            </div>
        </div>

        <!-- System Settings -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">System Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Maintenance Mode</label>
                    <select name="maintenance_mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="0" {{ !$settings['maintenance_mode'] ? 'selected' : '' }}>Disabled</option>
                        <option value="1" {{ $settings['maintenance_mode'] ? 'selected' : '' }}>Enabled</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">When enabled, users will see a maintenance page</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Maintenance Message</label>
                    <textarea name="maintenance_message" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter maintenance message...">{{ $settings['maintenance_message'] ?? 'We are currently performing scheduled maintenance to improve your experience. Please check back soon.' }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">This message will be shown to users during maintenance</p>
                </div>
            </div>
            
            <div class="mt-6 border-t pt-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4">User Session Management</h4>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                        <div>
                            <p class="text-sm font-medium text-yellow-800">Force Logout All Users</p>
                            <p class="text-xs text-yellow-700 mt-1">This action will invalidate all active user sessions. All users will be logged out and required to login again.</p>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="forceLogoutAll()" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-semibold shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-out-alt mr-2"></i>Force Logout All Users
                </button>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">System Status</label>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 {{ $settings['maintenance_mode'] ? 'bg-yellow-500' : 'bg-green-500' }} rounded-full"></div>
                    <span class="text-sm text-gray-600">{{ $settings['maintenance_mode'] ? 'Maintenance Mode Active' : 'Online' }}</span>
                </div>
            </div>
            <div class="flex justify-end border-t pt-4 mt-6">
                <button type="button" onclick="saveSection('system', this)" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Save System Settings
                </button>
            </div>
        </div>

        <!-- MLM Configuration -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">MLM Commission Structure</h3>
            
            <div class="space-y-6">
                <!-- Property-Type-Based Commission Structure -->
                <div>
                    <h4 class="font-semibold text-gray-800 mb-4 text-lg">Level 1 - Direct Seller Commission (Percentage-Based)</h4>
                    <p class="text-sm text-gray-600 mb-4">Set commission percentages for each property type and slab combination. Commission = (Allocated Amount × Slab %) × Area Sold. If not set, default slab commission percentage will be used.</p>
                    
                    @if($propertyTypes->count() > 0 && $slabs->count() > 0)
                    <div class="overflow-x-auto mb-6 border border-gray-200 rounded-lg shadow-sm" style="max-height: 600px; overflow-y: auto;">
                        <div style="min-width: {{ max(800, ($slabs->count() * 200) + 300) }}px;">
                            <table class="w-full bg-white border-collapse">
                                <thead class="bg-gray-100 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-base font-semibold text-gray-700 border-b border-gray-300 min-w-[180px]">Property Type</th>
                                        <th class="px-6 py-4 text-left text-base font-semibold text-gray-700 border-b border-gray-300 min-w-[120px]">Unit</th>
                                        @foreach($slabs as $slab)
                                        <th class="px-6 py-4 text-center text-base font-semibold text-gray-700 border-b border-gray-300 min-w-[200px]">
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold">{{ $slab->name }}</span>
                                                <span class="text-xs font-normal text-gray-600 mt-1">(%)</span>
                                                @if($slab->propertyTypes->count() > 0)
                                                    <div class="mt-1 flex flex-wrap gap-1 justify-center">
                                                        @foreach($slab->propertyTypes as $pt)
                                                            <span class="px-1.5 py-0.5 text-xs bg-primary-100 text-primary-700 rounded">{{ $pt->name }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($propertyTypes as $propertyType)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-base font-medium text-gray-800 border-b border-gray-200">
                                            {{ $propertyType->name }}
                                        </td>
                                        <td class="px-6 py-4 text-base text-gray-600 border-b border-gray-200">
                                            <span class="font-semibold">{{ $propertyType->measurementUnit->symbol ?? $propertyType->measurementUnit->name ?? 'N/A' }}</span>
                                        </td>
                                        @foreach($slabs as $slab)
                                        <td class="px-6 py-4 border-b border-gray-200">
                                            @php
                                                // Check if this slab applies to this property type
                                                $slabAppliesToPropertyType = $slab->propertyTypes->contains('id', $propertyType->id);
                                                $currentValue = $propertyTypeCommissions[$propertyType->name][$slab->name] ?? '';
                                                $defaultValue = $settings['slab_commission_' . strtolower($slab->name)] ?? '';
                                            @endphp
                                            @if($slabAppliesToPropertyType)
                                                <div class="space-y-2">
                                                    <div class="relative">
                                                        <input 
                                                            type="number" 
                                                            step="0.01" 
                                                            min="0" 
                                                            max="100"
                                                            name="property_type_commissions[{{ $propertyType->name }}][{{ $slab->name }}]" 
                                                            value="{{ $currentValue }}"
                                                            placeholder="{{ $defaultValue }}"
                                                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg text-base font-medium text-center focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all hover:border-gray-400"
                                                        >
                                                        <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-600 text-lg font-semibold">%</span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 text-center leading-tight">
                                                        @if($currentValue !== '')
                                                            <span class="text-gray-400">Default: {{ $defaultValue }}%</span>
                                                        @else
                                                            <span>Default: {{ $defaultValue }}%</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-center text-gray-400 text-sm py-3">
                                                    <span class="italic">N/A</span>
                                                    <div class="text-xs mt-1">Slab not applicable</div>
                                                </div>
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 bg-gray-50 border-t border-gray-200">
                            <p class="text-sm text-gray-600 mb-3">
                                <span class="font-semibold">💡 Tip:</span> Leave empty to use default slab commission percentage. Enter a percentage (e.g., 35) to set commission percentage for that property type and slab combination.
                            </p>
                            <div class="mt-3 space-y-2">
                                <p class="text-sm font-semibold text-gray-700 mb-2">📊 Examples:</p>
                                @php
                                    $exampleAreaSold = 100; // Example: 100 units
                                    $exampleAllocatedAmount = 1500; // Example: ₹1500 allocated amount
                                    $firstSlab = $slabs->first(); // Use first slab for examples
                                @endphp
                                @foreach($propertyTypes as $propertyType)
                                    @php
                                        $currentValue = $propertyTypeCommissions[$propertyType->name][$firstSlab->name] ?? '';
                                        $defaultValue = $settings['slab_commission_' . strtolower($firstSlab->name)] ?? '35';
                                        $commissionPercentage = $currentValue !== '' ? $currentValue : $defaultValue;
                                        $unitSymbol = $propertyType->measurementUnit->symbol ?? $propertyType->measurementUnit->name ?? 'unit';
                                        $commissionPerUnit = ($exampleAllocatedAmount * (float)$commissionPercentage / 100);
                                        $totalCommission = $commissionPerUnit * $exampleAreaSold;
                                    @endphp
                                    <p class="text-sm text-blue-600 font-semibold">
                                        📊 Example: If {{ $firstSlab->name }} + {{ $propertyType->name }} = {{ number_format($commissionPercentage, 1) }}%, Allocated Amount = ₹{{ number_format($exampleAllocatedAmount, 0) }}, and someone sells {{ $exampleAreaSold }} {{ $unitSymbol }}, commission = (₹{{ number_format($exampleAllocatedAmount, 0) }} × {{ number_format($commissionPercentage, 1) }}%) × {{ $exampleAreaSold }} = ₹{{ number_format($totalCommission, 0) }}
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-sm text-yellow-800">⚠️ No property types or slabs found. Please create property types and slabs first.</p>
                    </div>
                    @endif
                </div>
                
                <!-- Default Slab Commission Structure (Fallback) -->
                <div>
                    <h4 class="font-semibold text-gray-800 mb-4 text-lg">Default Commission Percentage (Fallback - Used when property-type commission not set)</h4>
                    <p class="text-sm text-gray-600 mb-4">These are the default commission percentages used when property-type-specific percentages are not configured above.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="p-4 border-2 border-amber-600 rounded-lg bg-amber-50">
                            <label class="block text-sm font-medium text-amber-800 mb-2">Slab1 (%)</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" name="slab_commission_bronze" value="{{ $settings['slab_commission_bronze'] ?? '35' }}" class="w-full px-4 pr-8 py-2 border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-lg font-semibold text-amber-700 text-center" required>
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-amber-700 text-lg font-semibold">%</span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">commission percentage</div>
                        </div>
                        <div class="p-4 border-2 border-gray-400 rounded-lg bg-gray-50">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Slab2 (%)</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" name="slab_commission_silver" value="{{ $settings['slab_commission_silver'] ?? '40' }}" class="w-full px-4 pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-transparent text-lg font-semibold text-gray-600 text-center" required>
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-600 text-lg font-semibold">%</span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">commission percentage</div>
                        </div>
                        <div class="p-4 border-2 border-yellow-500 rounded-lg bg-yellow-50">
                            <label class="block text-sm font-medium text-yellow-800 mb-2">Slab3 (%)</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" name="slab_commission_gold" value="{{ $settings['slab_commission_gold'] ?? '45' }}" class="w-full px-4 pr-8 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent text-lg font-semibold text-yellow-700 text-center" required>
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-yellow-700 text-lg font-semibold">%</span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">commission percentage</div>
                        </div>
                        <div class="p-4 border-2 border-cyan-400 rounded-lg bg-cyan-50">
                            <label class="block text-sm font-medium text-cyan-800 mb-2">Slab4 (%)</label>
                            <div class="relative">
                                <input type="number" step="0.01" min="0" max="100" name="slab_commission_diamond" value="{{ $settings['slab_commission_diamond'] ?? '50' }}" class="w-full px-4 pr-8 py-2 border border-cyan-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-lg font-semibold text-cyan-700 text-center" required>
                                <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-cyan-700 text-lg font-semibold">%</span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">commission percentage</div>
                        </div>
                    </div>
                </div>

                <!-- Example Calculation (Dynamic) -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-800 mb-2">Example Calculation</h4>
                    <div class="text-sm text-blue-800 space-y-1">
                        <p><strong>Scenario:</strong> A Slab3 user sells 100 units, Allocated Amount = ₹1,500</p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            @php
                                $exampleAreaSold = 100; // Example: 100 units
                                $exampleAllocatedAmount = 1500; // Example: ₹1500
                                $examplePercentage = (float)($settings['slab_commission_gold'] ?? 45);
                                $commissionPerUnit = ($exampleAllocatedAmount * $examplePercentage / 100);
                                $exampleLevel1Commission = $commissionPerUnit * $exampleAreaSold;
                            @endphp
                            <li><strong>Level 1 (Seller):</strong> (₹{{ number_format($exampleAllocatedAmount, 0) }} × {{ number_format($examplePercentage, 1) }}%) × {{ $exampleAreaSold }} units = ₹{{ number_format($exampleLevel1Commission, 2) }}</li>
                        </ul>
                        <p class="mt-2 text-xs italic">Note: Commission = (Allocated Amount × Slab %) × Area Sold. Changes will apply to all future bookings.</p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end border-t pt-4 mt-6">
                <button type="button" onclick="saveSection('mlm', this)" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Save MLM Commission Structure
                </button>
            </div>
        </div>

        <!-- Home Page Settings -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">Home Page Slider</h3>
            
            <form id="sliderImagesForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Add Slider Images</label>
                    <div class="flex items-center space-x-4">
                        <input type="file" id="sliderImageInput" name="images[]" accept="image/*" multiple class="hidden">
                        <button type="button" onclick="document.getElementById('sliderImageInput').click()" 
                                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Select Images
                        </button>
                        <span class="text-sm text-gray-500">Select one or more images for the home page slider</span>
                    </div>
                    <div id="selectedFilesInfo" class="mt-2 text-sm text-gray-600 hidden"></div>
                </div>

                <!-- Slider Images List -->
                <div id="sliderImagesList" class="space-y-4 mb-6">
                    @php
                        $sliderImages = json_decode(\App\Models\Setting::get('home_slider_images', '[]'), true) ?? [];
                    @endphp
                    @if(!empty($sliderImages))
                        @foreach($sliderImages as $index => $imagePath)
                            @php
                                // Convert relative path to full URL for display
                                $displayUrl = strpos($imagePath, '/') === 0 ? url($imagePath) : $imagePath;
                            @endphp
                            <div class="flex items-center space-x-4 p-4 border border-gray-200 rounded-lg slider-image-item" data-index="{{ $index }}" data-url="{{ $imagePath }}">
                                <img src="{{ $displayUrl }}" alt="Slider {{ $index + 1 }}" class="w-24 h-16 object-cover rounded" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'96\' height=\'64\'%3E%3Crect fill=\'%23ddd\' width=\'96\' height=\'64\'/%3E%3Ctext fill=\'%23999\' font-family=\'sans-serif\' font-size=\'14\' dy=\'10.5\' font-weight=\'bold\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\'%3EImage%3C/text%3E%3C/svg%3E'">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-700">Slider Image {{ $index + 1 }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $imagePath }}</p>
                                </div>
                                <button type="button" onclick="removeSliderImage({{ $index }})" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                    <i class="fas fa-trash mr-2"></i>Remove
                                </button>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-500 text-center py-4">No slider images added yet. Click "Select Images" to upload.</p>
                    @endif
                </div>

                <!-- Save Button for Slider Images -->
                <div class="flex justify-end border-t pt-4">
                    <button type="button" id="saveSliderImagesBtn" 
                            class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                        <i class="fas fa-save mr-2"></i>Save Slider Images
                    </button>
                </div>
            </form>
        </div>

        <!-- App Update Settings -->
        <div class="bg-white rounded-2xl p-6 shadow-3d">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">App Update Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Android Build Number</label>
                    <input type="number" name="android_build_number" value="{{ $settings['android_build_number'] ?? '1' }}" placeholder="e.g., 1" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Minimum required build number for Android (e.g., 1, 2, 3...)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Android Store URL</label>
                    <input type="url" name="android_store_url" value="{{ $settings['android_store_url'] ?? '' }}" placeholder="https://play.google.com/store/apps/details?id=..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">iOS Build Number</label>
                    <input type="number" name="ios_build_number" value="{{ $settings['ios_build_number'] ?? '1' }}" placeholder="e.g., 1" min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Minimum required build number for iOS (e.g., 1, 2, 3...)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">iOS Store URL</label>
                    <input type="url" name="ios_store_url" value="{{ $settings['ios_store_url'] ?? '' }}" placeholder="https://apps.apple.com/app/id..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                
                <div class="md:col-span-2">
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" name="force_app_update" value="1" {{ ($settings['force_app_update'] ?? '0') === '1' ? 'checked' : '' }} class="w-5 h-5 text-primary-500 border-gray-300 rounded focus:ring-primary-500">
                        <span class="text-sm font-medium text-gray-700">Force App Update</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-8">When enabled, users with outdated app build numbers will see a non-dismissable update popup</p>
                </div>
            </div>
            <div class="flex justify-end border-t pt-4 mt-6">
                <button type="button" onclick="saveSection('app-update', this)" class="px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-700 text-white rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Save App Update Settings
                </button>
            </div>
        </div>
    </form>

    <!-- Danger Zone - Reset Data -->
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">What Will Be Deleted:</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Users:</strong> All broker users (admin users preserved)</li>
                        <li><strong>Projects:</strong> <span class="text-red-600 font-semibold">DELETED</span></li>
                        <li><strong>Plots:</strong> <span class="text-red-600 font-semibold">DELETED</span></li>
                        <li><strong>Slabs:</strong> <span class="text-red-600 font-semibold">DELETED</span></li>
                        <li><strong>Property Types:</strong> <span class="text-red-600 font-semibold">DELETED</span></li>
                        <li><strong>Measurement Units:</strong> <span class="text-red-600 font-semibold">DELETED</span></li>
                        <li><strong>All Settings:</strong> <span class="text-red-600 font-semibold">DELETED</span> (except SMTP)</li>
                        <li><strong>Transactions:</strong> All deposits, withdrawals, commissions, bonuses (including Pending Deposits & Pending Withdrawals)</li>
                        <li><strong>Sales:</strong> All booking/sale records</li>
                        <li><strong>Payment Requests:</strong> All deposit and withdrawal requests</li>
                        <li><strong>Payment Methods:</strong> All payment method configurations</li>
                        <li><strong>Wallets:</strong> All user wallets deleted; All wallet balances reset to 0 for all users including admin</li>
                        <li><strong>KYC Documents:</strong> All verification documents (including PNG/JPG files from storage)</li>
                        <li><strong>Slab Upgrade History:</strong> All slab upgrade records</li>
                        <li><strong>Contact Inquiries:</strong> All contact form submissions</li>
                        <li><strong>Sessions & Tokens:</strong> All login sessions and API tokens</li>
                    </ul>
                </div>
                <h3 class="text-sm font-medium text-green-800 mt-3">What Will Be Preserved:</h3>
                <div class="mt-2 text-sm text-green-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Admin Users:</strong> All admin accounts (with wallets reset to 0)</li>
                        <li><strong>SMTP Settings:</strong> Mail configuration (host, port, username, password, etc.)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-3d border-2 border-red-200">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-xl font-semibold text-red-600 mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Danger Zone
                </h3>
                <p class="text-sm text-gray-600">This action will permanently delete ALL data except admin users and SMTP settings.</p>
            </div>
        </div>
        
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="text-sm text-red-800 font-medium mb-2">⚠️ This will delete:</p>
                    <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
                        <li><strong>EVERYTHING</strong> will be deleted including:</li>
                        <li>All broker users (admin users preserved)</li>
                        <li>All projects and plots</li>
                        <li>All slabs, property types, and measurement units</li>
                        <li>All settings (except SMTP configuration)</li>
                        <li>All bookings/sales</li>
                        <li>All transactions (deposits, withdrawals, commissions, bonuses, including Pending Deposits & Pending Withdrawals)</li>
                        <li>All payment requests and payment methods</li>
                        <li>All user wallets deleted; All wallet balances reset to 0 (including admin wallets)</li>
                        <li>All KYC documents (including PNG/JPG files from storage)</li>
                        <li>All slab upgrade history</li>
                        <li>All contact inquiries</li>
                        <li>All OTP verifications</li>
                        <li>All sessions and API tokens</li>
                    </ul>
                    <p class="text-sm text-green-800 font-medium mt-3 mb-2">✅ Only these will be preserved:</p>
                    <ul class="text-sm text-green-700 list-disc list-inside space-y-1">
                        <li><strong>Admin Users:</strong> All admin accounts (with wallets reset to 0)</li>
                        <li><strong>SMTP Settings:</strong> Mail configuration (host, port, username, password, encryption, from address, etc.)</li>
                    </ul>
        </div>

        <form method="POST" action="{{ route('admin.settings.reset-data') }}" id="resetDataForm" onsubmit="return confirmReset()">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Enter Password to Confirm</label>
                <input type="password" name="password" id="resetPassword" class="w-full px-4 py-2 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Enter password" required>
                <p class="text-xs text-gray-500 mt-1">Password required to reset all data</p>
            </div>
            <button type="submit" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-8 py-3 rounded-lg font-semibold hover:from-red-600 hover:to-red-800 transition-all duration-300 shadow-lg hover:shadow-xl">
                <i class="fas fa-trash-alt mr-2"></i>
                Reset All Data
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Function to save individual sections
function saveSection(section, buttonElement) {
    // Find the main settings form - it might have onsubmit="return false;"
    const form = document.querySelector('form[action*="settings"]') || document.querySelector('form[method="POST"]');
    if (!form) {
        alert('Form not found');
        return;
    }
    
    const sectionFields = {
        'general': ['app_name', 'otp_expiry_minutes', 'kyc_required'],
        'financial': ['min_withdrawal_amount', 'max_withdrawal_amount'],
        'system': ['maintenance_mode', 'maintenance_message'],
        'mlm': ['slab_commission_bronze', 'slab_commission_silver', 'slab_commission_gold', 'slab_commission_diamond'],
        'app-update': ['android_build_number', 'android_store_url', 'ios_build_number', 'ios_store_url', 'force_app_update']
    };
    
    const fields = sectionFields[section];
    if (!fields) {
        alert('Invalid section');
        return;
    }
    
    // Create new FormData with only relevant fields
    const submitData = new FormData();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value;
    if (!csrfToken) {
        alert('CSRF token not found');
        return;
    }
    submitData.append('_token', csrfToken);
    submitData.append('_method', 'PUT');
    
    // Get values from form elements - search in entire document, not just form
    let allFieldsFound = true;
    
    // Special handling for property_type_commissions (nested array)
    if (section === 'mlm') {
        // Collect all property_type_commissions fields (only enabled inputs, not N/A disabled ones)
        const propertyTypeInputs = document.querySelectorAll('input[name^="property_type_commissions["]:not([disabled])');
        propertyTypeInputs.forEach(input => {
            const name = input.name;
            const value = input.value.trim();
            // Append all values (including empty strings to clear existing values)
            submitData.append(name, value);
            console.log(`Property type commission ${name}:`, value);
        });
    }
    
    fields.forEach(field => {
        // Search in the entire document, not just the form
        const element = document.querySelector(`[name="${field}"]`);
        if (element) {
            let valueToSend = '';
            if (element.type === 'checkbox') {
                // For checkboxes, send '1' if checked, '0' if not
                valueToSend = element.checked ? '1' : '0';
                console.log(`Checkbox ${field}:`, valueToSend, 'checked:', element.checked);
            } else if (element.tagName === 'SELECT') {
                // For selects, get the selected value
                valueToSend = element.value || '';
                console.log(`Select ${field}:`, valueToSend, 'selectedIndex:', element.selectedIndex);
            } else {
                // For text, number, etc. - get the actual value
                valueToSend = element.value || '';
                console.log(`Input ${field}:`, valueToSend, 'type:', element.type);
            }
            submitData.append(field, valueToSend);
        } else {
            console.error('Field not found:', field);
            allFieldsFound = false;
        }
    });
    
    if (!allFieldsFound) {
        alert('Some fields were not found. Please check the console for details.');
        return;
    }
    
    // Show loading
    const button = buttonElement || document.querySelector(`button[onclick*="saveSection('${section}')"]`);
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    console.log('Saving section:', section);
    console.log('Fields:', fields);
    console.log('FormData entries:', Array.from(submitData.entries()));
    
    fetch('{{ route('admin.settings.update') }}', {
        method: 'POST',
        body: submitData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html, application/json',
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response type:', response.type);
        console.log('Response ok:', response.ok);
        
        // If status is OK (200-299) or redirect, reload
        if (response.ok || response.status === 0 || response.type === 'opaqueredirect') {
            console.log('Save successful, reloading page...');
            // Small delay to ensure server processed the request
            setTimeout(() => {
                window.location.reload();
            }, 100);
            return;
        }
        
        // If it's a redirect (302, 303, etc.), reload the page
        if (response.status >= 300 && response.status < 400) {
            console.log('Redirect detected, reloading page...');
            window.location.reload();
            return;
        }
        
        // If there's an error, try to get error message
        return response.text().then(text => {
            console.error('Error response:', text);
            throw new Error('Server returned status ' + response.status);
        });
    })
    .catch(error => {
        console.error('Error:', error);
        // Even on error, if it's a network error (status 0), it might have succeeded
        if (error.message.includes('status 0') || error.message === '') {
            console.log('Network error or redirect, reloading anyway...');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            alert('Error saving settings: ' + (error.message || 'Unknown error'));
            button.disabled = false;
            button.innerHTML = originalText;
        }
    });
}

console.log('🔧 Slider images script loaded');

// Add some interactive animations
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM Content Loaded');
    
    // Add floating animation to cards
    const cards = document.querySelectorAll('.bg-white');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });

    // Handle slider image selection
    const sliderImageInput = document.getElementById('sliderImageInput');
    const selectedFilesInfo = document.getElementById('selectedFilesInfo');
    let selectedFiles = [];

    console.log('🔍 Slider image input element:', sliderImageInput);
    console.log('🔍 Selected files info element:', selectedFilesInfo);

    if (sliderImageInput) {
        sliderImageInput.addEventListener('change', function(e) {
            console.log('📁 File input changed');
            const files = Array.from(e.target.files);
            console.log('📁 Selected files:', files.length, files.map(f => f.name));
            
            if (files.length > 0) {
                selectedFiles = files;
                const fileNames = files.map(f => f.name).join(', ');
                if (selectedFilesInfo) {
                    selectedFilesInfo.textContent = `${files.length} file(s) selected: ${fileNames}`;
                    selectedFilesInfo.classList.remove('hidden');
                }
            } else {
                selectedFiles = [];
                if (selectedFilesInfo) {
                    selectedFilesInfo.classList.add('hidden');
                }
            }
        });
    } else {
        console.error('❌ Slider image input not found!');
    }

    // Handle save slider images button
    const saveSliderImagesBtn = document.getElementById('saveSliderImagesBtn');
    console.log('🔍 Save button element:', saveSliderImagesBtn);
    
    if (saveSliderImagesBtn) {
        saveSliderImagesBtn.addEventListener('click', function(e) {
            console.log('💾 Save button clicked!');
            e.preventDefault();
            e.stopPropagation();
            saveSliderImages();
        });
    } else {
        console.error('❌ Save button not found!');
    }
});

// Save slider images
function saveSliderImages() {
    console.log('💾 saveSliderImages function called');
    
    const sliderImageInput = document.getElementById('sliderImageInput');
    const saveBtn = document.getElementById('saveSliderImagesBtn');
    
    console.log('📁 Input element:', sliderImageInput);
    console.log('💾 Save button:', saveBtn);
    
    if (!sliderImageInput) {
        console.error('❌ Slider image input not found!');
        alert('Error: Image input not found');
        return;
    }
    
    if (!saveBtn) {
        console.error('❌ Save button not found!');
        alert('Error: Save button not found');
        return;
    }
    
    // Get existing images to keep (those still in the DOM)
    const existingImages = [];
    const existingItems = document.querySelectorAll('.slider-image-item[data-url]');
    console.log('🖼️ Existing image items found:', existingItems.length);
    
    existingItems.forEach(item => {
        const url = item.getAttribute('data-url');
        if (url) {
            existingImages.push(url);
            console.log('🖼️ Keeping existing image:', url);
        }
    });

    // Check if there are new files to upload or existing images to keep
    const hasNewFiles = sliderImageInput.files && sliderImageInput.files.length > 0;
    const hasExistingImages = existingImages.length > 0;

    console.log('📊 Has new files:', hasNewFiles);
    console.log('📊 Has existing images:', hasExistingImages);
    console.log('📊 New files count:', hasNewFiles ? sliderImageInput.files.length : 0);

    if (!hasNewFiles && !hasExistingImages) {
        alert('Please select at least one image to upload or keep existing images');
        return;
    }

    // Show loading
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

    const formData = new FormData();
    
    // Add new files to upload
    if (hasNewFiles) {
        const files = Array.from(sliderImageInput.files);
        console.log('📤 Uploading files:', files.map(f => f.name));
        files.forEach((file, index) => {
            console.log(`📤 Adding file ${index}:`, file.name, file.size, file.type);
            formData.append(`images[${index}]`, file);
        });
    }
    
    // Add existing images to keep
    existingImages.forEach((url, index) => {
        console.log(`🖼️ Keeping existing image ${index}:`, url);
        formData.append(`keep_existing[${index}]`, url);
    });
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        formData.append('_token', csrfToken.content);
        console.log('🔐 CSRF token added');
    } else {
        console.error('❌ CSRF token not found!');
    }

    const routeUrl = '{{ route("admin.settings.save-slider-images") }}';
    console.log('🌐 Upload URL:', routeUrl);
    console.log('📦 FormData entries:', Array.from(formData.entries()).map(([k, v]) => [k, v instanceof File ? `${v.name} (${v.size} bytes)` : v]));

    fetch(routeUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
        }
    })
    .then(response => {
        console.log('📥 Response received, status:', response.status, response.statusText);
        if (!response.ok) {
            return response.text().then(text => {
                console.error('❌ Response error:', text);
                throw new Error('Server error: ' + response.status + ' - ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('✅ Save response:', data);
        if (data.success) {
            alert('Slider images saved successfully!');
            // Reset file input
            if (sliderImageInput) {
                sliderImageInput.value = '';
            }
            const selectedFilesInfo = document.getElementById('selectedFilesInfo');
            if (selectedFilesInfo) {
                selectedFilesInfo.classList.add('hidden');
            }
            location.reload();
        } else {
            alert('Failed to save images: ' + (data.message || 'Unknown error'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('❌ Save error:', error);
        alert('Failed to save images: ' + error.message);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
}

// Remove slider image (mark for deletion, will be saved on save)
function removeSliderImage(index) {
    if (!confirm('Are you sure you want to remove this slider image?')) {
        return;
    }

    const item = document.querySelector(`.slider-image-item[data-index="${index}"]`);
    if (item) {
        item.remove();
        // Update indices
        updateSliderImageIndices();
    }
}

// Update slider image indices after removal
function updateSliderImageIndices() {
    const items = document.querySelectorAll('.slider-image-item');
    items.forEach((item, newIndex) => {
        item.setAttribute('data-index', newIndex);
        const img = item.querySelector('img');
        const text = item.querySelector('.flex-1 p:first-child');
        if (text) {
            text.textContent = `Slider Image ${newIndex + 1}`;
        }
        const deleteBtn = item.querySelector('button[onclick*="removeSliderImage"]');
        if (deleteBtn) {
            deleteBtn.setAttribute('onclick', `removeSliderImage(${newIndex})`);
        }
    });
}

// Force logout all users
function forceLogoutAll() {
    if (!confirm('⚠️ WARNING: This will logout ALL users immediately. Are you sure you want to continue?')) {
        return;
    }
    
    // Create a form dynamically and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('admin.settings.force-logout-all') }}';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);
    
    // Append to body and submit
    document.body.appendChild(form);
    form.submit();
}

// Confirmation dialog for reset data
function confirmReset() {
    const message = '⚠️ WARNING: This will permanently delete EVERYTHING except admin users and SMTP settings!\n\n' +
                   'This includes:\n' +
                   '• All users (except admin)\n' +
                   '• All projects and plots\n' +
                   '• All slabs, property types, and measurement units\n' +
                   '• All settings (except SMTP)\n' +
                   '• All bookings and transactions\n' +
                   '• All wallets and commissions\n' +
                   '• All payment requests and payment methods\n' +
                   '• All KYC documents\n' +
                   '• All referrals\n' +
                   '• EVERYTHING ELSE\n\n' +
                   'Only preserved:\n' +
                   '• Admin users\n' +
                   '• SMTP/mail settings\n\n' +
                   'This action CANNOT be undone!\n\n' +
                   'Are you absolutely sure you want to proceed?';
    
    return confirm(message);
}
</script>
@endpush
@endsection

