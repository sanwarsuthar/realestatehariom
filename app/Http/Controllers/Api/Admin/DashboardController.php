<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(Request $request)
    {
        try {
            // Total Users
            $totalUsers = DB::table('users')->where('user_type', 'broker')->count();
            
            // New Users Today
            $newUsersToday = DB::table('users')
                ->where('user_type', 'broker')
                ->whereDate('created_at', today())
                ->count();
            
            // Properties Sold Today
            $propertiesSoldToday = DB::table('sales')
                ->whereDate('created_at', today())
                ->where('status', 'confirmed')
                ->count();
            
            // Total Business Volume
            $totalBusinessVolume = DB::table('sales')
                ->where('status', 'confirmed')
                ->sum('sale_price');
            
            // Pending KYC
            $pendingKyc = DB::table('kyc_documents')
                ->where('status', 'pending')
                ->count();
            
            // Pending Deposit Requests
            $pendingDeposits = DB::table('transactions')
                ->where('type', 'deposit')
                ->where('status', 'pending')
                ->count();
            
            // Pending Withdrawal Requests
            $pendingWithdrawals = DB::table('transactions')
                ->where('type', 'withdrawal')
                ->where('status', 'pending')
                ->count();
            
            // Latest Projects
            $latestProjects = DB::table('projects')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'location', 'status', 'created_at']);
            
            // User Growth (Last 7 days)
            $userGrowth = DB::table('users')
                ->where('user_type', 'broker')
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Sales Growth (Last 7 days)
            $salesGrowth = DB::table('sales')
                ->where('status', 'confirmed')
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(sale_price) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Slab Distribution
            $slabDistribution = DB::table('users')
                ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                ->where('users.user_type', 'broker')
                ->select('slabs.name', 'slabs.color_code', DB::raw('COUNT(*) as count'))
                ->groupBy('slabs.id', 'slabs.name', 'slabs.color_code')
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard stats retrieved successfully',
                'data' => [
                    'overview' => [
                        'total_users' => $totalUsers,
                        'new_users_today' => $newUsersToday,
                        'properties_sold_today' => $propertiesSoldToday,
                        'total_business_volume' => $totalBusinessVolume,
                        'pending_kyc' => $pendingKyc,
                        'pending_deposits' => $pendingDeposits,
                        'pending_withdrawals' => $pendingWithdrawals,
                    ],
                    'latest_projects' => $latestProjects,
                    'user_growth' => $userGrowth,
                    'sales_growth' => $salesGrowth,
                    'slab_distribution' => $slabDistribution,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
