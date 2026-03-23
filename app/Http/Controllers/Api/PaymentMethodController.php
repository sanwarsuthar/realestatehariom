<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::orderBy('sort_order')->orderBy('created_at', 'desc')->get();
        return view('admin.payment-methods.index', compact('paymentMethods'));
    }

    public function create()
    {
        return view('admin.payment-methods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:upi,bank,razorpay,other',
            'details' => 'nullable|string',
            'ifsc_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:50',
            'upi_ids_text' => 'nullable|string',
            'account_type' => 'nullable|in:savings,current,other',
            'scanner_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Handle UPI IDs
        if ($request->filled('upi_ids_text')) {
            $upiIds = array_filter(array_map('trim', explode("\n", $request->upi_ids_text)));
            $validated['upi_ids'] = !empty($upiIds) ? $upiIds : null;
        }
        unset($validated['upi_ids_text']);

        // Handle scanner photo upload
        if ($request->hasFile('scanner_photo')) {
            $validated['scanner_photo'] = $request->file('scanner_photo')->store('payment-methods/qr-codes', 'public');
        }

        // Parse details as JSON if it's a JSON string
        if (isset($validated['details']) && is_string($validated['details']) && !empty($validated['details'])) {
            $decoded = json_decode($validated['details'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['details'] = $decoded;
            } else {
                // If not JSON, store as simple text
                $validated['details'] = ['text' => $validated['details']];
            }
        } else {
            $validated['details'] = [];
        }

        PaymentMethod::create($validated);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin.payment-methods.edit', compact('paymentMethod'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:upi,bank,razorpay,other',
            'details' => 'nullable|string',
            'ifsc_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|string|max:50',
            'upi_ids_text' => 'nullable|string',
            'account_type' => 'nullable|in:savings,current,other',
            'scanner_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Handle UPI IDs
        if ($request->filled('upi_ids_text')) {
            $upiIds = array_filter(array_map('trim', explode("\n", $request->upi_ids_text)));
            $validated['upi_ids'] = !empty($upiIds) ? $upiIds : null;
        } else {
            $validated['upi_ids'] = null;
        }
        unset($validated['upi_ids_text']);

        // Handle scanner photo upload
        if ($request->hasFile('scanner_photo')) {
            // Delete old photo if exists
            if ($paymentMethod->scanner_photo && Storage::disk('public')->exists($paymentMethod->scanner_photo)) {
                Storage::disk('public')->delete($paymentMethod->scanner_photo);
            }
            $validated['scanner_photo'] = $request->file('scanner_photo')->store('payment-methods/qr-codes', 'public');
        }

        // Parse details as JSON if it's a JSON string
        if (isset($validated['details']) && is_string($validated['details']) && !empty($validated['details'])) {
            $decoded = json_decode($validated['details'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['details'] = $decoded;
            } else {
                // If not JSON, store as simple text
                $validated['details'] = ['text' => $validated['details']];
            }
        } else {
            // Keep existing details if not provided
            if (!isset($validated['details'])) {
                $validated['details'] = $paymentMethod->details ?? [];
            }
        }

        $paymentMethod->update($validated);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        // Check if payment method is being used
        if ($paymentMethod->paymentRequests()->count() > 0) {
            return redirect()->route('admin.payment-methods.index')
                ->with('error', 'Cannot delete payment method that has payment requests.');
        }

        $paymentMethod->delete();

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method deleted successfully.');
    }
}
