@extends('admin.layouts.app')

@section('title', 'Booking #' . $booking->id)
@section('page-title', 'Booking #' . $booking->id)

@section('content')
<div class="admin-page-content space-y-6 min-w-0">
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.bookings.index') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium"><i class="fas fa-arrow-left mr-1"></i>Back to Bookings</a>
    </div>

    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Booking &amp; payment summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-600">Total sale value</p>
                <p class="text-xl font-bold text-gray-900">₹{{ number_format($totalValue, 2) }}</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <p class="text-sm text-gray-600">Total received</p>
                <p class="text-xl font-bold text-green-700">₹{{ number_format($totalReceived, 2) }}</p>
            </div>
            <div class="bg-orange-50 rounded-lg p-4">
                <p class="text-sm text-gray-600">Pending</p>
                <p class="text-xl font-bold text-orange-700">₹{{ number_format($pending, 2) }}</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-sm text-gray-600">Customer</p>
                <p class="font-semibold text-gray-900">{{ $booking->customer->name ?? $booking->customer_name }}</p>
                <p class="text-xs text-gray-500">{{ $booking->customer->phone_number ?? $booking->customer_phone }}</p>
            </div>
        </div>
        <div class="mb-4">
            <p class="text-sm text-gray-600"><strong>Project:</strong> {{ $booking->plot->project->name ?? 'N/A' }} &mdash; <strong>Plot:</strong> {{ ucfirst($booking->plot->type ?? '') }} {{ $booking->plot->plot_number ?? 'N/A' }}</p>
        </div>

        <!-- Record payment (instalment) -->
        @if($pending > 0)
        <div class="border-t pt-6 mt-6">
            <h4 class="font-semibold text-gray-700 mb-3">Record payment (instalment)</h4>
            <form action="{{ route('admin.bookings.record-payment', $booking->id) }}" method="POST" class="flex flex-wrap items-end gap-4">
                @csrf
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $pending }}" value="" placeholder="Max ₹{{ number_format($pending, 2) }}" class="px-3 py-2 border rounded-lg w-40" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Payment method</label>
                    <select name="payment_method_id" class="px-3 py-2 border rounded-lg" required>
                        <option value="">Select</option>
                        @foreach($paymentMethods as $pm)
                        <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm text-gray-600 mb-1">Notes (optional)</label>
                    <input type="text" name="admin_notes" placeholder="Admin notes" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Record payment</button>
            </form>
            <p class="text-xs text-gray-500 mt-2">Commission will be released proportionally to users’ <strong>Gross</strong> wallets. It becomes <strong>withdrawable</strong> only after you mark this deal as done.</p>
        </div>
        @else
        <p class="text-green-600 font-medium">Fully paid. No pending amount.</p>
        @endif
    </div>

    <!-- Deal status: Mark deal done / Mark deal failed -->
    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Deal status</h3>
        @if($dealStatus === 'done')
            <p class="text-green-700 font-medium">Deal marked as done.</p>
            @if($booking->deal_done_at)
                <p class="text-sm text-gray-500 mt-1">Done at: {{ $booking->deal_done_at->format('d M Y, H:i') }}</p>
            @endif
            <p class="text-sm text-gray-600 mt-2">Commission for this booking has been moved to users’ <strong>withdrawable</strong> wallets (they can raise withdrawal requests from that amount only).</p>
        @elseif($dealStatus === 'failed')
            <p class="text-red-700 font-medium">Deal marked as failed.</p>
            @if($booking->deal_failed_at)
                <p class="text-sm text-gray-500 mt-1">Failed at: {{ $booking->deal_failed_at->format('d M Y, H:i') }}</p>
            @endif
            <p class="text-sm text-gray-600 mt-2">Gross and main wallet amounts for this booking have been reversed. Users see a negative cut for this deal.</p>
        @else
            <p class="text-gray-700 mb-4">Only when the deal is <strong>done</strong> <span class="whitespace-nowrap">(after full payment is received)</span>, commission is moved to users’ <strong>withdrawable</strong> wallet. Until then, users can see commission in Main and Gross but cannot withdraw from it.</p>
            @if($amountLeftForDealDone > 0)
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="text-amber-800 font-medium">Amount left to be paid for full payment: <strong>₹{{ number_format($amountLeftForDealDone, 2) }}</strong></p>
                    <p class="text-sm text-amber-700 mt-1">You cannot mark this as deal done until the full payment is received. You may still mark the deal as failed if required.</p>
                </div>
            @else
                <p class="text-green-600 font-medium mb-4">Full payment received. No pending amount – safe to mark as deal done.</p>
            @endif
            <div class="flex flex-wrap gap-3">
                @if($amountLeftForDealDone <= 0)
                    <form action="{{ route('admin.bookings.mark-deal-done', $booking->id) }}" method="POST" class="inline" onsubmit="return confirm('Mark this deal as done? Commission will move to users\\' withdrawable wallets.');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Mark deal done</button>
                    </form>
                @endif
                <form action="{{ route('admin.bookings.mark-deal-failed', $booking->id) }}" method="POST" class="inline" onsubmit="return confirm('Mark this deal as FAILED? Gross and main wallet amounts for this booking will be reversed. Continue?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Mark deal failed</button>
                </form>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-3d p-6">
        <h4 class="font-semibold text-gray-800 mb-4">Payment history</h4>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recorded by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($booking->paymentRequests->where('status', 'approved') as $pr)
                    <tr>
                        <td class="px-4 py-2 text-sm">{{ $pr->processed_at ? $pr->processed_at->format('d M Y, H:i') : $pr->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-2 text-sm font-medium text-green-600">₹{{ number_format($pr->amount, 2) }}</td>
                        <td class="px-4 py-2 text-sm">{{ $pr->paymentMethod->name ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ $pr->processedBy->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-gray-500">No payments recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
