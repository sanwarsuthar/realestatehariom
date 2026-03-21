<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index()
    {
        $stats = [
            'total_balance' => DB::table('wallets')->sum('balance'),
            'total_withdrawable' => DB::table('wallets')->sum('withdrawable_balance'),
            'total_earned' => DB::table('wallets')->sum('total_earned'),
            'total_withdrawn' => DB::table('wallets')->sum('total_withdrawn'),
            'pending_deposits' => DB::table('transactions')
                ->where('type', 'deposit')
                ->where('status', 'pending')
                ->sum('amount'),
            'pending_withdrawals' => DB::table('transactions')
                ->where('type', 'withdrawal')
                ->where('status', 'pending')
                ->sum('amount'),
        ];

        // Get pending deposits and withdrawals
        $deposits = Transaction::with(['user'])
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $withdrawals = Transaction::with(['user'])
            ->where('type', 'withdrawal')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.wallet.index', compact('stats', 'deposits', 'withdrawals'));
    }

    public function deposits(Request $request)
    {
        $query = Transaction::with(['user'])
            ->where('type', 'deposit');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $deposits = $query->orderBy('created_at', 'desc')->paginate(20);

        $statusOptions = ['pending', 'completed', 'failed', 'cancelled'];

        return view('admin.wallet.deposits', compact('deposits', 'statusOptions'));
    }

    public function withdrawals(Request $request)
    {
        $query = Transaction::with(['user'])
            ->where('type', 'withdrawal');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderBy('created_at', 'desc')->paginate(20);

        $statusOptions = ['pending', 'completed', 'failed', 'cancelled'];

        return view('admin.wallet.withdrawals', compact('withdrawals', 'statusOptions'));
    }

    public function showWithdrawal($id)
    {
        $transaction = Transaction::with(['user'])->findOrFail($id);
        
        if ($transaction->type !== 'withdrawal') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'transaction' => [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'description' => $transaction->description,
                'metadata' => $transaction->metadata,
                'created_at' => $transaction->created_at->toDateTimeString(),
                'user' => [
                    'id' => $transaction->user->id,
                    'name' => $transaction->user->name,
                    'broker_id' => $transaction->user->broker_id,
                ],
            ]
        ]);
    }

    public function approveDeposit(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);
        
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction for approval'
            ], 400);
        }

        DB::transaction(function () use ($transaction) {
            // Get or create wallet
            $wallet = $transaction->user->wallet ?? \App\Models\Wallet::create([
                'user_id' => $transaction->user_id,
                'balance' => 0,
                'main_balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
                'total_deposited' => 0,
            ]);

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore + $transaction->amount;

            // Update transaction with balance info and status
            $transaction->update([
                'status' => 'completed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            // Update user wallet
            $wallet->increment('balance', $transaction->amount);
            $wallet->increment('total_deposited', $transaction->amount);
        });

        return response()->json([
            'success' => true,
            'message' => 'Deposit approved successfully'
        ]);
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);
        
        if ($transaction->type !== 'withdrawal' || $transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction for approval'
            ], 400);
        }

        // At this point, funds have already been reserved from withdrawable_balance
        $wallet = $transaction->user->wallet;
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found for this user.'
            ], 400);
        }

        // Check if user has sufficient withdrawable balance
        $withdrawableBalance = (float) ($wallet->withdrawable_balance ?? 0);
        if ($withdrawableBalance < $transaction->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient withdrawable balance. Available: ₹' . number_format($withdrawableBalance, 2)
            ], 400);
        }

        DB::transaction(function () use ($transaction, $wallet) {
            // Decrement withdrawable_balance ONLY when withdrawal is approved
            $balanceBefore = (float) ($wallet->withdrawable_balance ?? 0);
            $balanceAfter = $balanceBefore - $transaction->amount;

            // Update transaction with balance info and status
            $transaction->update([
                'status' => 'completed',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            // Decrement withdrawable balance and update total_withdrawn
            $wallet->decrement('withdrawable_balance', $transaction->amount);
            $wallet->increment('total_withdrawn', $transaction->amount);
        });

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal approved successfully'
        ]);
    }

    public function rejectDeposit(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);
        
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction for rejection'
            ], 400);
        }

        $transaction->update([
            'status' => 'cancelled',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deposit rejected successfully'
        ]);
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);
        
        if ($transaction->type !== 'withdrawal' || $transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction for rejection'
            ], 400);
        }

        DB::transaction(function () use ($transaction) {
            // Since balance was never decremented when request was created,
            // we don't need to increment it back when rejecting
            $transaction->update([
                'status' => 'cancelled',
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal rejected successfully'
        ]);
    }
}
