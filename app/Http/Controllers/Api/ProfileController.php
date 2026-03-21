<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get authenticated user's profile
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get slab information directly from database to ensure accuracy
            $slabName = 'Slab1';
            $slabId = null;
            $slabColor = '#9333EA';
            
            // Get user's slab_id directly from database
            $userSlabId = \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->value('slab_id');
            
            if ($userSlabId) {
                // Fetch slab directly from database
                $slab = \App\Models\Slab::find($userSlabId);
                if ($slab) {
                    $slabName = $slab->name;
                    $slabId = $slab->id;
                    $slabColor = $slab->color_code ?? '#9333EA';
                } else {
                    // Slab ID exists but slab not found, try to get default slab
                    $defaultSlab = \App\Models\Slab::where('name', 'Slab1')
                        ->orWhere('sort_order', 0)
                        ->orderBy('sort_order')
                        ->first();
                    if ($defaultSlab) {
                        $slabName = $defaultSlab->name;
                        $slabId = $defaultSlab->id;
                        $slabColor = $defaultSlab->color_code ?? '#9333EA';
                    }
                }
            } else {
                // No slab_id set, get default slab
                $defaultSlab = \App\Models\Slab::where('name', 'Slab1')
                    ->orWhere('sort_order', 0)
                    ->orderBy('sort_order')
                    ->first();
                if ($defaultSlab) {
                    $slabName = $defaultSlab->name;
                    $slabId = $defaultSlab->id;
                    $slabColor = $defaultSlab->color_code ?? '#9333EA';
                }
            }
            
            // Get sponsor information
            $sponsorName = null;
            if ($user->referred_by_user_id) {
                $sponsor = \App\Models\User::find($user->referred_by_user_id);
                if ($sponsor) {
                    $sponsorName = $sponsor->name;
                }
            }
            
            \Log::info('Profile API - Slab info', [
                'user_id' => $user->id,
                'user_slab_id' => $userSlabId,
                'slab_name' => $slabName,
                'slab_id' => $slabId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'referral_code' => $user->referral_code,
                    'referred_by_code' => $user->referred_by_code,
                    'sponsor_name' => $sponsorName,
                    'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
                    'address' => $user->address ?? '',
                    'city' => $user->city ?? '',
                    'state' => $user->state ?? '',
                    'pincode' => $user->pincode ?? '',
                    'slab' => $slabName,
                    'slab_id' => $slabId,
                    'slab_color' => $slabColor,
                    'broker_id' => $user->broker_id ?? '',
                    'total_referrals' => $user->referrals()->count(), // Direct referrals only (level 1)
                    'total_downline_count' => self::calculateTotalDownlineCount($user->id), // All levels
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update authenticated user's profile
     */
    public function updateProfile(Request $request)
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
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:10',
                'current_password' => 'required_with:password|string',
                'password' => 'sometimes|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if password change is requested
            if ($request->filled('password')) {
                // Verify current password
                if (!$request->filled('current_password') || !\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect',
                        'errors' => ['current_password' => ['Current password is incorrect']]
                    ], 422);
                }

                // Update password
                $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
                $user->save();
            }

            // Update user profile
            $user->update($request->only([
                'name', 'email', 'address', 'city', 'state', 'pincode'
            ]));

            // Get slab information directly from database to ensure accuracy
            $slabName = 'Slab1';
            $slabId = null;
            $slabColor = '#9333EA';
            
            // Get user's slab_id directly from database
            $userSlabId = \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->value('slab_id');
            
            if ($userSlabId) {
                // Fetch slab directly from database
                $slab = \App\Models\Slab::find($userSlabId);
                if ($slab) {
                    $slabName = $slab->name;
                    $slabId = $slab->id;
                    $slabColor = $slab->color_code ?? '#9333EA';
                } else {
                    // Slab ID exists but slab not found, try to get default slab
                    $defaultSlab = \App\Models\Slab::where('name', 'Slab1')
                        ->orWhere('sort_order', 0)
                        ->orderBy('sort_order')
                        ->first();
                    if ($defaultSlab) {
                        $slabName = $defaultSlab->name;
                        $slabId = $defaultSlab->id;
                        $slabColor = $defaultSlab->color_code ?? '#9333EA';
                    }
                }
            } else {
                // No slab_id set, get default slab
                $defaultSlab = \App\Models\Slab::where('name', 'Slab1')
                    ->orWhere('sort_order', 0)
                    ->orderBy('sort_order')
                    ->first();
                if ($defaultSlab) {
                    $slabName = $defaultSlab->name;
                    $slabId = $defaultSlab->id;
                    $slabColor = $defaultSlab->color_code ?? '#9333EA';
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'referral_code' => $user->referral_code,
                    'address' => $user->address ?? '',
                    'city' => $user->city ?? '',
                    'state' => $user->state ?? '',
                    'pincode' => $user->pincode ?? '',
                    'slab' => $slabName,
                    'slab_id' => $slabId,
                    'slab_color' => $slabColor,
                    'broker_id' => $user->broker_id ?? '',
                    'total_referrals' => $user->referrals()->count(), // Direct referrals only (level 1)
                    'total_downline_count' => self::calculateTotalDownlineCount($user->id), // All levels
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
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
                'current_password' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => ['current_password' => ['Current password is incorrect']]
                ], 422);
            }

            // Update password
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate total downline count recursively (all levels)
     * 
     * @param int $userId
     * @return int
     */
    private static function calculateTotalDownlineCount(int $userId): int
    {
        $count = 0;
        $directReferrals = \App\Models\User::where('referred_by_user_id', $userId)
            ->where('user_type', 'broker')
            ->get();
        
        foreach ($directReferrals as $referral) {
            $count++; // Count this referral
            $count += self::calculateTotalDownlineCount($referral->id); // Recursively count their downline
        }
        
        return $count;
    }
}

