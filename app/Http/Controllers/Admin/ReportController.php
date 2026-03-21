<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    public function sales(Request $request)
    {
        $query = DB::table('sales')
            ->join('plots', 'sales.plot_id', '=', 'plots.id')
            ->join('projects', 'plots.project_id', '=', 'projects.id')
            ->join('users', 'sales.sold_by_user_id', '=', 'users.id')
            ->select(
                'sales.*',
                'plots.plot_number',
                'projects.name as project_name',
                'users.name as broker_name',
                'users.broker_id'
            );

        // Apply date filters
        if ($request->filled('from_date')) {
            $query->whereDate('sales.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('sales.created_at', '<=', $request->to_date);
        }

        if ($request->filled('status')) {
            $query->where('sales.status', $request->status);
        }

        $sales = $query->orderBy('sales.created_at', 'desc')->paginate(20);

        // Get summary stats
        $summary = DB::table('sales')
            ->selectRaw('
                COUNT(*) as total_sales,
                SUM(sale_price) as total_revenue,
                SUM(commission_amount) as total_commission,
                AVG(sale_price) as avg_sale_price
            ')
            ->when($request->filled('from_date'), function ($query) use ($request) {
                return $query->whereDate('created_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($query) use ($request) {
                return $query->whereDate('created_at', '<=', $request->to_date);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->first();

        $statusOptions = ['pending', 'confirmed', 'cancelled'];

        return view('admin.reports.sales', compact('sales', 'summary', 'statusOptions'));
    }

    public function users(Request $request)
    {
        $query = DB::table('users')
            ->leftJoin('slabs', 'users.slab_id', '=', 'slabs.id')
            ->leftJoin('wallets', 'users.id', '=', 'wallets.user_id')
            ->select(
                'users.*',
                'slabs.name as slab_name',
                'slabs.color_code as slab_color',
                'wallets.balance',
                'wallets.total_earned',
                'wallets.total_withdrawn'
            )
            ->where('users.user_type', 'broker');

        // Apply filters
        if ($request->filled('slab_id')) {
            $query->where('users.slab_id', $request->slab_id);
        }

        if ($request->filled('status')) {
            $query->where('users.status', $request->status);
        }

        if ($request->filled('kyc_verified')) {
            $query->where('users.kyc_verified', $request->kyc_verified);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('users.created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('users.created_at', '<=', $request->to_date);
        }

        $users = $query->orderBy('users.created_at', 'desc')->paginate(20);

        // Get summary stats
        $summary = DB::table('users')
            ->leftJoin('wallets', 'users.id', '=', 'wallets.user_id')
            ->selectRaw('
                COUNT(*) as total_users,
                SUM(CASE WHEN users.status = "active" THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN users.kyc_verified = 1 THEN 1 ELSE 0 END) as kyc_verified_users,
                SUM(wallets.total_earned) as total_commission_paid,
                SUM(wallets.balance) as total_wallet_balance
            ')
            ->where('users.user_type', 'broker')
            ->when($request->filled('from_date'), function ($query) use ($request) {
                return $query->whereDate('users.created_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($query) use ($request) {
                return $query->whereDate('users.created_at', '<=', $request->to_date);
            })
            ->first();

        // Get filter options
        $slabs = DB::table('slabs')->where('is_active', true)->get();
        $statusOptions = ['active', 'inactive', 'suspended'];

        return view('admin.reports.users', compact('users', 'summary', 'slabs', 'statusOptions'));
    }
}
