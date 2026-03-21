<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\PropertyType;
use App\Models\Slab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class SettingController extends Controller
{
    public function index()
    {
        // Clean up slider images - convert any full URLs to relative paths
        $this->cleanupSliderImagePaths();
        
        // Get all settings from database, with defaults
        $settings = Setting::getAll();
        
        // Merge with defaults for any missing settings
        $defaults = [
            'app_name' => config('app.name', 'Shree Hari Om'),
            'otp_expiry_minutes' => '5',
            'min_withdrawal_amount' => '1000',
            'max_withdrawal_amount' => '100000',
            'kyc_required' => '1',
            'maintenance_mode' => '0',
            'maintenance_message' => 'We are currently performing scheduled maintenance to improve your experience. Please check back soon.',
            'slab_commission_bronze' => '35',
            'slab_commission_silver' => '40',
            'slab_commission_gold' => '45',
            'slab_commission_diamond' => '50',
            'android_build_number' => '1',
            'android_store_url' => '',
            'ios_build_number' => '1',
            'ios_store_url' => '',
            'force_app_update' => '0',
        ];
        
        $settings = array_merge($defaults, $settings);
        
        // Convert boolean strings to actual booleans for display
        $settings['kyc_required'] = (bool)($settings['kyc_required'] ?? 0);
        $settings['maintenance_mode'] = (bool)($settings['maintenance_mode'] ?? 0);
        
        // Get property types and slabs for property-type-based commission configuration
        $propertyTypes = PropertyType::where('is_active', true)->with('measurementUnit')->orderBy('name')->get();
        // Get slabs with their property types
        $slabs = Slab::where('is_active', true)->with('propertyTypes')->orderBy('sort_order')->get();
        
        // Parse property-type-based commissions
        $propertyTypeCommissionsJson = $settings['property_type_commissions'] ?? '{}';
        $propertyTypeCommissions = json_decode($propertyTypeCommissionsJson, true) ?? [];

        return view('admin.settings.index', compact('settings', 'propertyTypes', 'slabs', 'propertyTypeCommissions'));
    }

    public function update(Request $request)
    {
        // Log all incoming request data for debugging
        \Log::info('Settings update request', [
            'all_data' => $request->all(),
            'android_build_number' => $request->input('android_build_number'),
            'ios_build_number' => $request->input('ios_build_number'),
        ]);
        
        $request->validate([
            // General Settings
            'app_name' => 'sometimes|required|string|max:255',
            'otp_expiry_minutes' => 'sometimes|required|integer|min:1|max:60',
            'min_withdrawal_amount' => 'sometimes|required|numeric|min:0',
            'max_withdrawal_amount' => 'sometimes|required|numeric|min:0|gte:min_withdrawal_amount',
            'kyc_required' => 'sometimes|boolean',
            'maintenance_mode' => 'sometimes|boolean',
            'maintenance_message' => 'sometimes|nullable|string|max:1000',
            
            // Slab Commission Percentages
            'slab_commission_bronze' => 'sometimes|required|numeric|min:0|max:100',
            'slab_commission_silver' => 'sometimes|required|numeric|min:0|max:100',
            'slab_commission_gold' => 'sometimes|required|numeric|min:0|max:100',
            'slab_commission_diamond' => 'sometimes|required|numeric|min:0|max:100',
            
            // App Update Settings
            'android_build_number' => 'sometimes|nullable|integer|min:1',
            'android_store_url' => 'sometimes|nullable|url|max:500',
            'ios_build_number' => 'sometimes|nullable|integer|min:1',
            'ios_store_url' => 'sometimes|nullable|url|max:500',
            'force_app_update' => 'sometimes|boolean',
        ]);

        try {
            // Save all settings (only if provided in request)
            if ($request->filled('app_name')) {
                Setting::set('app_name', $request->app_name);
            }
            if ($request->filled('otp_expiry_minutes')) {
                Setting::set('otp_expiry_minutes', $request->otp_expiry_minutes);
            }
            if ($request->filled('min_withdrawal_amount')) {
                Setting::set('min_withdrawal_amount', $request->min_withdrawal_amount);
            }
            if ($request->filled('max_withdrawal_amount')) {
                Setting::set('max_withdrawal_amount', $request->max_withdrawal_amount);
            }
            if ($request->has('kyc_required')) {
                // For select dropdowns, check if value exists
                Setting::set('kyc_required', $request->kyc_required === '1' || $request->kyc_required === 1 ? '1' : '0');
            }
            if ($request->has('maintenance_mode')) {
                // For select dropdowns, check if value exists
                Setting::set('maintenance_mode', $request->maintenance_mode === '1' || $request->maintenance_mode === 1 ? '1' : '0');
            }
            if ($request->filled('maintenance_message')) {
                Setting::set('maintenance_message', $request->maintenance_message);
            }
            
            // Save slab commission percentages
            if ($request->filled('slab_commission_bronze')) {
                Setting::set('slab_commission_bronze', $request->slab_commission_bronze);
            }
            if ($request->filled('slab_commission_silver')) {
                Setting::set('slab_commission_silver', $request->slab_commission_silver);
            }
            if ($request->filled('slab_commission_gold')) {
                Setting::set('slab_commission_gold', $request->slab_commission_gold);
            }
            if ($request->filled('slab_commission_diamond')) {
                Setting::set('slab_commission_diamond', $request->slab_commission_diamond);
            }
            
            // Save property-type-based commissions
            if ($request->has('property_type_commissions')) {
                $propertyTypeCommissions = $request->input('property_type_commissions', []);
                // Clean up empty values - only keep non-empty commission values
                $cleanedCommissions = [];
                foreach ($propertyTypeCommissions as $propertyType => $slabCommissions) {
                    if (is_array($slabCommissions)) {
                        $cleanedSlabCommissions = [];
                        foreach ($slabCommissions as $slabName => $commission) {
                            // Only save if commission value is provided and not empty
                            if ($commission !== null && $commission !== '') {
                                $cleanedSlabCommissions[$slabName] = (float)$commission;
                            }
                        }
                        if (!empty($cleanedSlabCommissions)) {
                            $cleanedCommissions[$propertyType] = $cleanedSlabCommissions;
                        }
                    }
                }
                // Save as JSON
                Setting::set('property_type_commissions', json_encode($cleanedCommissions));
            }
            
            // Save app update settings - always save if key exists in request
            if ($request->exists('android_build_number')) {
                $buildNumber = $request->input('android_build_number');
                // Ensure we have a value, default to '1' if empty
                $buildNumber = $buildNumber !== null && $buildNumber !== '' ? (string)$buildNumber : '1';
                Setting::set('android_build_number', $buildNumber);
                \Log::info('Saved android_build_number', [
                    'received' => $request->input('android_build_number'),
                    'saved' => $buildNumber,
                    'verified' => Setting::get('android_build_number')
                ]);
            }
            if ($request->exists('android_store_url')) {
                Setting::set('android_store_url', $request->input('android_store_url', ''));
            }
            if ($request->exists('ios_build_number')) {
                $buildNumber = $request->input('ios_build_number');
                // Ensure we have a value, default to '1' if empty
                $buildNumber = $buildNumber !== null && $buildNumber !== '' ? (string)$buildNumber : '1';
                Setting::set('ios_build_number', $buildNumber);
                \Log::info('Saved ios_build_number', [
                    'received' => $request->input('ios_build_number'),
                    'saved' => $buildNumber,
                    'verified' => Setting::get('ios_build_number')
                ]);
            }
            if ($request->exists('ios_store_url')) {
                Setting::set('ios_store_url', $request->input('ios_store_url', ''));
            }
            if ($request->exists('force_app_update')) {
                // For checkboxes, check if value is '1' or checkbox is checked
                Setting::set('force_app_update', ($request->force_app_update === '1' || $request->force_app_update === 1) ? '1' : '0');
            }
            
            return redirect()->route('admin.settings')
                ->with('success', '✅ Settings updated successfully! Commission structure will apply to all future bookings.');
        } catch (\Exception $e) {
            return redirect()->route('admin.settings')
                ->with('error', '❌ Error updating settings: ' . $e->getMessage());
        }
    }

    public function resetData(Request $request)
    {
        // Validate password
        $request->validate([
            'password' => 'required|string',
        ]);
        
        // Check password
        if ($request->password !== '887563') {
            return redirect()->route('admin.settings')
                ->with('error', '❌ Invalid password. Reset operation cancelled.');
        }
        
        try {
            DB::beginTransaction();

            // Get admin IDs first
            $adminIds = DB::table('users')->where('user_type', 'admin')->pluck('id')->toArray();
            
            if (empty($adminIds)) {
                throw new \Exception('No admin user found. Cannot reset data.');
            }
            
            // Preserve SMTP/mail settings before deleting settings
            $smtpSettings = [];
            $smtpKeys = ['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 
                        'mail_from_address', 'mail_from_name', 'mail_mailer', 'smtp_host', 'smtp_port', 
                        'smtp_username', 'smtp_password', 'smtp_encryption'];
            
            foreach ($smtpKeys as $key) {
                $setting = DB::table('settings')->where('key', $key)->first();
                if ($setting) {
                    $smtpSettings[$key] = $setting->value;
                }
            }

            // Delete in order to respect foreign key constraints
            
            // 1. Delete all transactions (deposits, withdrawals, commissions, bonuses)
            // This includes: Pending Deposits, Pending Withdrawals, and all transaction history
            DB::table('transactions')->delete();
            
            // 2. Delete all sales/bookings
            DB::table('sales')->delete();
            
            // 3. Delete all payment requests (booking and withdrawal requests)
            DB::table('payment_requests')->delete();
            
            // 4. Delete slab upgrade history
            if (DB::getSchemaBuilder()->hasTable('slab_upgrades')) {
                DB::table('slab_upgrades')->delete();
            }
            
            // 5. Delete KYC documents from database
            DB::table('kyc_documents')->delete();
            
            // 5a. Delete KYC files from storage (PNG, JPG images)
            try {
                $kycPath = 'public/kyc';
                if (Storage::disk('public')->exists('kyc')) {
                    Storage::disk('public')->deleteDirectory('kyc');
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to delete KYC files: ' . $e->getMessage());
            }
            
            // 6. Reset ALL wallet balances to 0 (including admin wallets)
            // This clears: balance, total_earned, total_withdrawn, total_deposited
         
            
            // 6a. Delete user wallets (keep admin wallets but with zero balance)
            DB::table('wallets')->whereNotIn('user_id', $adminIds)->delete();


            DB::table('wallets')->update([
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_deposited' => 0,
                'main_balance' => 0,
                'withdrawable_balance' => 0,
            ]);
            
            // 7. Delete OTP verifications
            if (DB::getSchemaBuilder()->hasTable('otp_verifications')) {
                DB::table('otp_verifications')->delete();
            }
            
            // 8. Delete contact inquiries
            if (DB::getSchemaBuilder()->hasTable('contact_inquiries')) {
                DB::table('contact_inquiries')->delete();
            }
            
            // 9. Delete error logs
            if (DB::getSchemaBuilder()->hasTable('error_logs')) {
                DB::table('error_logs')->delete();
            }
            
            // 10. Delete personal access tokens (except admin)
            DB::table('personal_access_tokens')->whereNotIn('tokenable_id', $adminIds)->delete();
            
            // 11. Delete sessions
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->delete();
            }
            
            // 12. Delete referrals (if separate table exists)
            if (DB::getSchemaBuilder()->hasTable('referrals')) {
                DB::table('referrals')->delete();
            }
            
            // 13. Delete plots first (due to foreign key constraints)
            DB::table('plots')->delete();
            
            // 14. Delete projects
            DB::table('projects')->delete();
            
            // 15. Delete payment methods
            DB::table('payment_methods')->delete();
            
            // 16. Delete property type slabs (pivot table)
            if (DB::getSchemaBuilder()->hasTable('property_type_slab')) {
                DB::table('property_type_slab')->delete();
            }
            
            // 16a. Delete user slabs (pivot table)
            if (DB::getSchemaBuilder()->hasTable('user_slabs')) {
                DB::table('user_slabs')->delete();
            }
            
            // 17. Delete property types
            DB::table('property_types')->delete();
            
            // 18. Delete measurement units
            DB::table('measurement_units')->delete();
            
            // 19. Delete slabs
            DB::table('slabs')->delete();
            
            // 20. Delete all settings (SMTP settings already preserved above)
            DB::table('settings')->delete();
            
            // 22. Restore SMTP settings
            foreach ($smtpSettings as $key => $value) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // 23. Delete all users except admin (including soft deleted users)
            // Permanently delete all non-admin users (including soft deleted ones)
            // Using DB facade bypasses soft deletes, so this will delete everything
            DB::table('users')->where('user_type', '!=', 'admin')->delete();

       
            
            // 24. Clear cache
            // Clear Laravel application cache (including dashboard stats cache)
            Cache::flush();
            
            // Also clear database cache tables if they exist
            if (DB::getSchemaBuilder()->hasTable('cache')) {
                DB::table('cache')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('cache_locks')) {
                DB::table('cache_locks')->delete();
            }
            
            // 16. Clear job queue
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                DB::table('jobs')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                DB::table('failed_jobs')->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('job_batches')) {
                DB::table('job_batches')->delete();
            }
            
            // NOTE: Only Admin Users and SMTP Settings are PRESERVED
            // Everything else is DELETED: Slabs, Property Types, Measurement Units, Projects, Plots, Payment Methods, All Settings (except SMTP)
            
            // Reset auto-increment counters for SQLite
            if (DB::getDriverName() === 'sqlite') {
                $tables = ['users', 'sales', 'transactions', 'wallets', 'payment_requests', 'payment_methods', 'referrals', 'kyc_documents', 'personal_access_tokens'];
                $placeholders = implode(',', array_fill(0, count($tables), '?'));
                DB::statement("DELETE FROM sqlite_sequence WHERE name IN ($placeholders)", $tables);
            }

            DB::commit();
        
        $preservedSmtp = count($smtpSettings) > 0 ? count($smtpSettings) . ' SMTP settings' : 'SMTP settings';
        
        return redirect()->route('admin.settings')
                ->with('success', '✅ ALL DATA DELETED! Deleted: Users (except admin), Projects, Plots, Slabs, Property Types, Measurement Units, All Settings (except SMTP), All Transactions, Sales, Payment Requests, Payment Methods, User Wallets, All Wallet Balances (reset to 0), KYC Documents, Slab Upgrade History, Contact Inquiries, OTP Records, Error Logs, Sessions, Tokens. Preserved: Admin Users (with wallets reset to 0), ' . $preservedSmtp . '.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Reset data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('admin.settings')
                ->with('error', '❌ Error resetting data: ' . $e->getMessage());
        }
    }

    public function addSliderImage(Request $request)
    {
        try {
            // Log request details for debugging
            \Log::info('Slider image upload request', [
                'has_file' => $request->hasFile('image'),
                'file_size' => $request->hasFile('image') ? $request->file('image')->getSize() : null,
                'file_mime' => $request->hasFile('image') ? $request->file('image')->getMimeType() : null,
            ]);

            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            ]);

            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No image file provided'
                ], 422);
            }

            // Store the image in storage/app/public/sliders directory (consistent with projects)
            $image = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // Store in storage/app/public/sliders directory
            $imagePath = $image->storeAs('sliders', $filename, 'public');
            
            if (!$imagePath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store image file'
                ], 500);
            }

            // Store relative path (/storage/sliders/...) for consistency with projects
            $relativePath = '/storage/sliders/' . $filename;

            // Get existing slider images
            $sliderImages = json_decode(Setting::get('home_slider_images', '[]'), true) ?? [];
            
            // Convert existing URLs to relative paths if needed
            $sliderImages = array_map(function($img) {
                // If it's a full URL, extract just the path
                if (filter_var($img, FILTER_VALIDATE_URL)) {
                    $parsed = parse_url($img);
                    return $parsed['path'] ?? $img;
                }
                // If it's already a relative path, keep it
                return $img;
            }, $sliderImages);
            
            // Add new image path to the array
            $sliderImages[] = $relativePath;

            // Save updated array
            Setting::set('home_slider_images', json_encode($sliderImages));

            \Log::info('Slider image uploaded successfully', [
                'image_path' => $imagePath,
                'relative_path' => $relativePath,
                'total_images' => count($sliderImages)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Slider image added successfully',
                'image_url' => url($relativePath), // Return full URL for response only
                'relative_path' => $relativePath
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Slider image validation failed', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()['image'] ?? ['Invalid image'])
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Slider image upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteSliderImage(Request $request)
    {
        try {
            $request->validate([
                'index' => 'required|integer|min:0',
            ]);

            // Get existing slider images
            $sliderImages = json_decode(Setting::get('home_slider_images', '[]'), true) ?? [];
            
            $index = $request->index;
            
            if (!isset($sliderImages[$index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found'
                ], 404);
            }

            // Get the image path (could be URL or relative path)
            $imagePath = $sliderImages[$index];
            
            // Extract filename from path or URL
            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Full URL - extract path
                $parsedUrl = parse_url($imagePath);
                $path = $parsedUrl['path'] ?? '';
                $filename = basename($path);
            } else {
                // Relative path - extract filename directly
                $filename = basename($imagePath);
            }
            
            // Delete the file from storage/app/public/sliders directory
            $filePath = storage_path('app/public/sliders/' . $filename);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Also check old public/sliders location for backward compatibility
            $oldFilePath = public_path('sliders/' . $filename);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // Remove from array
            unset($sliderImages[$index]);
            
            // Re-index array to maintain sequential indices
            $sliderImages = array_values($sliderImages);

            // Save updated array
            Setting::set('home_slider_images', json_encode($sliderImages));

            return response()->json([
                'success' => true,
                'message' => 'Slider image deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveSliderImages(Request $request)
    {
        try {
            \Log::info('Save slider images request', [
                'has_files' => $request->hasFile('images'),
                'files_count' => $request->hasFile('images') ? count($request->file('images')) : 0,
                'keep_existing' => $request->input('keep_existing', [])
            ]);

            // Get existing images to keep
            $keepExisting = $request->input('keep_existing', []);
            if (!is_array($keepExisting)) {
                $keepExisting = [];
            }

            $finalImages = [];

            // Add existing images that should be kept (convert to relative paths)
            foreach ($keepExisting as $existingUrl) {
                if (!empty($existingUrl)) {
                    // Convert full URL to relative path if needed
                    if (filter_var($existingUrl, FILTER_VALIDATE_URL)) {
                        $parsed = parse_url($existingUrl);
                        $path = $parsed['path'] ?? '';
                        if (!empty($path)) {
                            $finalImages[] = $path;
                        }
                    } else {
                        // Already a relative path
                        $finalImages[] = $existingUrl;
                    }
                }
            }

            // Handle new image uploads
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                
                // Ensure it's an array
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        // Validate each file
                        $validator = \Validator::make(['image' => $file], [
                            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                        ]);

                        if ($validator->fails()) {
                            \Log::warning('Image validation failed', [
                                'file' => $file->getClientOriginalName(),
                                'errors' => $validator->errors()
                            ]);
                            continue; // Skip invalid files
                        }

                        // Store the image in storage/app/public/sliders directory (consistent with projects)
                        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $imagePath = $file->storeAs('sliders', $filename, 'public');
                        
                        if ($imagePath) {
                            // Store relative path (/storage/sliders/...) for consistency with projects
                            $relativePath = '/storage/sliders/' . $filename;
                            $finalImages[] = $relativePath;
                            
                            \Log::info('Image uploaded', [
                                'path' => $imagePath,
                                'relative_path' => $relativePath
                            ]);
                        }
                    }
                }
            }

            // Save the final list
            Setting::set('home_slider_images', json_encode($finalImages));

            \Log::info('Slider images saved', [
                'total_images' => count($finalImages)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Slider images saved successfully',
                'total_images' => count($finalImages)
            ]);
        } catch (\Exception $e) {
            \Log::error('Save slider images error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up slider image paths - convert full URLs to relative paths
     * This ensures images work with any server URL (local or production)
     */
    private function cleanupSliderImagePaths()
    {
        try {
            $sliderImages = json_decode(Setting::get('home_slider_images', '[]'), true) ?? [];
            $needsUpdate = false;
            $cleanedImages = [];

            foreach ($sliderImages as $imagePath) {
                if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                    // Full URL - extract relative path
                    $parsedUrl = parse_url($imagePath);
                    $path = $parsedUrl['path'] ?? '';
                    if (!empty($path)) {
                        $cleanedImages[] = $path;
                        $needsUpdate = true;
                    }
                } else {
                    // Already a relative path
                    $cleanedImages[] = $imagePath;
                }
            }

            // Update if any URLs were converted
            if ($needsUpdate) {
                Setting::set('home_slider_images', json_encode($cleanedImages));
                \Log::info('Cleaned up slider image paths', [
                    'converted_count' => count($sliderImages) - count($cleanedImages),
                    'total_images' => count($cleanedImages)
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error cleaning up slider image paths: ' . $e->getMessage());
        }
    }

    /**
     * Force logout all users by deleting all tokens
     */
    public function forceLogoutAll(Request $request)
    {
        try {
            // Delete all personal access tokens (Sanctum tokens)
            $deletedCount = PersonalAccessToken::query()->delete();
            
            \Log::info('Force logout all users', [
                'deleted_tokens' => $deletedCount,
                'admin_id' => auth()->id(),
            ]);

            return redirect()->route('admin.settings')
                ->with('success', "Successfully logged out all users. {$deletedCount} tokens deleted.");
        } catch (\Exception $e) {
            \Log::error('Error force logging out all users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.settings')
                ->with('error', 'Failed to logout all users: ' . $e->getMessage());
        }
    }
}
