@extends('admin.layouts.app')

@section('title', 'Create Payment Method')
@section('page-title', 'Create Payment Method')

@section('content')
<div class="admin-page-content bg-white rounded-2xl shadow-3d p-6 min-w-0 max-w-4xl">
    <form method="POST" action="{{ route('admin.payment-methods.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                <input 
                    type="text" 
                    name="name" 
                    value="{{ old('name') }}" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                    placeholder="e.g., UPI Payment, Bank Transfer"
                    required
                >
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label class="block text-sm text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                <select 
                    name="type" 
                    id="payment_type"
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                    required
                >
                    <option value="">Select Type</option>
                    <option value="upi" {{ old('type') === 'upi' ? 'selected' : '' }}>UPI</option>
                    <option value="bank" {{ old('type') === 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="razorpay" {{ old('type') === 'razorpay' ? 'selected' : '' }}>Razorpay</option>
                    <option value="other" {{ old('type') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
                @error('type')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Bank Account Fields (shown when type is bank) -->
        <div id="bank_fields" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Account Number</label>
                <input 
                    type="text" 
                    name="account_number" 
                    value="{{ old('account_number') }}" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                    placeholder="Enter account number"
                >
                @error('account_number')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label class="block text-sm text-gray-600 mb-1">IFSC Code</label>
                <input 
                    type="text" 
                    name="ifsc_code" 
                    value="{{ old('ifsc_code') }}" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                    placeholder="Enter IFSC code"
                >
                @error('ifsc_code')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label class="block text-sm text-gray-600 mb-1">Account Type</label>
                <select 
                    name="account_type" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                >
                    <option value="">Select Account Type</option>
                    <option value="savings" {{ old('account_type') === 'savings' ? 'selected' : '' }}>Savings</option>
                    <option value="current" {{ old('account_type') === 'current' ? 'selected' : '' }}>Current</option>
                    <option value="other" {{ old('account_type') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
                @error('account_type')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- UPI Fields (shown when type is upi) -->
        <div id="upi_fields" style="display: none;">
            <label class="block text-sm text-gray-600 mb-1">UPI IDs (one per line)</label>
            <textarea 
                name="upi_ids_text" 
                id="upi_ids_text"
                rows="3"
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                placeholder="Enter UPI IDs, one per line&#10;e.g.,&#10;yourname@paytm&#10;yourname@upi"
            >{{ old('upi_ids_text') }}</textarea>
            <p class="text-xs text-gray-500 mt-1">Enter multiple UPI IDs, one per line</p>
            @error('upi_ids')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Scanner Photo (QR Code) -->
        <div>
            <label class="block text-sm text-gray-600 mb-1">Scanner Photo (QR Code)</label>
            <input 
                type="file" 
                name="scanner_photo" 
                accept="image/*"
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
            >
            <p class="text-xs text-gray-500 mt-1">Upload QR code image (JPG, PNG, max 5MB)</p>
            @error('scanner_photo')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Payment Details (for other types or additional info) -->
        <div>
            <label class="block text-sm text-gray-600 mb-1">Additional Details (Optional)</label>
            <p class="text-xs text-gray-500 mb-2">Enter additional payment details as JSON. Examples:</p>
            <ul class="text-xs text-gray-500 mb-2 list-disc list-inside">
                <li>Bank: {"bank_name": "Bank Name", "account_holder": "Account Holder Name", "branch": "Branch Name"}</li>
                <li>Razorpay: {"merchant_id": "your_merchant_id"}</li>
                <li>Other: {"text": "Any payment details here"}</li>
            </ul>
            <textarea 
                name="details" 
                id="payment_details"
                rows="4"
                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm" 
                placeholder='{"bank_name": "Bank Name", "account_holder": "Account Holder Name"}'
            >{{ old('details') }}</textarea>
            @error('details')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Sort Order</label>
                <input 
                    type="number" 
                    name="sort_order" 
                    value="{{ old('sort_order', 0) }}" 
                    class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                    min="0"
                >
                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
            </div>
            
            <div class="flex items-center">
                <input 
                    type="checkbox" 
                    name="is_active" 
                    id="is_active"
                    value="1"
                    {{ old('is_active', true) ? 'checked' : '' }}
                    class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                >
                <label for="is_active" class="ml-2 text-sm text-gray-600">Active (visible to users)</label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-4 border-t">
            <a href="{{ route('admin.payment-methods.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-save mr-2"></i>Create Payment Method
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('payment_type').addEventListener('change', function() {
    const type = this.value;
    const bankFields = document.getElementById('bank_fields');
    const upiFields = document.getElementById('upi_fields');
    
    // Hide all fields first
    bankFields.style.display = 'none';
    upiFields.style.display = 'none';
    
    // Show relevant fields based on type
    if (type === 'bank') {
        bankFields.style.display = 'grid';
    } else if (type === 'upi') {
        upiFields.style.display = 'block';
    }
});

// Trigger on page load if old value exists
@if(old('type'))
document.getElementById('payment_type').dispatchEvent(new Event('change'));
@endif
</script>
@endsection
