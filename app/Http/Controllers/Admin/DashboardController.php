<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Show admin dashboard
     */
    public function index()
    {
        try {
            // Cache dashboard data for 60 seconds to improve performance
            $stats = Cache::remember('admin.dashboard.stats', 60, function () {
                $today = Carbon::today();
                $todayEnd = Carbon::today()->endOfDay();
                
                return [
                    'total_users' => DB::table('users')->where('user_type', 'broker')->count(),
                    'new_users_today' => DB::table('users')
                        ->where('user_type', 'broker')
                        ->whereBetween('created_at', [$today, $todayEnd])
                        ->count(),
                    'properties_sold_today' => DB::table('sales')
                        ->whereBetween('created_at', [$today, $todayEnd])
                        ->where('status', 'confirmed')
                        ->count(),
                    'total_business_volume' => DB::table('sales')
                        ->where('status', 'confirmed')
                        ->sum('sale_price'),
                    'pending_kyc' => DB::table('kyc_documents')
                        ->where('status', 'pending')
                        ->count(),
                    'pending_deposits' => DB::table('transactions')
                        ->where('type', 'deposit')
                        ->where('status', 'pending')
                        ->count(),
                    'pending_withdrawals' => DB::table('transactions')
                        ->where('type', 'withdrawal')
                        ->where('status', 'pending')
                        ->count(),
                ];
            });

            // Cache latest projects for 120 seconds
            $latestProjects = Cache::remember('admin.dashboard.latest_projects', 120, function () {
                return DB::table('projects')
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($project) {
                        $project->created_at = Carbon::parse($project->created_at);
                        return $project;
                    });
            });

            // Cache recent users for 60 seconds
            $recentUsers = Cache::remember('admin.dashboard.recent_users', 60, function () {
                return DB::table('users')
                    ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                    ->where('users.user_type', 'broker')
                    ->select([
                        'users.id',
                        'users.name',
                        'users.broker_id',
                        'users.email',
                        'users.phone_number',
                        'users.status',
                        'users.kyc_verified',
                        'users.created_at',
                        'slabs.name as slab_name',
                        'slabs.color_code as slab_color'
                    ])
                    ->orderBy('users.created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($user) {
                        $user->created_at = Carbon::parse($user->created_at);
                        return $user;
                    });
            });

            // Cache slab distribution for 300 seconds (5 minutes)
            $slabDistribution = Cache::remember('admin.dashboard.slab_distribution', 300, function () {
                return DB::table('users')
                    ->join('slabs', 'users.slab_id', '=', 'slabs.id')
                    ->where('users.user_type', 'broker')
                    ->select('slabs.name', 'slabs.color_code', DB::raw('COUNT(*) as count'))
                    ->groupBy('slabs.id', 'slabs.name', 'slabs.color_code')
                    ->get();
            });

            // Get admin referral code
            $admin = DB::table('users')->where('user_type', 'admin')->first();
            $adminReferralCode = $admin ? $admin->referral_code : 'N/A';

            return view('admin.dashboard', compact('stats', 'latestProjects', 'recentUsers', 'slabDistribution', 'adminReferralCode'));
            
        } catch (\Exception $e) {
            // Get admin referral code even on error
            $admin = DB::table('users')->where('user_type', 'admin')->first();
            $adminReferralCode = $admin ? $admin->referral_code : 'N/A';
            
            return view('admin.dashboard', [
                'stats' => [],
                'latestProjects' => collect(),
                'recentUsers' => collect(),
                'slabDistribution' => collect(),
                'adminReferralCode' => $adminReferralCode,
                'error' => $e->getMessage()
            ]);
        }
    }
}
