<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Slab;
use App\Models\SlabUpgrade;
use App\Models\PropertyType;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('users.user_type', 'broker')
            ->whereNull('users.deleted_at')
            ->leftJoin('slabs', 'users.slab_id', '=', 'slabs.id')
            ->select('users.*', 'slabs.name as slab_name', 'slabs.color_code as slab_color');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('users.status', $request->status);
        }

        if ($request->filled('slab_id')) {
            $query->where('users.slab_id', $request->slab_id);
        }

        if ($request->filled('kyc_verified')) {
            $query->where('users.kyc_verified', $request->kyc_verified);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'like', '%' . $search . '%')
                  ->orWhere('users.email', 'like', '%' . $search . '%')
                  ->orWhere('users.phone_number', 'like', '%' . $search . '%')
                  ->orWhere('users.broker_id', 'like', '%' . $search . '%');
            });
        }

        $users = $query->orderBy('users.created_at', 'desc')->paginate(15)->appends(request()->query());
      
        // Load all slabs for each user and calculate referral income
        $users->getCollection()->transform(function ($user) {

           
            // Load all user slabs with property types
            $userSlabs = DB::table('user_slabs')
                ->join('slabs', 'user_slabs.slab_id', '=', 'slabs.id')
                ->join('property_types', 'user_slabs.property_type_id', '=', 'property_types.id')
                ->where('user_slabs.user_id', $user->id)
                ->select('slabs.name as slab_name', 'slabs.color_code as slab_color', 'property_types.name as property_type_name')
                ->get();
            
            $user->all_slabs = $userSlabs;
            
            // Calculate referral income (commissions earned from downline users' sales)
            // Step 1: Get all downline user IDs (children and children of children recursively)
            $downlineUserIds = $this->getDownlineUserIds($user);
            
            $referralIncome = 0;
            if (!empty($downlineUserIds)) {
                // Step 2: Get all sale IDs from downline users (children and their children)
                $downlineSaleIds = DB::table('sales')
                    ->whereIn('sold_by_user_id', $downlineUserIds)
                    ->where('status', 'confirmed')
                    ->pluck('id')
                    ->toArray();
                
                // Step 3: Get total referral income for this user only (commission THEY received from downline sales)
                if (!empty($downlineSaleIds)) {
                    $referralIncome = DB::table('referral_commissions')
                        ->where('parent_user_id', $user->id)
                        ->whereIn('sale_id', $downlineSaleIds)
                        ->sum('referral_commission_amount');
                }
            }
            
            $user->referral_income = round((float)$referralIncome, 2);
            
            return $user;
        });
        
        $slabs = Slab::where('is_active', true)->get();
        $statusOptions = ['active', 'inactive', 'suspended'];
        
        // Get all referral codes for autocomplete
        $referralCodes = User::where('user_type', '!=', 'admin')
            ->whereNotNull('referral_code')
            ->select('referral_code', 'name', 'broker_id')
            ->orderBy('referral_code')
            ->get();


       
        return view('admin.users.index', compact('users', 'slabs', 'statusOptions', 'referralCodes'));
    }

    public function show(User $user)
    {
        try {
            // Refresh user to ensure we have latest data
            $user->refresh();
            
            // Load wallet relationship first
            $user->load('wallet');
        
        // Ensure wallet exists, create if not
        if (!$user->wallet) {
            $userId = (int) $user->id; // Ensure it's an integer
            
            if (!$userId) {
                // User doesn't have an ID, something is wrong
                abort(500, 'User ID is missing');
            }
            
            try {
                // Check if wallet exists by querying directly
                $existingWallet = \App\Models\Wallet::where('user_id', $userId)->first();
                
                if (!$existingWallet) {
                    // Create wallet using DB facade to avoid any model issues
                    \Illuminate\Support\Facades\DB::table('wallets')->insert([
                        'user_id' => $userId,
                        'balance' => 0,
                        'total_earned' => 0,
                        'total_withdrawn' => 0,
                        'total_deposited' => 0,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                // Reload the wallet relationship
                $user->load('wallet');
            } catch (\Exception $e) {
                // Log the error but continue
                \Log::error('Failed to create wallet for user ' . $userId . ': ' . $e->getMessage());
                // Try to load wallet one more time
                $user->load('wallet');
            }
        }
        
        // Recalculate wallet balance from transactions if needed
        $wallet = $user->wallet;
        if ($wallet) {
            // Calculate actual balance from transactions
            $transactions = $user->transactions()->get();

           
            $calculatedBalance = 0;
            $totalEarned = 0;
            $totalWithdrawn = 0;
            $totalDeposited = 0;
            
            foreach ($transactions as $txn) {
                if (in_array($txn->type, ['deposit', 'commission', 'bonus'])) {
                    // Only count completed deposits
                    if ($txn->type === 'deposit' && $txn->status !== 'completed') {
                        continue;
                    }
                   
                    if ($txn->type === 'commission' && $txn->status == 'completed') {
                        $totalEarned += $txn->amount;
                        $calculatedBalance += $txn->amount;
                    }
                    if ($txn->type === 'deposit' && $txn->status === 'completed') {
                        $totalDeposited += $txn->amount;
                        $calculatedBalance += $txn->amount;
                    }
                } elseif (in_array($txn->type, ['withdrawal', 'booking'])) {
                    // Only count completed withdrawals - pending withdrawals should NOT affect balance
                    if ($txn->type === 'withdrawal' && $txn->status !== 'completed') {
                        continue;
                    }
                    
                    if ($txn->type === 'withdrawal' && $txn->status === 'completed') {
                        $totalWithdrawn += $txn->amount;
                        $calculatedBalance -= $txn->amount;
                    }
                }
            }
            
            // Update wallet if calculated balance differs significantly (more than 0.01)
            if (abs($wallet->balance - $calculatedBalance) > 0.01) {

               
                $wallet->update([
                    'balance' => $calculatedBalance,
                    'total_earned' => $totalEarned,
                    'total_withdrawn' => $totalWithdrawn,
                    'total_deposited' => $totalDeposited,
                ]);
                $user->refresh();
            }
            
            // Also update user's total_commission_earned from transactions
            $totalCommission = $user->transactions()
                ->where('type', 'commission')
                ->where('status', 'completed')
                ->sum('amount');
            
            if (abs(($user->total_commission_earned ?? 0) - $totalCommission) > 0.01) {
                $user->update(['total_commission_earned' => $totalCommission]);
                $user->refresh();
            }
        }
        
        // Load all relationships without limits for tabs
        $user->load([
            'slab', 
            'wallet', 
            'kycDocument', 
            'kycDocument.verifiedBy',
            'transactions' => function ($q) {
                $q->latest(); // Load all transactions
            }, 
            'sales' => function ($q) {
            $q->latest()->limit(25);
            },
            'referrals' => function ($q) {
                $q->select('users.*')->with('slab:id,name')->latest();
            },
            'referredBy:id,name,referral_code,referred_by_user_id',
            'slabUpgrades' => function ($q) {
                $q->with(['oldSlab', 'newSlab', 'sale'])->latest('upgraded_at');
            }
        ]);

        // Load all payment requests for this user (pending, approved, rejected, booked_by_other)
        $paymentRequests = \App\Models\PaymentRequest::where('user_id', $user->id)
            ->with(['plot.project', 'paymentMethod', 'processedBy'])
            ->latest()
            ->get();

        // Ensure transactions are loaded as a proper collection of model instances
        if ($user->transactions) {
            $transactions = $user->transactions->filter(function($txn) {
                return is_object($txn) && method_exists($txn, 'getKey');
            });
            $user->setRelation('transactions', $transactions);
        }
        
        // Ensure referrals are proper model instances
        if ($user->referrals) {
            $referrals = $user->referrals->filter(function($ref) {
                return is_object($ref) && method_exists($ref, 'getKey');
            });
            $user->setRelation('referrals', $referrals);
        }

        return view('admin.users.show', compact('user', 'paymentRequests'));
        } catch (\Exception $e) {
            \Log::error('Error in UserController@show: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error loading user details: ' . $e->getMessage());
        }
    }

    /**
     * Get downline users at a specific level
     */
    public function getDownlineByLevel(Request $request, User $user)
    {
        try {
            $level = (int) $request->input('level', 1);
            $level = max(1, min(15, $level)); // Clamp between 1 and 15

            // Function to get users at a specific level
            $getUsersAtLevel = function($userId, $targetLevel, $currentLevel = 1) use (&$getUsersAtLevel) {
                if ($currentLevel > $targetLevel) {
                    return collect([]);
                }

                if ($currentLevel == $targetLevel) {
                    // Get direct referrals at this level
                    return \App\Models\User::where('referred_by_user_id', $userId)
                        ->with('slab')
                        ->get();
                }

                // Get direct referrals and recursively get their downline
                $directReferrals = \App\Models\User::where('referred_by_user_id', $userId)->pluck('id');
                $usersAtLevel = collect([]);
                
                foreach ($directReferrals as $referralId) {
                    $usersAtLevel = $usersAtLevel->merge($getUsersAtLevel($referralId, $targetLevel, $currentLevel + 1));
                }
                
                return $usersAtLevel;
            };

            $downline = $getUsersAtLevel($user->id, $level);

            return response()->json([
                'success' => true,
                'data' => $downline->map(function($member) use ($level, $user) {
                    // Commission earned from this downline member (from referral_commissions table)
                    $commissionFromMember = (float) \App\Models\ReferralCommission::where('parent_user_id', $user->id)
                        ->where('child_user_id', $member->id)
                        ->sum('referral_commission_amount');

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'phone_number' => $member->phone_number,
                        'broker_id' => $member->broker_id ?? '—',
                        'referral_code' => $member->referral_code ?? '—',
                        'slab' => $member->slab ? $member->slab->name : 'Slab1',
                        'created_at' => $member->created_at ? $member->created_at->format('Y-m-d H:i:s') : null,
                        'level' => $level,
                        'commission_from_member' => round((float)$commissionFromMember, 2),
                    ];
                }),
                'level' => $level,
                'total_count' => $downline->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch downline: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended'
        ]);

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully.'
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Use query builder to bypass any attribute casting/mutators and avoid double hashing.
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make($request->new_password),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error changing password for user ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update password.',
            ], 500);
        }
    }

    public function slabUpgrades(Request $request)
    {
        $query = SlabUpgrade::with(['user', 'oldSlab', 'newSlab', 'sale.plot.project']);

        // Filter by user if provided
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by slab if provided
        if ($request->filled('slab_id')) {
            $query->where(function($q) use ($request) {
                $q->where('old_slab_id', $request->slab_id)
                  ->orWhere('new_slab_id', $request->slab_id);
            });
        }

        // Search by user name, email, or broker ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('broker_id', 'like', '%' . $search . '%');
            });
        }

        $upgrades = $query->latest('upgraded_at')->paginate(20);
        $slabs = Slab::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.users.slab-upgrades', compact('upgrades', 'slabs'));
    }

    public function destroy(User $user)
    {
        try {
            $user->delete(); // Soft delete

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's referral code (sponsor)
     */
    public function updateReferralCode(Request $request, User $user)
    {
        $request->validate([
            'new_referral_code' => 'required|string|exists:users,referral_code'
        ]);

        try {
            \DB::beginTransaction();

            // Find the new sponsor by referral code
            $newSponsor = User::where('referral_code', $request->new_referral_code)
                ->where('id', '!=', $user->id)
                ->first();

            if (!$newSponsor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code. User not found.'
                ], 404);
            }

            // Prevent circular reference - check if new sponsor is in user's downline
            $userDownline = $this->getEntireDownline($user->id);
            $downlineIds = $userDownline->pluck('id')->toArray();
            
            if ($newSponsor->referred_by_user_id == $user->id || in_array($newSponsor->id, $downlineIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign user as their own sponsor or create circular reference. The new sponsor cannot be in this user\'s downline.'
                ], 400);
            }
            
            // Prevent self-assignment
            if ($newSponsor->id == $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot assign user as their own sponsor.'
                ], 400);
            }

            // Get all downline users (entire tree)
            $downlineUsers = $this->getEntireDownline($user->id);
            $allUsersToUpdate = collect([$user])->merge($downlineUsers);

            // Store old sponsor ID for downline count update
            $oldSponsorId = $user->referred_by_user_id;

            // Update each user's referral information
            foreach ($allUsersToUpdate as $userToUpdate) {
                // Get the old referrer_id for this user before updating
                $oldReferrerId = $userToUpdate->referred_by_user_id;

                // Calculate correct level in new sponsor's structure
                $correctLevel = $this->calculateReferralLevel($userToUpdate->id, $newSponsor->id);

                // Update referred_by_user_id and referred_by_code
                $userToUpdate->update([
                    'referred_by_user_id' => $newSponsor->id,
                    'referred_by_code' => $newSponsor->referral_code,
                ]);

                // Delete old referral record if exists
                if ($oldReferrerId) {
                    \DB::table('referrals')
                        ->where('referrer_id', $oldReferrerId)
                        ->where('referred_id', $userToUpdate->id)
                        ->delete();
                }

                // Delete any existing referral record for this user with new sponsor (to avoid duplicates)
                \DB::table('referrals')
                    ->where('referrer_id', $newSponsor->id)
                    ->where('referred_id', $userToUpdate->id)
                    ->delete();

                // Create new referral record with correct level
                \DB::table('referrals')->insert([
                    'referrer_id' => $newSponsor->id,
                    'referred_id' => $userToUpdate->id,
                    'level' => $correctLevel, // Calculate correct level in new sponsor's structure
                    'commission_percentage' => 0.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update new sponsor's downline count
            $newSponsor->increment('total_downline_count', $allUsersToUpdate->count());

            // Update old sponsor's downline count if exists
            if ($oldSponsorId) {
                $oldSponsor = User::find($oldSponsorId);
                if ($oldSponsor) {
                    $oldSponsor->decrement('total_downline_count', $allUsersToUpdate->count());
                    // Ensure count doesn't go negative
                    if ($oldSponsor->total_downline_count < 0) {
                        $oldSponsor->update(['total_downline_count' => 0]);
                    }
                }
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Referral code updated successfully. User and entire downline (' . $allUsersToUpdate->count() . ' users) have been shifted to the new sponsor.'
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error updating referral code: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update referral code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:15|unique:users,phone_number',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'referral_code' => 'required|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find referrer by referral code
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if (!$referrer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code. User not found.'
                ], 404);
            }

            // Normalize phone number (remove spaces, dashes, etc.)
            $normalizedPhone = preg_replace('/[^0-9]/', '', $request->phone_number);
            if (strlen($normalizedPhone) < 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format.'
                ], 422);
            }

            // Get default slab (first initial slab from first property type)
            $defaultSlab = User::getDefaultSlab();
            if (!$defaultSlab) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active slabs found. Please create an active slab first.'
                ], 500);
            }

            // Generate broker ID and referral code: SHOB + 5 digits, sequential starting from 00001
            $lastUser = DB::table('users')
                ->where(function($query) {
                    $query->where('broker_id', 'like', 'SHOB%')
                          ->orWhere('referral_code', 'like', 'SHOB%');
                })
                ->orderBy('id', 'desc')
                ->first();
            
            $lastNumber = 0;
            if ($lastUser) {
                if ($lastUser->broker_id && strpos($lastUser->broker_id, 'SHOB') === 0) {
                    $codeStr = substr($lastUser->broker_id, 4);
                    $lastNumber = (int)$codeStr;
                }
                if ($lastNumber == 0 && $lastUser->referral_code && strpos($lastUser->referral_code, 'SHOB') === 0) {
                    $codeStr = substr($lastUser->referral_code, 4);
                    $lastNumber = (int)$codeStr;
                }
            }
            
            $nextNumber = $lastNumber + 1;
            $brokerId = 'SHOB' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            $referralCode = $brokerId;

            // Safety check: ensure uniqueness
            while (DB::table('users')->where('broker_id', $brokerId)->orWhere('referral_code', $referralCode)->exists()) {
                $nextNumber++;
                $brokerId = 'SHOB' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                $referralCode = $brokerId;
            }

            // Create user
            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $normalizedPhone,
                'password' => Hash::make($request->password),
                'broker_id' => $brokerId,
                'referral_code' => $referralCode,
                'referred_by_code' => $request->referral_code,
                'referred_by_user_id' => $referrer->id,
                'slab_id' => $defaultSlab->id,
                'user_type' => 'broker',
                'status' => 'active',
                'kyc_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create wallet for user
            DB::table('wallets')->insert([
                'user_id' => $userId,
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_deposited' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create referral relationship
            DB::table('referrals')->insert([
                'referrer_id' => $referrer->id,
                'referred_id' => $userId,
                'level' => 1,
                'commission_percentage' => 0.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update referrer's downline count
            DB::table('users')->where('id', $referrer->id)->increment('total_downline_count');

            // Assign all initial slabs to the new user (one per property type)
            $newUser = User::find($userId);
            if ($newUser) {
                $newUser->assignAllInitialSlabs();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user_id' => $userId,
                    'broker_id' => $brokerId,
                    'referral_code' => $referralCode,
                    'name' => $request->name,
                    'email' => $request->email,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdrawAdminAmount(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        if ($user->user_type !== 'admin') {
            return redirect()
                ->back()
                ->with('error', 'Withdrawal is allowed only for admin users.');
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return redirect()
                ->back()
                ->with('error', 'Wallet not found for this admin user.');
        }

        $amount = round((float) $request->amount, 2);
        $withdrawableBefore = (float) ($wallet->withdrawable_balance ?? 0);

        if ($amount > $withdrawableBefore) {
            return redirect()
                ->back()
                ->with('error', 'Insufficient withdrawable balance. Available: ₹' . number_format($withdrawableBefore, 2));
        }

        try {
            DB::transaction(function () use ($user, $wallet, $amount, $withdrawableBefore) {
                $withdrawableAfter = $withdrawableBefore - $amount;

                Transaction::create([
                    'user_id' => $user->id,
                    'transaction_id' => 'WTHADM' . strtoupper(Str::random(6)) . time(),
                    'type' => 'withdrawal',
                    'status' => 'completed',
                    'amount' => $amount,
                    'balance_before' => $withdrawableBefore,
                    'balance_after' => $withdrawableAfter,
                    'description' => 'Manual admin withdrawal from user details page',
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                    'metadata' => [
                        'source' => 'admin_user_details_page',
                        'executed_by' => auth()->id(),
                    ],
                ]);

                $wallet->decrement('withdrawable_balance', $amount);
                $wallet->decrement('main_balance', $amount);
                $wallet->increment('total_withdrawn', $amount);
            });

            return redirect()
                ->back()
                ->with('success', 'Amount withdrawn successfully.');
        } catch (\Throwable $e) {
            \Log::error('Admin manual withdrawal failed: ' . $e->getMessage(), [
                'admin_user_id' => $user->id,
                'amount' => $amount,
                'performed_by' => auth()->id(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to process withdrawal. Please try again.');
        }
    }

    /**
     * Get entire downline tree recursively
     */
    private function getEntireDownline($userId)
    {
        $downline = collect();
        $directReferrals = User::where('referred_by_user_id', $userId)->get();

        foreach ($directReferrals as $referral) {
            $downline->push($referral);
            $downline = $downline->merge($this->getEntireDownline($referral->id));
        }

        return $downline;
    }

    /**
     * Calculate the referral level of a user relative to a sponsor
     * Level 1 = direct referral, Level 2 = referral of referral, etc.
     */
    private function calculateReferralLevel($userId, $sponsorId): int
    {
        $level = 1;
        $currentUserId = $userId;
        $visited = [];
        
        // Traverse up the referral chain until we reach the sponsor
        while ($currentUserId && !isset($visited[$currentUserId])) {
            $visited[$currentUserId] = true;
            
            $user = User::find($currentUserId);
            if (!$user || !$user->referred_by_user_id) {
                break;
            }
            
            // If we've reached the sponsor, return the level
            if ($user->referred_by_user_id == $sponsorId) {
                return $level;
            }
            
            // Move up one level
            $level++;
            $currentUserId = $user->referred_by_user_id;
            
            // Safety check to prevent infinite loops
            if ($level > 100) {
                break;
            }
        }
        
        // If we couldn't find the sponsor in the chain, return level 1 (direct referral)
        return 1;
    }

    /**
     * Get all downline user IDs (children and their children recursively)
     * Used to get all users in the referral chain below a user
     * 
     * @param User $user The user whose downline needs to be found
     * @param array $visited Array to prevent infinite loops from circular references
     * @return array Array of user IDs
     */
    private function getDownlineUserIds(User $user, array $visited = []): array
    {
        // Prevent infinite loops from circular references
        if (in_array($user->id, $visited)) {
            return [];
        }
        $visited[] = $user->id;
        
        $ids = [];
        // Get all direct referrals in one query
        $directReferralIds = User::where('referred_by_user_id', $user->id)
            ->whereNull('deleted_at') // Exclude soft-deleted users
            ->pluck('id')
            ->toArray();
        
        foreach ($directReferralIds as $id) {
            $ids[] = $id;
            // Recursively get their downline (pass visited array to prevent loops)
            $downlineUser = User::find($id);
            if ($downlineUser) {
                $ids = array_merge($ids, $this->getDownlineUserIds($downlineUser, $visited));
            }
        }
        
        return array_unique($ids);
    }
}