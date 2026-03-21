<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class KycController extends Controller
{
    /**
     * Get authenticated user's KYC status
     */
    public function getKycStatus(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $kycDocument = KycDocument::where('user_id', $user->id)->first();

            if (!$kycDocument) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'is_verified' => false,
                        'status' => 'not_submitted',
                        'rejection_reason' => '',
                        'pan_image_path' => '',
                        'aadhaar_front_image_path' => '',
                        'aadhaar_back_image_path' => '',
                    ]
                ]);
            }

            // Convert image paths to full URLs - ALWAYS use shrihariomgroup.com/superadmin format
            $convertImagePath = function($imagePath) {
                if (empty($imagePath)) return $imagePath;
                
                // If already absolute URL, convert to shrihariomgroup.com/superadmin format
                if (strpos($imagePath, 'http') === 0) {
                    if (strpos($imagePath, 'shrihariomgroup.com') !== false) {
                        // Extract file path
                        $filePath = '';
                        if (strpos($imagePath, '/public/storage/') !== false) {
                            $parts = explode('/public/storage/', $imagePath, 2);
                            $filePath = $parts[1] ?? '';
                        } elseif (strpos($imagePath, '/storage/') !== false) {
                            $parts = explode('/storage/', $imagePath, 2);
                            $filePath = $parts[1] ?? '';
                        }
                        return !empty($filePath) ? 'https://shrihariomgroup.com/superadmin/storage/app/public/' . $filePath : $imagePath;
                    }
                    return $imagePath;
                }
                
                // If relative path (starts with /storage/), convert to full URL
                if (strpos($imagePath, '/storage/') === 0) {
                    return 'https://shrihariomgroup.com/superadmin/storage/app/public' . substr($imagePath, 8);
                }
                
                return $imagePath;
            };
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_verified' => ($kycDocument->status === 'verified' || $kycDocument->status === 'approved'),
                    'status' => $kycDocument->status ?? 'not_submitted',
                    'rejection_reason' => $kycDocument->rejection_reason ?? '',
                    'pan_image_path' => $convertImagePath($kycDocument->pan_image_path ?? ''),
                    'aadhaar_front_image_path' => $convertImagePath($kycDocument->aadhaar_front_image_path ?? ''),
                    'aadhaar_back_image_path' => $convertImagePath($kycDocument->aadhaar_back_image_path ?? ''),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload KYC document image
     */
    public function uploadKycDocument(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:pan,aadhaar_front,aadhaar_back',
                'image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->input('type');
            $image = $request->file('image');
            
            // Generate unique filename
            $filename = 'kyc_' . $user->id . '_' . $type . '_' . time() . '.' . $image->getClientOriginalExtension();
            
            // Store image in storage/app/public/kyc
            $path = $image->storeAs('kyc', $filename, 'public');
            
            // Get or create KYC document
            $kycDocument = KycDocument::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => 'pending',
                    'pan_number' => '',
                    'aadhaar_number' => '',
                ]
            );

            // Update the appropriate image path - store relative path
            // Storage::url() returns /storage/kyc/filename.jpg which is correct
            $imagePath = Storage::url($path);
            
            switch ($type) {
                case 'pan':
                    $kycDocument->pan_image_path = $imagePath;
                    break;
                case 'aadhaar_front':
                    $kycDocument->aadhaar_front_image_path = $imagePath;
                    break;
                case 'aadhaar_back':
                    $kycDocument->aadhaar_back_image_path = $imagePath;
                    break;
            }

            // Reset status to pending if documents are being updated
            if ($kycDocument->status === 'rejected') {
                $kycDocument->status = 'pending';
                $kycDocument->rejection_reason = null;
            }

            $kycDocument->save();

            // Convert to full URL - ALWAYS use shrihariomgroup.com/superadmin format
            $fullImageUrl = 'https://shrihariomgroup.com/superadmin/storage/app/public' . substr($imagePath, 8);
            
            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'image_path' => $fullImageUrl,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit KYC documents for verification
     */
    public function submitKyc(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $kycDocument = KycDocument::where('user_id', $user->id)->first();

            if (!$kycDocument) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload all required documents first'
                ], 400);
            }

            // Check if all documents are uploaded
            if (empty($kycDocument->pan_image_path) || 
                empty($kycDocument->aadhaar_front_image_path) || 
                empty($kycDocument->aadhaar_back_image_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload all required documents (PAN, Aadhaar Front, Aadhaar Back)'
                ], 400);
            }

            // Update status to pending
            $kycDocument->status = 'pending';
            $kycDocument->save();

            return response()->json([
                'success' => true,
                'message' => 'KYC documents submitted for verification'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit KYC: ' . $e->getMessage()
            ], 500);
        }
    }
}

