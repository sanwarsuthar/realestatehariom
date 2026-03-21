@extends('admin.layouts.app')

@section('title', 'Contact Inquiry Details')

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl p-6 text-white shadow-3d-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Inquiry #{{ $contactInquiry->id }}</h1>
                <p class="text-primary-100">View and manage contact inquiry details.</p>
            </div>
            <a href="{{ route('admin.contact-inquiries.index') }}" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Inquiry Details -->
            <div class="bg-white rounded-2xl p-6 shadow-3d">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Inquiry Details</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Subject</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $contactInquiry->subject }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Message</label>
                        <div class="bg-gray-50 rounded-lg p-4 text-gray-900 whitespace-pre-wrap">{{ $contactInquiry->message }}</div>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white rounded-2xl p-6 shadow-3d">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Contact Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Name</label>
                        <p class="text-gray-900">{{ $contactInquiry->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Email</label>
                        <p class="text-gray-900">{{ $contactInquiry->email }}</p>
                    </div>
                    @if($contactInquiry->phone)
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Phone</label>
                        <p class="text-gray-900">{{ $contactInquiry->phone }}</p>
                    </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Submitted</label>
                        <p class="text-gray-900">{{ $contactInquiry->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                </div>

                @if($contactInquiry->user)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Registered User</label>
                    <p class="text-gray-900">
                        {{ $contactInquiry->user->name }} 
                        <span class="text-primary-600">({{ $contactInquiry->user->referral_code }})</span>
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-2xl p-6 shadow-3d">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Status</h3>
                
                <div class="mb-4">
                    @if($contactInquiry->status == 'pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-clock mr-2"></i>Pending
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-2"></i>Resolved
                        </span>
                    @endif
                </div>

                @if($contactInquiry->status == 'resolved' && $contactInquiry->resolver)
                <div class="text-sm text-gray-600">
                    <p class="font-medium">Resolved by:</p>
                    <p>{{ $contactInquiry->resolver->name }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $contactInquiry->resolved_at->format('M d, Y h:i A') }}</p>
                </div>
                @endif

                @if($contactInquiry->status == 'pending')
                <form method="POST" action="{{ route('admin.contact-inquiries.resolve', $contactInquiry) }}" class="mt-4">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Add any notes about resolution...">{{ $contactInquiry->admin_notes }}</textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-check-circle mr-2"></i>Mark as Resolved
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.contact-inquiries.reopen', $contactInquiry) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                        <i class="fas fa-redo mr-2"></i>Reopen Inquiry
                    </button>
                </form>
                @endif
            </div>

            <!-- Admin Notes -->
            @if($contactInquiry->admin_notes)
            <div class="bg-white rounded-2xl p-6 shadow-3d">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Admin Notes</h3>
                <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap">{{ $contactInquiry->admin_notes }}</div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

