<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * Get wallet data
     */
    public function getWallet(Request $request)
    {
        try {
            $user = $request->user();
            
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'main_balance' => 0,
                    'withdrawable_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'total_deposited' => 0,
                ]
            );

            $mainBalance = (float) ($wallet->main_balance ?? 0);
            $grossBalance = (float) $wallet->balance;
            $withdrawableBalance = (float) ($wallet->withdrawable_balance ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    // Three wallets: Main (projected), Gross (released from payments), Withdrawable (deal-done only)
                    'wallets' => [
                        'main' => [
                            'key' => 'main',
                            'label' => 'Main (Projected)',
                            'subtitle' => 'Commission from approved bookings (including future)',
                            'amount' => $mainBalance,
                            'currency' => 'INR',
                            'formatted' => '₹' . number_format($mainBalance, 2),
                            'is_withdrawable' => false,
                        ],
                        'gross' => [
                            'key' => 'gross',
                            'label' => 'Gross',
                            'subtitle' => 'Released commission against payments received (not withdrawable until deal is done)',
                            'amount' => $grossBalance,
                            'currency' => 'INR',
                            'formatted' => '₹' . number_format($grossBalance, 2),
                            'is_withdrawable' => false,
                        ],
                        'withdrawable' => [
                            'key' => 'withdrawable',
                            'label' => 'Withdrawable',
                            'subtitle' => 'Amount from deals marked done — use this for withdrawal requests',
                            'amount' => $withdrawableBalance,
                            'currency' => 'INR',
                            'formatted' => '₹' . number_format($withdrawableBalance, 2),
                            'is_withdrawable' => true,
                        ],
                    ],
                    // Legacy/flat keys for backward compatibility (withdrawable = only balance users can withdraw)
                    'balance' => $withdrawableBalance,
                    'main_balance' => $mainBalance,
                    'gross_balance' => $grossBalance,
                    'withdrawable_balance' => $withdrawableBalance,
                    'total_earned' => (float) $wallet->total_earned,
                    'total_withdrawn' => (float) $wallet->total_withdrawn,
                    'total_deposited' => (float) $wallet->total_deposited,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wallet transactions
     */
    public function getTransactions(Request $request)
    {
        try {
            $user = $request->user();
            
            // Set timezone to IST
            $istTimezone = new \DateTimeZone('Asia/Kolkata');
            
            $transactions = Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($txn) use ($istTimezone) {
                    // Convert to IST
                    $istDate = \Carbon\Carbon::parse($txn->created_at)->setTimezone($istTimezone);
                    $processedAt = $txn->processed_at ? \Carbon\Carbon::parse($txn->processed_at)->setTimezone($istTimezone) : null;
                    
                    // Parse metadata if available
                    $metadata = [];
                    if ($txn->metadata) {
                        $metadata = is_string($txn->metadata) ? json_decode($txn->metadata, true) : $txn->metadata;
                    }
                    
                    return [
                        'id' => $txn->id,
                        'transaction_id' => $txn->transaction_id,
                        'type' => $txn->type,
                        'status' => $txn->status,
                        'amount' => (float) $txn->amount,
                        'balance_before' => (float) $txn->balance_before,
                        'balance_after' => (float) $txn->balance_after,
                        'description' => $txn->description,
                        'created_at' => $istDate->format('Y-m-d H:i:s'),
                        'date' => $istDate->format('d M Y'),
                        'time' => $istDate->format('h:i A'),
                        'date_time' => $istDate->format('d M Y, h:i A'),
                        'processed_at' => $processedAt ? $processedAt->format('Y-m-d H:i:s') : null,
                        // Include metadata for commission transactions
                        'project_name' => $metadata['project_name'] ?? null,
                        'project_location' => $metadata['project_location'] ?? null,
                        'plot_number' => $metadata['plot_number'] ?? null,
                        'plot_type' => $metadata['plot_type'] ?? null,
                        'level' => $metadata['level'] ?? null,
                        'booking_amount' => isset($metadata['booking_amount']) ? (float)$metadata['booking_amount'] : null,
                        'customer_name' => $metadata['customer_name'] ?? null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request deposit
     */
    public function requestDeposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $user = $request->user();
            
            // Generate unique transaction ID
            $transactionId = 'DEP' . strtoupper(Str::random(8)) . time();

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'type' => 'deposit',
                'status' => 'pending',
                'amount' => $request->amount,
                'balance_before' => 0,
                'balance_after' => 0,
                'description' => $request->description ?? 'Deposit request',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Deposit request submitted successfully',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit deposit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request withdrawal
     */
    public function requestWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'bank_account_number' => 'required|string|max:50',
            'bank_name' => 'required|string|max:100',
            'account_holder_name' => 'required|string|max:100',
            'ifsc_code' => 'required|string|max:20',
            'branch_location' => 'required|string|max:200',
            'upi_id' => 'nullable|string|max:100',
            'comments' => 'nullable|string|max:500',
        ]);

        try {
            $user = $request->user();
            
            // Withdrawal only from Gross (released) wallet
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'main_balance' => 0,
                    'withdrawable_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'total_deposited' => 0,
                ]
            );

            $withdrawable = (float) ($wallet->withdrawable_balance ?? 0);
            if ($withdrawable < $request->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient withdrawable balance. You can only withdraw from deals marked as done. Available: ₹' . number_format($withdrawable, 2)
                ], 400);
            }

            // Generate unique transaction ID
            $transactionId = 'WTH' . strtoupper(Str::random(8)) . time();

            // Store bank details in metadata
            $metadata = [
                'bank_account_number' => $request->bank_account_number,
                'bank_name' => $request->bank_name,
                'account_holder_name' => $request->account_holder_name,
                'ifsc_code' => $request->ifsc_code,
                'branch_location' => $request->branch_location,
                'upi_id' => $request->upi_id,
                'comments' => $request->comments,
            ];

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'type' => 'withdrawal',
                'status' => 'pending',
                'amount' => $request->amount,
                'balance_before' => $wallet->withdrawable_balance ?? 0,
                'balance_after' => $wallet->withdrawable_balance ?? 0, // Will be updated on approval
                'description' => $request->description ?? 'Withdrawal request',
                'metadata' => $metadata,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }
}

