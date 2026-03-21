<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function aboutUs()
    {
        $content = Setting::get('about_us_content', '');
        return view('admin.content.about-us', compact('content'));
    }

    public function updateAboutUs(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
            'html_file' => 'nullable|file|mimes:html,htm|max:10240', // Max 10MB
        ]);

        $content = '';

        // If HTML file is uploaded, read its content
        if ($request->hasFile('html_file')) {
            $file = $request->file('html_file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->has('content')) {
            // Use textarea content (can be HTML)
            $content = $request->content;
        }

        if (empty($content)) {
            return redirect()->back()
                ->withErrors(['content' => 'Please provide content or upload an HTML file.']);
        }

        Setting::set('about_us_content', $content);

        return redirect()->route('admin.content.about-us')
            ->with('success', 'About Us content updated successfully!');
    }

    public function contactUs()
    {
        $data = [
            'phone' => Setting::get('contact_phone', ''),
            'email' => Setting::get('contact_email', ''),
            'address' => Setting::get('contact_address', ''),
            'website' => Setting::get('contact_website', ''),
        ];
        return view('admin.content.contact-us', compact('data'));
    }

    public function updateContactUs(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
        ]);

        Setting::set('contact_phone', $request->phone ?? '');
        Setting::set('contact_email', $request->email ?? '');
        Setting::set('contact_address', $request->address ?? '');
        Setting::set('contact_website', $request->website ?? '');

        return redirect()->route('admin.content.contact-us')
            ->with('success', 'Contact Us information updated successfully!');
    }

    public function privacyPolicy()
    {
        $content = Setting::get('privacy_policy_content', '');
        return view('admin.content.privacy-policy', compact('content'));
    }

    public function updatePrivacyPolicy(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
            'html_file' => 'nullable|file|mimes:html,htm|max:10240', // Max 10MB
        ]);

        $content = '';

        // If HTML file is uploaded, read its content
        if ($request->hasFile('html_file')) {
            $file = $request->file('html_file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->has('content')) {
            // Use textarea content (can be HTML)
            $content = $request->content;
        }

        if (empty($content)) {
            return redirect()->back()
                ->withErrors(['content' => 'Please provide content or upload an HTML file.']);
        }

        Setting::set('privacy_policy_content', $content);

        return redirect()->route('admin.content.privacy-policy')
            ->with('success', 'Privacy Policy content updated successfully!');
    }

    public function termsConditions()
    {
        $content = Setting::get('terms_conditions_content', '');
        return view('admin.content.terms-conditions', compact('content'));
    }

    public function updateTermsConditions(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
            'html_file' => 'nullable|file|mimes:html,htm|max:10240', // Max 10MB
        ]);

        $content = '';

        // If HTML file is uploaded, read its content
        if ($request->hasFile('html_file')) {
            $file = $request->file('html_file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->has('content')) {
            // Use textarea content (can be HTML)
            $content = $request->content;
        }

        if (empty($content)) {
            return redirect()->back()
                ->withErrors(['content' => 'Please provide content or upload an HTML file.']);
        }

        Setting::set('terms_conditions_content', $content);

        return redirect()->route('admin.content.terms-conditions')
            ->with('success', 'Terms & Conditions content updated successfully!');
    }
}

