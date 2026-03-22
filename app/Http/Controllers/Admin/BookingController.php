<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\PaymentRequest;
use App\Models\PaymentMethod;
use App\Models\SaleCommissionRelease;
use App\Services\CommissionDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['plot.project', 'customer', 'soldByUser'])
            ->where('status', 'confirmed')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone_number', 'like', '%' . $search . '%')
                        ->orWhere('broker_id', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('plot', function ($pq) use ($search) {
                        $pq->where('plot_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('plot.project', function ($projQ) use ($search) {
                        $projQ->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $bookings = $query->paginate(20)->through(function ($sale) {
            $totalReceived = (float) PaymentRequest::where('sale_id', $sale->id)->where('status', 'approved')->sum('amount');
            $totalValue = (float)($sale->total_sale_value ?? 0) ?: $totalReceived;
            $sale->total_received = $totalReceived;
            $sale->pending_amount = max(0, $totalValue - $totalReceived);
            return $sale;
        })->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function show($id)
    {
        $booking = Sale::with(['plot.project', 'customer', 'soldByUser', 'paymentRequests.paymentMethod', 'paymentRequests.processedBy'])->findOrFail($id);
        $totalReceived = (float) $booking->paymentRequests()->where('status', 'approved')->sum('amount');
        $totalValue = (float)($booking->total_sale_value ?? 0) ?: $totalReceived;
        $pending = max(0, $totalValue - $totalReceived);
        $paymentMethods = PaymentMethod::where('is_active', true)->get();
        $dealStatus = $booking->deal_status ?? 'pending';
        $amountLeftForDealDone = $pending; // Amount left to be paid for full payment (info only when marking deal done)

        return view('admin.bookings.show', compact('booking', 'totalReceived', 'totalValue', 'pending', 'paymentMethods', 'dealStatus', 'amountLeftForDealDone'));
    }

    public function recordPayment(Request $request, $id)
    {
        $booking = Sale::with(['plot', 'customer'])->findOrFail($id);
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $booking->load(['plot', 'customer']);
        $totalValue = (float)($booking->total_sale_value ?? 0);
        $totalReceived = (float) $booking->paymentRequests()->where('status', 'approved')->sum('amount');
        $pending = max(0, $totalValue - $totalReceived);
        $amount = (float) $request->amount;

        if ($amount > $pending) {
            return redirect()->back()
                ->with('error', 'Amount cannot exceed pending amount (₹' . number_format($pending, 2) . ').');
        }

        DB::beginTransaction();
        try {
            $pr = PaymentRequest::create([
                'user_id' => $booking->customer_id,
                'plot_id' => $booking->plot_id,
                'payment_method_id' => $request->payment_method_id,
                'amount' => $amount,
                'status' => 'approved',
                'sale_id' => $booking->id,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'admin_notes' => $request->admin_notes ?? 'Recorded by admin (instalment)',
            ]);

            $commissionService = new CommissionDistributionService();
            $commissionService->releaseProportionalCommission($booking);

            DB::commit();
            return redirect()->back()
                ->with('success', 'Payment of ₹' . number_format($amount, 2) . ' recorded. Commission will be credited to broker wallet when the deal is marked as done.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Record payment failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * Mark booking as Deal Done: move this sale's gross (released) commission into each user's withdrawable wallet.
     * Only process withdrawal from withdrawable wallet; this unlocks that amount for withdrawal.
     */
    public function markDealDone(Request $request, $id)
    {
        $booking = Sale::with(['plot.project', 'commissionReleases', 'paymentRequests'])->findOrFail($id);
        $dealStatus = $booking->deal_status ?? 'pending';

        if ($dealStatus === 'done') {
            return redirect()->back()->with('error', 'This deal is already marked as done.');
        }
        if ($dealStatus === 'failed') {
            return redirect()->back()->with('error', 'Cannot mark as done: this deal was marked as failed.');
        }

        // Enforce: deal can only be marked as done when full payment has been received
        $totalReceived = (float) $booking->paymentRequests()->where('status', 'approved')->sum('amount');
        $totalValue = (float)($booking->total_sale_value ?? 0) ?: $totalReceived;
        $pending = max(0, $totalValue - $totalReceived);
        if ($pending > 0) {
            return redirect()->back()->with('error', 'Cannot mark deal as done while there is pending amount (₹' . number_format($pending, 2) . '). Please collect full payment or mark the deal as failed.');
        }

        $releases = SaleCommissionRelease::where('sale_id', $booking->id)->get();
        if ($releases->isEmpty()) {
            return redirect()->back()->with('error', 'No commission releases found for this booking. Cannot mark deal done.');
        }

        DB::beginTransaction();
        try {
            foreach ($releases as $row) {
                $userId = $row->user_id;
                // Final credit happens ONLY on Deal Done.
                // Use total_commission (full) because Deal Done is only allowed after full payment.
                $finalAmount = (float) ($row->total_commission ?? 0);
                if ($finalAmount <= 0) {
                    continue;
                }
                $wallet = DB::table('wallets')->where('user_id', $userId)->first();
                if (!$wallet) {
                    // Create wallet if missing
                    DB::table('wallets')->insert([
                        'user_id' => $userId,
                        'balance' => 0,
                        'main_balance' => 0,
                        'withdrawable_balance' => 0,
                        'total_earned' => 0,
                        'total_withdrawn' => 0,
                        'total_deposited' => 0,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $wallet = DB::table('wallets')->where('user_id', $userId)->first();
                }

                $withdrawableBefore = (float)($wallet->withdrawable_balance ?? 0);
                $withdrawableAfter = $withdrawableBefore + $finalAmount;

                if($userId != 1){
                // Credit broker wallet balance ONLY now (Deal Done)
                    DB::table('wallets')->where('user_id', $userId)->increment('balance', $finalAmount);
                    DB::table('wallets')->where('user_id', $userId)->increment('withdrawable_balance', $finalAmount);
                    DB::table('wallets')->where('user_id', $userId)->increment('main_balance', $finalAmount);
                    DB::table('wallets')->where('user_id', $userId)->increment('total_earned', $finalAmount);

                }
                // Update user's total commission earned now (finalized)
                DB::table('users')->where('id', $userId)->increment('total_commission_earned', $finalAmount);

                // Mark pending commission transaction as completed (avoid duplicate entries)
                $pendingTx = DB::table('transactions')
                    ->where('user_id', $userId)
                    ->where('user_id', '!=', 1)
                    ->where('type', 'commission')
                    ->where('status', 'pending')
                    ->where('reference_id', $booking->id)
                    ->orderBy('id', 'asc')
                    ->first();

               $adminPendingTx = DB::table('transactions')
                    ->where('user_id', 1)
                    ->where('type', 'commission')
                    ->where('status', 'pending')
                    ->where('reference_id', $booking->id)
                    ->where('metadata->source', 'remaining_pool_pending')
                    ->orderBy('id', 'asc')
                    ->first();

              
                $project = $booking->plot && $booking->plot->project ? $booking->plot->project : null;
                $plot = $booking->plot;
                $desc = "Deal done – commission credited (Booking #{$booking->id})";
                if ($project && $plot) {
                    $desc .= " – {$project->name}, {$plot->type} {$plot->plot_number}";
                }


                if ($adminPendingTx) {

                    $totalFinalAmount = $finalAmount + $adminPendingTx->amount;

                    DB::table('wallets')->where('user_id', 1)->increment('balance', $finalAmount);
                    DB::table('wallets')->where('user_id', 1)->increment('withdrawable_balance', $finalAmount);
                    DB::table('wallets')->where('user_id', 1)->increment('main_balance', $finalAmount);
                    DB::table('wallets')->where('user_id', 1)->increment('total_earned', $finalAmount);

                    $updated = DB::table('transactions')
                        ->where('id', $adminPendingTx->id)
                        ->update([
                            'status' => 'completed',
                            'processed_by' => auth()->id(),
                            'processed_at' => now(),
                            'amount' => $totalFinalAmount,
                            'updated_at' => now(),
                        ]);
                
                    \Log::info('Admin TX updated', [
                        'id' => $adminPendingTx->id,
                        'updated' => $updated
                    ]);
                }

                if ($pendingTx) {
                    DB::table('transactions')->where('id', $pendingTx->id)->update([
                        'status' => 'completed',
                        'amount' => $finalAmount,
                        'balance_before' => $withdrawableBefore,
                        'balance_after' => $withdrawableAfter,
                        // 'description' => $desc,
                        'processed_by' => auth()->id(),
                        'processed_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    if($userId != 1){
                        // Fallback (older data): create a single completed transaction
                        $txId = 'DEAL' . strtoupper(Str::random(8)) . time();
                        while (DB::table('transactions')->where('transaction_id', $txId)->exists()) {
                            $txId = 'DEAL' . strtoupper(Str::random(8)) . time() . rand(100, 999);
                        }
                        DB::table('transactions')->insert([
                            'user_id' => $userId,
                            'transaction_id' => $txId,
                            'type' => 'commission',
                            'status' => 'completed',
                            'amount' => $finalAmount,
                            'balance_before' => $withdrawableBefore,
                            'balance_after' => $withdrawableAfter,
                            'description' => $desc,
                            'reference_id' => $booking->id,
                            'metadata' => json_encode(['sale_id' => $booking->id, 'source' => 'deal_done_finalize', 'deal_done_at' => now()->toIso8601String()]),
                            'processed_by' => auth()->id(),
                            'processed_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    
                }

                // Keep release row consistent (full release on deal done)
                $row->update(['released_amount' => $finalAmount]);
            }
            $booking->update(['deal_status' => 'done', 'deal_done_at' => now()]);
            DB::commit();
            return redirect()->back()->with('success', 'Deal marked as done. Released amounts have been moved to users’ withdrawable wallets.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mark deal done failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to mark deal done: ' . $e->getMessage());
        }
    }

    /**
     * Mark booking as Deal Failed: reverse gross (and withdrawable if already done) and show negative cut in main wallet.
     * - Reverses wallet balance (gross), main_balance, and withdrawable (if deal was done) from sale_commission_releases.
     * - If no release records exist (e.g. data inconsistency), reverses from sale.commission_distribution.
     * - Decrements users.total_commission_earned so reporting stays correct.
     * - Sale status → cancelled (so it no longer counts in volume/slabs); plot is freed.
     */
    public function markDealFailed(Request $request, $id)
    {
        $booking = Sale::with(['plot.project', 'commissionReleases'])->findOrFail($id);
        $dealStatus = $booking->deal_status ?? 'pending';

        if ($dealStatus === 'failed') {
            return redirect()->back()->with('error', 'This deal is already marked as failed.');
        }

        $releases = SaleCommissionRelease::where('sale_id', $booking->id)->get();

        // If no release rows (e.g. commission was distributed but releases missing), build from commission_distribution
        $reversalRows = [];
        if ($releases->isNotEmpty()) {
            foreach ($releases as $row) {
                $reversalRows[] = [
                    'user_id' => $row->user_id,
                    'released_amount' => (float) $row->released_amount,
                    'total_commission' => (float) $row->total_commission,
                ];
            }
        } else {
            $distribution = $booking->commission_distribution;
            if (is_string($distribution)) {
                $distribution = json_decode($distribution, true) ?: [];
            }
            foreach ($distribution ?? [] as $level => $entry) {
                if (is_string($level) && strpos($level, '_') === 0) {
                    continue;
                }
                if (!is_array($entry) || empty($entry['user_id']) || !isset($entry['commission_amount'])) {
                    continue;
                }
                $reversalRows[] = [
                    'user_id' => $entry['user_id'],
                    'released_amount' => (float)($entry['commission_amount'] ?? 0),
                    'total_commission' => (float)($entry['commission_amount'] ?? 0),
                ];
            }
            if (empty($reversalRows)) {
                Log::warning("Mark deal failed: Sale #{$booking->id} has no commission_releases and no commission_distribution. Only updating status and freeing plot.");
            }
        }

        DB::beginTransaction();
        try {
            foreach ($reversalRows as $row) {
                $userId = $row['user_id'];
                $totalCommission = $row['total_commission'];
                $wallet = DB::table('wallets')->where('user_id', $userId)->first();
                if (!$wallet) {
                    continue;
                }
                $balance = (float) $wallet->balance;
                $mainBalance = (float)($wallet->main_balance ?? 0);
                $withdrawableBalance = (float)($wallet->withdrawable_balance ?? 0);

                /**
                 * IMPORTANT after "credit on Deal Done" change:
                 * - If deal was NOT done, wallet was never credited → do NOT reduce balances.
                 *   Just cancel pending commission transactions for this sale.
                 * - If deal WAS done, reverse only what was actually credited (released_amount / total_commission on deal done),
                 *   so the net impact of this deal becomes 0.
                 */
                $grossToReverse = 0;
                $mainToReverse = 0;
                $withdrawableToReverse = 0;

                if ($dealStatus === 'done') {
                    // On deal done we set released_amount = total_commission (full).
                    $credited = (float)($row['released_amount'] ?? 0);
                    if ($credited <= 0) {
                        $credited = (float)$totalCommission;
                    }
                    $grossToReverse = ($credited > 0 && $balance > 0) ? min($credited, $balance) : 0;
                    $withdrawableToReverse = ($credited > 0 && $withdrawableBalance > 0) ? min($credited, $withdrawableBalance) : 0;
                    $mainToReverse = $credited;
                }

                $newBalance = max(0, $balance - $grossToReverse);
                $newWithdrawable = max(0, $withdrawableBalance - $withdrawableToReverse);
                $newMain = $dealStatus === 'done' ? ($mainBalance - $mainToReverse) : $mainBalance;

                $walletTotalEarned = (float)($wallet->total_earned ?? 0);
                $newTotalEarned = $dealStatus === 'done'
                    ? max(0, $walletTotalEarned - $mainToReverse)
                    : $walletTotalEarned;


                DB::table('wallets')->where('user_id', $userId)->update([
                    'balance' => $newBalance,
                    'main_balance' => $newMain,
                    'withdrawable_balance' => $newWithdrawable,
                    'total_earned' => $newTotalEarned,
                    'updated_at' => now(),
                ]);

                $user = DB::table('users')->where('id', $userId)->first();
                if ($user && $dealStatus === 'done' && $mainToReverse > 0) {
                    $newTotal = max(0, (float)($user->total_commission_earned ?? 0) - $mainToReverse);
                    DB::table('users')->where('id', $userId)->update(['total_commission_earned' => $newTotal]);
                }

                // Cancel any pending commission transaction(s) for this sale so they don't later get finalized.
                DB::table('transactions')
                    ->where('user_id', $userId)
                    ->where('type', 'commission')
                    ->where('status', 'pending')
                    ->where('reference_id', $booking->id)
                    ->update([
                        'status' => 'cancelled',
                        'processed_by' => auth()->id(),
                        'processed_at' => now(),
                        'updated_at' => now(),
                    ]);

                $txId = 'FAIL' . strtoupper(Str::random(8)) . time();
                while (DB::table('transactions')->where('transaction_id', $txId)->exists()) {
                    $txId = 'FAIL' . strtoupper(Str::random(8)) . time() . rand(100, 999);
                }
                $project = $booking->plot && $booking->plot->project ? $booking->plot->project : null;
                $plot = $booking->plot;
                $desc = "Deal failed – reversed (Booking #{$booking->id}). Gross and main reduced.";
                if ($project && $plot) {
                    $desc .= " – {$project->name}, {$plot->type} {$plot->plot_number}";
                }
                DB::table('transactions')->insert([
                    'user_id' => $userId,
                    'transaction_id' => $txId,
                    'type' => 'refund',
                    'status' => 'completed',
                    'amount' => -$grossToReverse,
                    'balance_before' => $balance,
                    'balance_after' => $newBalance,
                    'description' => $desc,
                    'reference_id' => $booking->id,
                    'metadata' => json_encode([
                        'sale_id' => $booking->id,
                        'source' => 'deal_failed_reversal',
                        'deal_failed_at' => now()->toIso8601String(),
                        'gross_reversed' => $grossToReverse,
                        'main_reversed' => $mainToReverse,
                    ]),
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Also cancel any pending commission transactions for this sale that might not have matched a release row
            DB::table('transactions')
                ->where('type', 'commission')
                ->where('status', 'pending')
                ->where('reference_id', $booking->id)
                ->update([
                    'status' => 'cancelled',
                    'processed_by' => auth()->id(),
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Mark deal as failed and free the plot so other users can book it
            $booking->update([
                'deal_status' => 'failed',
                'deal_failed_at' => now(),
                'status' => 'cancelled',
            ]);
            $plot = $booking->plot;
            if ($plot) {
                $plot->update(['status' => 'available']);
            }
            DB::commit();
            return redirect()->back()->with('success', 'Deal marked as failed. Wallets reversed, plot is now available for others to book.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mark deal failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to mark deal failed: ' . $e->getMessage());
        }
    }
}
