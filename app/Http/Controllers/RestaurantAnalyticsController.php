<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class RestaurantAnalyticsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $restaurant = $request->user()->restaurant;

    $range = $request->range ?? '7days';

    $startDate = match($range) {
        '30days' => Carbon::now()->subDays(30),
        '12months' => Carbon::now()->subMonths(12),
        default => Carbon::now()->subDays(7),
    };

    $orders = $restaurant->orders()
        ->where('status', 'delivered')
        ->where('created_at', '>=', $startDate);

    $totalRevenue = $orders->sum('total_price');
    $totalOrders = $orders->count();

    $todayRevenue = $restaurant->orders()
        ->whereDate('created_at', Carbon::today())
        ->where('status', 'delivered')
        ->sum('total_price');

    $todayOrders = $restaurant->orders()
        ->whereDate('created_at', Carbon::today())
        ->count();

    $commissionRate = 0.10;
    $commission = $totalRevenue * $commissionRate;
    $net = $totalRevenue - $commission;

    // Previous period (for growth comparison)
    $previousRevenue = $restaurant->orders()
    ->where('order_type', 'online')
        ->where('status', 'delivered')
        ->whereBetween('created_at', [
            $startDate->copy()->subDays(7),
            $startDate
        ])
        ->sum('total_price');

    $growth = $previousRevenue > 0
        ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100
        : 0;

    // Revenue chart
    $chart = $restaurant->orders()
        ->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total_price) as revenue')
        )
        ->where('status', 'delivered')
        ->where('created_at', '>=', $startDate)
        ->groupBy('date')
        ->orderBy('date')
        ->get();

    // Order status breakdown (for Pie chart)
    $statusBreakdown = $restaurant->orders()
        ->select('status', DB::raw('count(*) as total'))
        ->groupBy('status')
        ->get();

    return response()->json([
        'summary' => [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'commission' => $commission,
            'net' => $net,
            'today_orders' => $todayOrders,
            'today_revenue' => $todayRevenue,
            'growth' => round($growth, 2),
        ],
        'revenue_chart' => $chart,
        'status_breakdown' => $statusBreakdown,
    ]);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
