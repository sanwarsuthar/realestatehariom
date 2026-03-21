<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * Get About Us content
     */
    public function getAboutUs()
    {
        try {
            $content = Setting::get('about_us_content', '');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch About Us content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Contact Us content
     */
    public function getContactUs()
    {
        try {
            $data = [
                'phone' => Setting::get('contact_phone', ''),
                'email' => Setting::get('contact_email', ''),
                'address' => Setting::get('contact_address', ''),
                'website' => Setting::get('contact_website', ''),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Contact Us content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Privacy Policy content
     */
    public function getPrivacyPolicy()
    {
        try {
            $content = Setting::get('privacy_policy_content', '');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Privacy Policy content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Terms & Conditions content
     */
    public function getTermsConditions()
    {
        try {
            $content = Setting::get('terms_conditions_content', '');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Terms & Conditions content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check app build number
     */
    public function checkAppVersion(Request $request)
    {
        try {
            $platform = $request->input('platform', 'android'); // android or ios
            $currentBuildNumber = (int) $request->input('build_number', 1);
            
            $requiredBuildNumber = (int) ($platform === 'ios' 
                ? Setting::get('ios_build_number', '1')
                : Setting::get('android_build_number', '1'));
            
            $storeUrl = $platform === 'ios'
                ? Setting::get('ios_store_url', '')
                : Setting::get('android_store_url', '');
            
            $forceUpdate = Setting::get('force_app_update', '0') === '1';
            
            // Compare build numbers (simple integer comparison)
            $needsUpdate = $currentBuildNumber < $requiredBuildNumber;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current_build_number' => $currentBuildNumber,
                    'required_build_number' => $requiredBuildNumber,
                    'needs_update' => $needsUpdate,
                    'force_update' => $forceUpdate && $needsUpdate,
                    'store_url' => $storeUrl,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check app build number: ' . $e->getMessage()
            ], 500);
        }
    }
}

