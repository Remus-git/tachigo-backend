<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /* ─── DASHBOARD ─────────────────────────────────────────────── */
    public function dashboard()
    {
        $totalRevenue = Order::where('status', 'delivered')->sum('total_price');
        $monthRevenue = Order::where('status', 'delivered')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->sum('total_price');

        $totalOrders = Order::count();
        $monthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();
        $liveOrders  = Order::whereIn('status', ['pending','confirmed','preparing','on_the_way'])->count();

        $totalShops   = Restaurant::where('is_approved', true)->count();
        $pendingShops = Restaurant::where('is_approved', false)->count();

        $totalUsers   = User::where('role', 'customer')->count();
        $newUsersWeek = User::where('role', 'customer')
            ->where('created_at', '>=', now()->subDays(7))->count();

        $revenueChart = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("DATE(created_at) as day, SUM(total_price) as revenue, COUNT(*) as orders")
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn($r) => [
                'day'     => date('D', strtotime($r->day)),
                'revenue' => (float) $r->revenue,
                'orders'  => (int)   $r->orders,
            ]);

        $statusBreakdown = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        return response()->json([
            'revenue'          => ['total' => $totalRevenue,  'month' => $monthRevenue],
            'orders'           => ['total' => $totalOrders,   'month' => $monthOrders, 'live' => $liveOrders],
            'shops'            => ['total' => $totalShops,    'pending' => $pendingShops],
            'users'            => ['total' => $totalUsers,    'new_this_week' => $newUsersWeek],
            'revenue_chart'    => $revenueChart,
            'status_breakdown' => $statusBreakdown,
        ]);
    }

    /* ─── SHOPS ──────────────────────────────────────────────────── */
    public function shops(Request $request)
    {
        $status = $request->query('status');

        // ✅ FIXED: removed broken withSum closure syntax & bad is_active_shop column
        $query = Restaurant::with('user')
            ->withCount('orders')
            ->withSum('orders', 'total_price'); // sum ALL orders, filter in frontend if needed

        if ($status === 'pending')   $query->where('is_approved', false);
        if ($status === 'approved')  $query->where('is_approved', true)->where('is_open', true);
        if ($status === 'suspended') $query->where('is_approved', true)->where('is_open', false);

        return response()->json($query->latest()->get());
    }

    public function approveShop($id)
    {
        $shop = Restaurant::findOrFail($id);
        $shop->is_approved = true;
        $shop->is_open     = true;
        $shop->save();

        \App\Models\Notification::create([
            'user_id' => $shop->user_id,
            'title'   => 'Application Approved! 🎉',
            'message' => 'Your restaurant "' . $shop->name . '" is now live on TachiGo.',
            'type'    => 'system',
        ]);

        return response()->json(['message' => 'Shop approved', 'shop' => $shop]);
    }

    public function rejectShop(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);
        $shop = Restaurant::findOrFail($id);
        $shop->is_approved = false;
        $shop->save();

        \App\Models\Notification::create([
            'user_id' => $shop->user_id,
            'title'   => 'Application Not Approved',
            'message' => 'Your restaurant "' . $shop->name . '" was not approved. Reason: ' . $request->reason,
            'type'    => 'system',
        ]);

        return response()->json(['message' => 'Shop rejected']);
    }

    public function suspendShop($id)
    {
        $shop = Restaurant::findOrFail($id);
        $shop->is_open = false;
        $shop->save();

        \App\Models\Notification::create([
            'user_id' => $shop->user_id,
            'title'   => 'Shop Suspended',
            'message' => 'Your restaurant "' . $shop->name . '" has been suspended. Contact support.',
            'type'    => 'system',
        ]);

        return response()->json(['message' => 'Shop suspended']);
    }

    public function reinstateShop($id)
    {
        $shop = Restaurant::findOrFail($id);
        $shop->is_open     = true;
        $shop->is_approved = true;
        $shop->save();

        \App\Models\Notification::create([
            'user_id' => $shop->user_id,
            'title'   => 'Shop Reinstated ✅',
            'message' => 'Your restaurant "' . $shop->name . '" is active again.',
            'type'    => 'system',
        ]);

        return response()->json(['message' => 'Shop reinstated']);
    }

    /* ─── ORDERS ─────────────────────────────────────────────────── */
    public function orders(Request $request)
    {
        $query = Order::with(['user:id,name,email', 'restaurant:id,name'])->latest();

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('id', 'like', "%$s%")
                  ->orWhereHas('user',       fn($u) => $u->where('name',  'like', "%$s%"))
                  ->orWhereHas('restaurant', fn($r) => $r->where('name',  'like', "%$s%"));
            });
        }

        return response()->json($query->paginate(20));
    }

    /* ─── USERS ──────────────────────────────────────────────────── */
    public function users(Request $request)
    {
        $query = User::where('role', 'customer')
            ->withCount('orders')
            ->withSum('orders', 'total_price')
            ->latest();

        if ($request->search) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"));
        }

        return response()->json($query->get());
    }

    public function banUser($id)
    {
        User::findOrFail($id)->update(['is_active' => false]);
        return response()->json(['message' => 'User banned']);
    }

    public function unbanUser($id)
    {
        User::findOrFail($id)->update(['is_active' => true]);
        return response()->json(['message' => 'User unbanned']);
    }


    //Rider

     public function riders()
    {
        return response()->json(
            \App\Models\Rider::with('user')->latest()->get()
        );
    }

    public function approveRider($id)
    {
        $rider = \App\Models\Rider::findOrFail($id);
        $rider->update(['status' => 'approved', 'approved_at' => now()]);

        \App\Models\Notification::create([
            'user_id' => $rider->user_id,
            'title'   => '✅ Account Approved!',
            'message' => 'Your rider account has been approved. You can now go online and start delivering!',
            'type'    => 'system',
            'data'    => ['status' => 'approved'],
        ]);

        return response()->json(['message' => 'Rider approved']);
    }

    public function suspendRider($id)
    {
        $rider = \App\Models\Rider::findOrFail($id);
        $rider->update(['status' => 'suspended', 'is_online' => false, 'is_available' => false]);
        return response()->json(['message' => 'Rider suspended']);
    }
}
