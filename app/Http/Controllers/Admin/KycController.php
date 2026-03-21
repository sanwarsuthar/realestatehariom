<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KycController extends Controller
{
    public function index(Request $request)
    {
        $query = KycDocument::with(['user', 'verifiedBy'])
            ->select('kyc_documents.*', 'users.name as user_name', 'users.email', 'users.phone_number', 'users.broker_id')
            ->join('users', 'kyc_documents.user_id', '=', 'users.id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('kyc_documents.status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%")
                  ->orWhere('users.phone_number', 'like', "%{$search}%")
                  ->orWhere('users.broker_id', 'like', "%{$search}%");
            });
        }

        $kycDocuments = $query->orderBy('kyc_documents.created_at', 'desc')->paginate(20)->appends(request()->query());

        // Get filter options
        $statusOptions = ['pending', 'verified', 'approved', 'rejected'];

        return view('admin.kyc.index', compact('kycDocuments', 'statusOptions'));
    }

    public function pending()
    {
        $kycDocuments = KycDocument::with(['user'])
            ->select('kyc_documents.*', 'users.name as user_name', 'users.email', 'users.phone_number', 'users.broker_id')
            ->join('users', 'kyc_documents.user_id', '=', 'users.id')
            ->where('kyc_documents.status', 'pending')
            ->orderBy('kyc_documents.created_at', 'desc')
            ->paginate(20);

        return view('admin.kyc.pending', compact('kycDocuments'));
    }

    public function approve(Request $request, $id)
    {
        try {
        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        $kycDocument = KycDocument::findOrFail($id);
            
            DB::beginTransaction();
        
        $kycDocument->update([
                'status' => 'verified', // Using 'verified' to match database CHECK constraint
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        // Update user KYC status
        User::where('id', $kycDocument->user_id)->update(['kyc_verified' => true]);

            DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'KYC document approved successfully'
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to approve KYC: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve KYC document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $kycDocument = KycDocument::findOrFail($id);
        
        DB::beginTransaction();
        
        try {
            $kycDocument->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);

            // Update user KYC status to false
            User::where('id', $kycDocument->user_id)->update(['kyc_verified' => false]);
            
            // Clear image paths so user can re-upload
            $kycDocument->update([
                'pan_image_path' => null,
                'aadhaar_front_image_path' => null,
                'aadhaar_back_image_path' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'KYC document rejected successfully. User can now re-upload documents.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to reject KYC: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject KYC document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $kycDocument = KycDocument::with(['user', 'verifiedBy'])
            ->findOrFail($id);

        // Helper function to convert image paths to correct format
        // Format: https://superadmin.shrihariomgroup.com/storage/app/public/kyc/... (production)
        // Format: http://localhost:8000/storage/app/public/kyc/... (local)
        $convertImagePath = function($imagePath) {
            if (empty($imagePath)) {
                return null;
            }
            
            // Extract the file path from various URL formats
            $filePath = null;
            
            if (str_starts_with($imagePath, 'http')) {
                // Handle format: https://superadmin.shrihariomgroup.com/storage/app/public/kyc/...
                if (strpos($imagePath, '/storage/app/public/') !== false) {
                    $parts = explode('/storage/app/public/', $imagePath, 2);
                    $filePath = $parts[1] ?? null;
                }
                // Handle format: https://superadmin.shrihariomgroup.com/public/storage/kyc/...
                elseif (strpos($imagePath, '/public/storage/') !== false) {
                    $parts = explode('/public/storage/', $imagePath, 2);
                    $filePath = $parts[1] ?? null;
                }
                // Handle format: https://superadmin.shrihariomgroup.com/storage/kyc/...
                elseif (strpos($imagePath, '/storage/') !== false) {
                    $parts = explode('/storage/', $imagePath, 2);
                    $filePath = $parts[1] ?? null;
                }
            } elseif (str_starts_with($imagePath, '/storage/')) {
                // Relative path format: /storage/kyc/...
                $filePath = substr($imagePath, 9); // Remove '/storage/'
            }
            
            // Generate URL in the correct format
            if ($filePath) {
                $baseUrl = config('app.url');
                // For production, use the specific format
                if (strpos($baseUrl, 'shrihariomgroup.com') !== false || strpos($baseUrl, 'superadmin') !== false) {
                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                }
                // For local development
                return rtrim($baseUrl, '/') . '/storage/app/public/' . $filePath;
            }
            
            // Fallback: if we can't extract, try to use as-is or generate from relative path
            if (str_starts_with($imagePath, '/storage/')) {
                $filePath = substr($imagePath, 9);
                $baseUrl = config('app.url');
                if (strpos($baseUrl, 'shrihariomgroup.com') !== false || strpos($baseUrl, 'superadmin') !== false) {
                    return 'https://superadmin.shrihariomgroup.com/storage/app/public/' . $filePath;
                }
                return rtrim($baseUrl, '/') . '/storage/app/public/' . $filePath;
            }
            
            return $imagePath;
        };

        // Get full image URLs using the converter
        $panImagePath = $convertImagePath($kycDocument->pan_image_path);
        $aadhaarFrontPath = $convertImagePath($kycDocument->aadhaar_front_image_path);
        $aadhaarBackPath = $convertImagePath($kycDocument->aadhaar_back_image_path);

        return response()->json([
            'success' => true,
            'kyc' => [
                'id' => $kycDocument->id,
                'user_name' => $kycDocument->user->name ?? 'N/A',
                'email' => $kycDocument->user->email ?? 'N/A',
                'phone_number' => $kycDocument->user->phone_number ?? 'N/A',
                'status' => $kycDocument->status,
                'pan_image_path' => $panImagePath,
                'aadhaar_front_image_path' => $aadhaarFrontPath,
                'aadhaar_back_image_path' => $aadhaarBackPath,
                'rejection_reason' => $kycDocument->rejection_reason,
                'created_at' => $kycDocument->created_at ? $kycDocument->created_at->format('d M Y, h:i A') : null,
                'verified_at' => $kycDocument->verified_at ? $kycDocument->verified_at->format('d M Y, h:i A') : null,
            ]
        ]);
    }
}
