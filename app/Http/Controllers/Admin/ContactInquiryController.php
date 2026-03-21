<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;

class ContactInquiryController extends Controller
{
    /**
     * Display a listing of contact inquiries
     */
    public function index(Request $request)
    {
        $query = ContactInquiry::with(['user', 'resolver'])
            ->latest();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $inquiries = $query->paginate(20);

        return view('admin.contact-inquiries.index', compact('inquiries'));
    }

    /**
     * Display the specified contact inquiry
     */
    public function show(ContactInquiry $contactInquiry)
    {
        $contactInquiry->load(['user', 'resolver']);
        return view('admin.contact-inquiries.show', compact('contactInquiry'));
    }

    /**
     * Mark inquiry as resolved
     */
    public function resolve(Request $request, ContactInquiry $contactInquiry)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $contactInquiry->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'admin_notes' => $request->admin_notes,
        ]);

        return redirect()->route('admin.contact-inquiries.index')
            ->with('success', 'Contact inquiry marked as resolved');
    }

    /**
     * Reopen a resolved inquiry
     */
    public function reopen(ContactInquiry $contactInquiry)
    {
        $contactInquiry->update([
            'status' => 'pending',
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        return redirect()->route('admin.contact-inquiries.index')
            ->with('success', 'Contact inquiry reopened');
    }
}
