@extends('admin.layouts.app')

@section('title', 'User Management')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">User Management</h1>
                <p class="text-primary-100">Manage all broker accounts, their status, and KYC verification.</p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <div class="text-4xl font-bold">{{ $users->total() }}</div>
                    <div class="text-primary-200">Total Users</div>
                </div>
                <button onclick="openCreateUserModal()" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-primary-50 transition-colors shadow-lg">
                    <i class="fas fa-plus mr-2"></i>Add New User
                </button>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-2xl p-6 shadow-3d">
        <form method="GET" action="{{ route('admin.users') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Status</option>
                        @foreach($statusOptions as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Slab</label>
                    <select name="slab_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Slabs</option>
                        @foreach($slabs as $slab)
                            <option value="{{ $slab->id }}" {{ request('slab_id') == $slab->id ? 'selected' : '' }}>{{ $slab->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">KYC Status</label>
                    <select name="kyc_verified" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All KYC</option>
                        <option value="1" {{ request('kyc_verified') === '1' ? 'selected' : '' }}>Verified</option>
                        <option value="0" {{ request('kyc_verified') === '0' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-end space-x-2">
                <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                @if(request('search') || request('status') || request('slab_id') || request('kyc_verified'))
                <a href="{{ route('admin.users') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl shadow-3d overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Broker Users</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slabs</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">KYC</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Income</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center">
                                        <span class="text-white font-medium text-sm">{{ substr($user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <span class="text-primary-600 font-semibold">{{ $user->referral_code }}</span> - {{ $user->name }}
                                    </div>
                                    <div class="text-sm text-gray-500">ID: {{ $user->broker_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $user->email }}</div>
                            <div class="text-sm text-gray-500">{{ $user->phone_number }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @if(isset($user->all_slabs) && $user->all_slabs->count() > 0)
                                    @foreach($user->all_slabs as $userSlab)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                              style="background-color: {{ $userSlab->slab_color ?? '#8B5CF6' }}20; color: {{ $userSlab->slab_color ?? '#8B5CF6' }};"
                                              title="{{ $userSlab->property_type_name ?? 'N/A' }}">
                                            {{ $userSlab->slab_name ?? 'N/A' }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: {{ $user->slab_color ?? '#8B5CF6' }}20; color: {{ $user->slab_color ?? '#8B5CF6' }};">
                                        {{ $user->slab_name ?? 'Slab1' }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($user->status == 'active') bg-green-100 text-green-800
                                @elseif($user->status == 'inactive') bg-gray-100 text-gray-800
                                @else bg-red-100 text-red-800
                                @endif">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($user->kyc_verified) bg-green-100 text-green-800
                                @else bg-yellow-100 text-yellow-800
                                @endif">
                                @if($user->kyc_verified) Verified @else Pending @endif
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="font-semibold">₹{{ number_format($user->total_commission_earned ?? 0, 2) }}</div>
                            <div class="text-xs text-gray-500">Direct Sales</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="font-semibold text-green-600">₹{{ number_format($user->referral_income ?? 0, 2) }}</div>
                            <div class="text-xs text-gray-500">From Downline</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.users.show', $user->id) }}" class="text-primary-600 hover:text-primary-900" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button
                                    class="text-indigo-600 hover:text-indigo-900"
                                    onclick="openChangePasswordModal('{{ route('admin.users.change-password', $user->id) }}', {{ $user->id }})"
                                    title="Change Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="text-yellow-600 hover:text-yellow-900" onclick="toggleStatus({{ $user->id }}, '{{ $user->status }}')" title="Change Status">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="text-red-600 hover:text-red-900" onclick="deleteUser({{ $user->id }}, '{{ $user->name }}')" title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-users text-4xl mb-4"></i>
                                <p class="text-lg">No users found</p>
                                <p class="text-sm">Users will appear here once they register.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Create User Modal (hidden by default via .hidden in admin.css) -->
<div id="createUserModal" class="fixed inset-0 bg-black  hidden z-[9999] flex items-center justify-center" aria-hidden="true">
    <div class="bg-white rounded-2xl shadow-3d-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-800">Create New User</h2>
                <button onclick="closeCreateUserModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form id="createUserForm" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter full name">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
                <input type="tel" name="phone_number" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter phone number">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter email address">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter password (min 6 characters)">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Referral Code <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" name="referral_code" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" 
                           placeholder="Enter referral code (e.g., SHOB00001)" 
                           id="referralCodeInput"
                           list="referralCodesList"
                           autocomplete="off">
                    <datalist id="referralCodesList">
                        @foreach($referralCodes as $refCode)
                        <option value="{{ $refCode->referral_code }}">{{ $refCode->referral_code }} - {{ $refCode->name }}</option>
                        @endforeach
                    </datalist>
                </div>
                <p class="text-xs text-gray-500 mt-1">User will be added below the user with this referral code</p>
            </div>
            
            <div id="createUserError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>
            
            <div class="flex space-x-3 pt-4">
                <button type="submit" class="flex-1 bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>Create User
                </button>
                <button type="button" onclick="closeCreateUserModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal (hidden by default) -->
<div id="changePasswordModal" class="fixed inset-0 bg-black  hidden z-[9999] flex items-center justify-center" aria-hidden="true">
    <div class="bg-white rounded-2xl shadow-3d-lg w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-800">Change Password</h2>
                <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <form id="changePasswordForm" class="p-6 space-y-4">
            <input type="hidden" id="changePasswordUserId" name="user_id" value="">
            <input type="hidden" id="changePasswordUrl" value="">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password <span class="text-red-500">*</span></label>
                <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Enter new password (min 6 characters)">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="new_password_confirmation" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Confirm new password">
            </div>

            <div id="changePasswordError" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg"></div>

            <div class="flex space-x-3 pt-4">
                <button type="submit" class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition-colors">
                    <i class="fas fa-key mr-2"></i>Change Password
                </button>
                <button type="button" onclick="closeChangePasswordModal()" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
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
});

function toggleStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this user?`)) {
        fetch(`/admin/users/${userId}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating user status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating user status');
        });
    }
}

function deleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"?\n\nThis will soft delete the user and they will not appear in the users list.`)) {
        fetch(`/admin/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully.');
                location.reload();
            } else {
                alert('Error deleting user: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting user');
        });
    }
}

function openCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
    document.getElementById('createUserForm').reset();
    document.getElementById('createUserError').classList.add('hidden');
    document.getElementById('createUserError').textContent = '';
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
    document.getElementById('createUserForm').reset();
    document.getElementById('createUserError').classList.add('hidden');
    document.getElementById('createUserError').textContent = '';
}

// Handle create user form submission
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const errorDiv = document.getElementById('createUserError');
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
    
    // Disable submit button
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    fetch('{{ route("admin.users.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('User created successfully!\n\nBroker ID: ' + data.data.broker_id + '\nReferral Code: ' + data.data.referral_code);
            closeCreateUserModal();
            location.reload();
        } else {
            // Show validation errors
            let errorMessage = data.message || 'Failed to create user';
            if (data.errors) {
                const errorList = Object.values(data.errors).flat().join('\n');
                errorMessage = errorMessage + '\n' + errorList;
            }
            errorDiv.textContent = errorMessage;
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorDiv.textContent = 'An error occurred while creating the user. Please try again.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Close modal when clicking outside
document.getElementById('createUserModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateUserModal();
    }
});

function openChangePasswordModal(changePasswordUrl, userId) {
    document.getElementById('changePasswordModal').classList.remove('hidden');
    document.getElementById('changePasswordForm').reset();
    document.getElementById('changePasswordUserId').value = userId;
    document.getElementById('changePasswordUrl').value = changePasswordUrl;

    const errorDiv = document.getElementById('changePasswordError');
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('hidden');
    document.getElementById('changePasswordForm').reset();

    const errorDiv = document.getElementById('changePasswordError');
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
}

document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const userId = document.getElementById('changePasswordUserId').value;
    const changePasswordUrl = document.getElementById('changePasswordUrl').value;
    const formData = new FormData(this);
    const errorDiv = document.getElementById('changePasswordError');
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';

    fetch(changePasswordUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password updated successfully.');
            closeChangePasswordModal();
            location.reload();
        } else {
            let errorMessage = data.message || 'Failed to update password';
            if (data.errors) {
                errorMessage = errorMessage + '\n' + Object.values(data.errors).flat().join('\n');
            }
            errorDiv.textContent = errorMessage;
            errorDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        errorDiv.textContent = 'An error occurred while updating the password. Please try again.';
        errorDiv.classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Close change password modal when clicking outside
document.getElementById('changePasswordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChangePasswordModal();
    }
});
</script>
@endpush
@endsection
