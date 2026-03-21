<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users with filters
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('users')
                ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                ->leftJoin('users as referrer', 'users.referred_by_user_id', '=', 'referrer.id')
                ->where('users.user_type', 'broker')
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone_number',
                    'users.broker_id',
                    'users.referral_code',
                    'users.status',
                    'users.kyc_verified',
                    'users.total_business_volume',
                    'users.total_commission_earned',
                    'users.total_downline_count',
                    'users.created_at',
                    'slabs.name as slab_name',
                    'slabs.color_code as slab_color',
                    'referrer.name as referrer_name',
                    'referrer.broker_id as referrer_broker_id'
                ]);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('users.status', $request->status);
            }

            if ($request->has('slab_id') && $request->slab_id) {
                $query->where('users.slab_id', $request->slab_id);
            }

            if ($request->has('kyc_verified') && $request->kyc_verified !== '') {
                $query->where('users.kyc_verified', $request->kyc_verified);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.broker_id', 'like', "%{$search}%")
                      ->orWhere('users.phone_number', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->orderBy('users.created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function show($id)
    {
        try {
            $user = DB::table('users')
                ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                ->leftJoin('users as referrer', 'users.referred_by_user_id', '=', 'referrer.id')
                ->leftJoin('wallets', 'users.id', '=', 'wallets.user_id')
                ->leftJoin('kyc_documents', 'users.id', '=', 'kyc_documents.user_id')
                ->where('users.id', $id)
                ->where('users.user_type', 'broker')
                ->select([
                    'users.*',
                    'slabs.name as slab_name',
                    'slabs.color_code as slab_color',
                    'slabs.minimum_target',
                    'slabs.commission_ratio',
                    'slabs.bonus_percentage',
                    'referrer.name as referrer_name',
                    'referrer.broker_id as referrer_broker_id',
                    'wallets.balance',
                    'wallets.total_earned',
                    'wallets.total_withdrawn',
                    'wallets.total_deposited',
                    'kyc_documents.status as kyc_status',
                    'kyc_documents.pan_number',
                    'kyc_documents.aadhaar_number',
                    'kyc_documents.verified_at as kyc_verified_at'
                ])
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get referral tree (downline)
            $downline = DB::table('referrals')
                ->join('users', 'referrals.referred_id', '=', 'users.id')
                ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                ->where('referrals.referrer_id', $id)
                ->select([
                    'users.id',
                    'users.name',
                    'users.broker_id',
                    'users.phone_number',
                    'users.total_business_volume',
                    'users.total_commission_earned',
                    'users.created_at',
                    'slabs.name as slab_name',
                    'slabs.color_code as slab_color',
                    'referrals.level'
                ])
                ->orderBy('referrals.level')
                ->orderBy('users.created_at')
                ->get();

            // Get recent transactions
            $transactions = DB::table('transactions')
                ->where('user_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'User details retrieved successfully',
                'data' => [
                    'user' => $user,
                    'downline' => $downline,
                    'recent_transactions' => $transactions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = DB::table('users')->where('id', $id)->where('user_type', 'broker')->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            DB::table('users')->where('id', $id)->update([
                'status' => $request->status,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => [
                    'user_id' => $id,
                    'status' => $request->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
