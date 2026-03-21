@extends('admin.layouts.app')

@section('title', 'Payment Methods')
@section('page-title', 'Payment Methods')

@section('content')
<div class="admin-page-content space-y-4 min-w-0">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">Manage Payment Methods</h2>
        <a href="{{ route('admin.payment-methods.create') }}" role="button" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700">
            <i class="fas fa-plus mr-2"></i>Add Payment Method
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <div class="bg-white rounded-2xl shadow-3d p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($paymentMethods as $method)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $method->name }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 capitalize">
                                {{ $method->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-500">
                                @if(is_array($method->details))
                                    @if(isset($method->details['upi_id']))
                                        UPI ID: {{ $method->details['upi_id'] }}
                                    @elseif(isset($method->details['account_number']))
                                        Account: {{ $method->details['account_number'] }}<br>
                                        IFSC: {{ $method->details['ifsc'] ?? 'N/A' }}
                                    @elseif(isset($method->details['text']))
                                        {{ $method->details['text'] }}
                                    @else
                                        {{ json_encode($method->details) }}
                                    @endif
                                @else
                                    {{ $method->details }}
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                @if($method->is_active) bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ $method->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $method->sort_order }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.payment-methods.edit', $method->id) }}" class="text-primary-600 hover:text-primary-900">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form action="{{ route('admin.payment-methods.destroy', $method->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No payment methods found. <a href="{{ route('admin.payment-methods.create') }}" class="text-primary-600 hover:underline">Create one</a></td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

