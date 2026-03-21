@extends('admin.layouts.app')

@section('title', 'KYC Management')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">KYC Management</h1>
                <p class="text-primary-100">Review and verify user KYC documents</p>
            </div>
            <div class="text-right">
                <div class="text-4xl font-bold">{{ $kycDocuments->total() }}</div>
                <div class="text-primary-200">Total KYC Documents</div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-2xl p-6 shadow-3d">
        <form method="GET" action="{{ route('admin.kyc') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email, phone..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Verified</option>
                        <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Verified (Legacy)</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Actions</label>
                    <a href="{{ route('admin.kyc.pending') }}" class="block w-full px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-center">
                        <i class="fas fa-clock mr-2"></i>Pending ({{ \App\Models\KycDocument::where('status', 'pending')->count() }})
                    </a>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Actions</label>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                        @if(request('search') || request('status'))
                            <a href="{{ route('admin.kyc') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- KYC Documents Table -->
    <div class="bg-white rounded-2xl shadow-3d overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">KYC Documents</h3>
        </div>
        
        @if($kycDocuments->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($kycDocuments as $kyc)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                                            <span class="text-white font-medium text-sm">{{ substr($kyc->user_name ?? 'N', 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $kyc->user_name ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ $kyc->email ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-400">{{ $kyc->phone_number ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2">
                                    @php
                                        // Helper function to convert image paths to correct format
                                        // Format: https://superadmin.shrihariomgroup.com/storage/app/public/kyc/... (production)
                                        // Format: http://localhost:8000/storage/app/public/kyc/... (local)
                                        $convertImagePath = function($imagePath) {
                                            if (empty($imagePath)) {
                                                return null;
                                            }
                                            
                                            // Extract the file path from various URL formats
                                            $filePath = null;
                                            
                                            if (str_starts_with($imagePath, 'http')) {
                                                // Handle format: https://superadmin.shrihariomgroup.com/storage/app/public/kyc/...
                                                if (strpos($imagePath, '/storage/app/public/') !== false) {
                                                    $parts = explode('/storage/app/public/', $imagePath, 2);
                                                    $filePath = $parts[1] ?? null;
                                                }
                                                // Handle format: https://superadmin.shrihariomgroup.com/public/storage/kyc/...
                                                elseif (strpos($imagePath, '/public/storage/') !== false) {
                                                    $parts = explode('/public/storage/', $imagePath, 2);
                                                    $filePath = $parts[1] ?? null;
                                                }
                                                // Handle format: https://superadmin.shrihariomgroup.com/storage/kyc/...
                                                elseif (strpos($imagePath, '/storage/') !== false) {
                                                    $parts = explode('/storage/', $imagePath, 2);
                                                    $filePath = $parts[1] ?? null;
                                                }
                                            } elseif (str_starts_with($imagePath, '/storage/')) {
                                                // Relative path format: /storage/kyc/...
                                                $filePath = substr($imagePath, 9); // Remove '/storage/'
                                            }
                                            
                                            // Generate URL in the correct format
                                            if ($filePath) {
                                                $baseUrl = config('app.url');
                                                // For production, use the specific format
                                                if (strpos($baseUrl, 'shrihariomgroup.com') !== false || strpos($baseUrl, 'superadmin') !== false) {
                                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                                                }
                                                // For local development
                                                return rtrim("https://superadmin.shrihariomgroup.com", '/') . '/storage/app/public/' . $filePath;
                                            }
                                            
                                            // Fallback: if we can't extract, try to use as-is or generate from relative path
                                            if (str_starts_with($imagePath, '/storage/')) {
                                                $filePath = substr($imagePath, 9);
                                                $baseUrl = config('app.url');
                                                if (strpos($baseUrl, 'shrihariomgroup.com') !== false || strpos($baseUrl, 'superadmin') !== false) {
                                                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                                                }
                                                return rtrim("https://superadmin.shrihariomgroup.com", '/') . '/storage/app/public/' . $filePath;
                                            }
                                            
                                            return $imagePath;
                                        };
                                        
                                        $panPath = $convertImagePath($kyc->pan_image_path);
                                        $aadhaarFrontPath = $convertImagePath($kyc->aadhaar_front_image_path);
                                        $aadhaarBackPath = $convertImagePath($kyc->aadhaar_back_image_path);
                                    @endphp
                                    @if($panPath)
                                        <a href="{{ $panPath }}" target="_blank" 
                                           class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded hover:bg-blue-200">
                                            <i class="fas fa-file-image mr-1"></i>PAN
                                        </a>
                                    @endif
                                    @if($aadhaarFrontPath)
                                        <a href="{{ $aadhaarFrontPath }}" target="_blank" 
                                           class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded hover:bg-green-200">
                                            <i class="fas fa-file-image mr-1"></i>Aadhaar F
                                        </a>
                                    @endif
                                    @if($aadhaarBackPath)
                                        <a href="{{ $aadhaarBackPath }}" target="_blank" 
                                           class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded hover:bg-green-200">
                                            <i class="fas fa-file-image mr-1"></i>Aadhaar B
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($kyc->status == 'verified' || $kyc->status == 'approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Verified
                                    </span>
                                @elseif($kyc->status == 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $kyc->created_at ? $kyc->created_at->format('d M Y, h:i A') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewKycDetails({{ $kyc->id }})" 
                                            class="text-primary-600 hover:text-primary-900"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @if($kyc->status == 'pending')
                                        <button onclick="approveKyc({{ $kyc->id }})" 
                                                class="text-green-600 hover:text-green-900"
                                                title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectKyc({{ $kyc->id }})" 
                                                class="text-red-600 hover:text-red-900"
                                                title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @elseif($kyc->status == 'verified' || $kyc->status == 'approved')
                                        <button onclick="rejectKyc({{ $kyc->id }})" 
                                                class="text-red-600 hover:text-red-900"
                                                title="Reject (User can re-upload)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($kycDocuments->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $kycDocuments->links() }}
        </div>
        @endif
        @else
        <div class="px-6 py-12 text-center">
            <div class="text-gray-500">
                <i class="fas fa-file-alt text-4xl mb-4"></i>
                <p class="text-lg">No KYC documents found</p>
                <p class="text-sm">KYC documents will appear here once users submit them.</p>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- View KYC Details Modal -->
<div id="kycDetailsModal" class="hidden fixed inset-0 bg-gray-600  overflow-y-auto h-full w-full" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">KYC Document Details</h3>
            <button onclick="closeKycDetails()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="kycDetailsContent" class="space-y-4">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<!-- Reject KYC Modal -->
<div id="rejectKycModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">Reject KYC Document</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="rejectKycForm" onsubmit="submitReject(event)">
            <input type="hidden" id="rejectKycId" name="kyc_id">
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Rejecting this KYC will clear all uploaded documents and allow the user to re-upload from the app.</p>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason *</label>
                <textarea id="rejectionReason" name="rejection_reason" rows="4" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                          placeholder="Please provide a reason for rejection..."></textarea>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRejectModal()" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-times mr-2"></i>Reject KYC
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Auto-submit form when filters change (with debounce for search input)
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            const searchInput = filterForm.querySelector('input[name="search"]');
            const selects = filterForm.querySelectorAll('select');
            
            let searchTimeout;
            
            // Handle search input with debounce
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        filterForm.submit();
                    }, 500); // Wait 500ms after user stops typing
                });
            }
            
            // Handle select changes - submit immediately
            selects.forEach(function(select) {
                select.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        }
    });

    // Base URL from Laravel
    const baseUrl = '{{ url("/") }}';
    
    // Route URL templates
    const getKycDetailsUrl = (id) => `${baseUrl}/admin/kyc/${id}/details`;
    const getKycApproveUrl = (id) => `${baseUrl}/admin/kyc/${id}/approve`;
    const getKycRejectUrl = (id) => `${baseUrl}/admin/kyc/${id}/reject`;
    
    // Make function globally accessible
    window.viewKycDetails = function(id) {
        console.log('Viewing KYC details for ID:', id);
        
        if (!id) {
            alert('Invalid KYC ID');
            return;
        }
        
        // Show loading state
        const modal = document.getElementById('kycDetailsModal');
        const content = document.getElementById('kycDetailsContent');
        
        if (!modal || !content) {
            alert('Modal elements not found');
            return;
        }
        
        content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-primary-600"></i><p class="mt-4 text-gray-600">Loading KYC details...</p></div>';
        modal.classList.remove('hidden');
        
        fetch(getKycDetailsUrl(id), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const statusBadge = (data.kyc.status === 'verified' || data.kyc.status === 'approved') 
                        ? '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><i class="fas fa-check-circle mr-1"></i>Verified</span>'
                        : data.kyc.status === 'rejected'
                        ? '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"><i class="fas fa-times-circle mr-1"></i>Rejected</span>'
                        : '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800"><i class="fas fa-clock mr-1"></i>Pending</span>';
                    
                    content.innerHTML = `
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">User Name</label>
                                    <p class="text-gray-900 font-semibold">${data.kyc.user_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <p class="text-gray-900">${data.kyc.email || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <p class="text-gray-900">${data.kyc.phone_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    ${statusBadge}
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Submitted On</label>
                                    <p class="text-gray-900">${data.kyc.created_at || 'N/A'}</p>
                                </div>
                                ${data.kyc.verified_at ? `
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Verified On</label>
                                    <p class="text-gray-900">${data.kyc.verified_at}</p>
                                </div>
                                ` : ''}
                            </div>
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">KYC Documents</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    ${data.kyc.pan_image_path ? `
                                        <div class="border rounded-lg p-3">
                                            <p class="text-sm font-medium mb-2 text-center">PAN Card</p>
                                            <a href="${data.kyc.pan_image_path}" target="_blank" class="block">
                                                <img src="${data.kyc.pan_image_path}" alt="PAN" 
                                                     class="w-full h-48 object-contain rounded-lg border bg-gray-50"
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImage not found%3C/text%3E%3C/svg%3E'">
                                                </img>
                                            </a>
                                            <a href="${data.kyc.pan_image_path}" target="_blank" 
                                               class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                                <i class="fas fa-external-link-alt mr-1"></i>Open Full Size
                                            </a>
                                        </div>
                                    ` : '<div class="border rounded-lg p-3 text-center text-gray-400"><p>PAN Card not uploaded</p></div>'}
                                    ${data.kyc.aadhaar_front_image_path ? `
                                        <div class="border rounded-lg p-3">
                                            <p class="text-sm font-medium mb-2 text-center">Aadhaar Front</p>
                                            <a href="${data.kyc.aadhaar_front_image_path}" target="_blank" class="block">
                                                <img src="${data.kyc.aadhaar_front_image_path}" alt="Aadhaar Front" 
                                                     class="w-full h-48 object-contain rounded-lg border bg-gray-50"
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImage not found%3C/text%3E%3C/svg%3E'">
                                                </img>
                                            </a>
                                            <a href="${data.kyc.aadhaar_front_image_path}" target="_blank" 
                                               class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                                <i class="fas fa-external-link-alt mr-1"></i>Open Full Size
                                            </a>
                                        </div>
                                    ` : '<div class="border rounded-lg p-3 text-center text-gray-400"><p>Aadhaar Front not uploaded</p></div>'}
                                    ${data.kyc.aadhaar_back_image_path ? `
                                        <div class="border rounded-lg p-3">
                                            <p class="text-sm font-medium mb-2 text-center">Aadhaar Back</p>
                                            <a href="${data.kyc.aadhaar_back_image_path}" target="_blank" class="block">
                                                <img src="${data.kyc.aadhaar_back_image_path}" alt="Aadhaar Back" 
                                                     class="w-full h-48 object-contain rounded-lg border bg-gray-50"
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImage not found%3C/text%3E%3C/svg%3E'">
                                                </img>
                                            </a>
                                            <a href="${data.kyc.aadhaar_back_image_path}" target="_blank" 
                                               class="mt-2 block text-center text-sm text-primary-600 hover:text-primary-800">
                                                <i class="fas fa-external-link-alt mr-1"></i>Open Full Size
                                            </a>
                                        </div>
                                    ` : '<div class="border rounded-lg p-3 text-center text-gray-400"><p>Aadhaar Back not uploaded</p></div>'}
                                </div>
                            </div>
                            ${data.kyc.rejection_reason ? `
                                <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <label class="block text-sm font-medium text-red-700 mb-2">Rejection Reason</label>
                                    <p class="text-red-900">${data.kyc.rejection_reason}</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-4xl mb-4"></i><p>${data.message || 'Failed to load KYC details'}</p></div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-4xl mb-4"></i><p>Error loading KYC details: ${error.message}</p><p class="text-sm text-gray-500 mt-2">Please check the console for more details.</p></div>`;
            });
    }

    window.closeKycDetails = function() {
        const modal = document.getElementById('kycDetailsModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    window.approveKyc = function(id) {
        if (confirm('Are you sure you want to approve this KYC document?')) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken || !csrfToken.content) {
                alert('CSRF token not found. Please refresh the page and try again.');
                return;
            }
            
            fetch(getKycApproveUrl(id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken.content
                },
                body: JSON.stringify({})
            })
            .then(response => {
                console.log('Approve response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Approve response data:', data);
                if (data.success) {
                    alert('KYC document approved successfully');
                    location.reload();
                } else {
                    alert('Failed to approve KYC: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error approving KYC:', error);
                alert('Failed to approve KYC document: ' + error.message);
            });
        }
    }

    window.rejectKyc = function(id) {
        const rejectIdInput = document.getElementById('rejectKycId');
        const rejectReasonInput = document.getElementById('rejectionReason');
        const rejectModal = document.getElementById('rejectKycModal');
        if (rejectIdInput && rejectReasonInput && rejectModal) {
            rejectIdInput.value = id;
            rejectReasonInput.value = '';
            rejectModal.classList.remove('hidden');
        }
    }

    window.closeRejectModal = function() {
        const rejectModal = document.getElementById('rejectKycModal');
        if (rejectModal) {
            rejectModal.classList.add('hidden');
        }
    }

    window.submitReject = function(event) {
        event.preventDefault();
        const id = document.getElementById('rejectKycId').value;
        const reason = document.getElementById('rejectionReason').value;

        fetch(getKycRejectUrl(id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                rejection_reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'KYC document rejected successfully. User can now re-upload documents from the app.');
                window.closeRejectModal();
                location.reload();
            } else {
                alert('Failed to reject KYC: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to reject KYC document');
        });
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const detailsModal = document.getElementById('kycDetailsModal');
        const rejectModal = document.getElementById('rejectKycModal');
        if (event.target == detailsModal) {
            closeKycDetails();
        }
        if (event.target == rejectModal) {
            closeRejectModal();
        }
    }
</script>
@endpush
@endsection

