<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PosController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET menu for POS (categories → items → modifier groups → options)
    | GET /pos/menu
    |--------------------------------------------------------------------------
    */
    public function getMenu(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        $categories = $restaurant->categories()
            ->with([
                'menuItems' => function ($q) {
                    $q->where('is_available', true)
                      ->with('modifierGroups.options');
                },
            ])
            ->get();

        return response()->json($categories);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE a POS order
    | POST /pos/order
    |--------------------------------------------------------------------------
    */
    public function createOrder(Request $request)
    {
        $request->validate([
            'items'                                  => 'required|array|min:1',
            'items.*.menu_item_id'                   => 'required|integer|exists:menu_items,id',
            'items.*.quantity'                       => 'required|integer|min:1',
            'items.*.price'                          => 'required|numeric|min:0',
            'items.*.modifiers'                      => 'sometimes|array',
            'items.*.modifiers.*.option_id'          => 'required|integer',
            'items.*.modifiers.*.name'               => 'required|string',
            'items.*.modifiers.*.additional_price'   => 'required|numeric|min:0',
            'table_number'                           => 'nullable|string|max:50',
            'phone'                                  => 'nullable|string|max:30',
            'delivery_address'                       => 'nullable|string|max:255',
            'delivery_fee'                           => 'nullable|numeric|min:0',
        ]);

        $restaurant  = $request->user()->restaurant;
        $deliveryFee = $request->delivery_fee ?? 0;
        $subtotal    = collect($request->items)->sum(fn($i) => $i['price'] * $i['quantity']);
        $total       = $subtotal + $deliveryFee;

        $order = Order::create([
            'restaurant_id'    => $restaurant->id,
            'user_id'          => $request->user()->id,
            'status'           => 'confirmed',
            'order_type'       => 'pos',
            'table_number'     => $request->table_number,
            'phone'            => $request->phone ?? '-',
            'delivery_address' => $request->delivery_address ?? 'Walk-in',
            'delivery_fee'     => $deliveryFee,
            'subtotal'         => $subtotal,
            'total_price'      => $total,
        ]);

        foreach ($request->items as $item) {
            $orderItem = $order->items()->create([
                'menu_item_id' => $item['menu_item_id'],
                'quantity'     => $item['quantity'],
                'price'        => $item['price'],
                'status'       => 'confirmed',
            ]);

            // Save modifier selections
            if (!empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $mod) {
                    // Try to get group name from the modifier option
                    $option = \App\Models\ModifierOption::with('group')->find($mod['option_id']);
                    $orderItem->modifiers()->create([
                        'modifier_option_id'   => $mod['option_id'],
                        'modifier_option_name' => $mod['name'],
                        'modifier_group_name'  => $option?->group?->name ?? '',
                        'additional_price'     => $mod['additional_price'],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'POS order created',
            'order'   => $order->load('items.menuItem'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | GET POS history / analytics
    | GET /pos/history?range=today|week|month
    |--------------------------------------------------------------------------
    */
    public function history(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        $range      = $request->query('range', 'today');

        $start = match($range) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            default => Carbon::today(),
        };
        $end = Carbon::now();

        // ── Summary ──────────────────────────────────────────────────────────
        $baseQ      = Order::where('restaurant_id', $restaurant->id)
                           ->where('order_type', 'pos')
                           ->whereBetween('created_at', [$start, $end]);

        $totalSales = (clone $baseQ)->sum('total_price');
        $orderCount = (clone $baseQ)->count();
        $avgOrder   = $orderCount > 0 ? $totalSales / $orderCount : 0;

        // ── Chart: by hour (today) or by date (week/month) ────────────────────
        if ($range === 'today') {
            $chart = (clone $baseQ)
                ->select(
                    DB::raw('HOUR(created_at) as period'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total_price) as sales')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($r) => [
                    'label'  => str_pad($r->period, 2, '0', STR_PAD_LEFT) . ':00',
                    'orders' => (int)   $r->orders,
                    'sales'  => (float) $r->sales,
                ]);
        } else {
            $chart = (clone $baseQ)
                ->select(
                    DB::raw('DATE(created_at) as period'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total_price) as sales')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($r) => [
                    'label'  => Carbon::parse($r->period)->format($range === 'week' ? 'D' : 'd/m'),
                    'orders' => (int)   $r->orders,
                    'sales'  => (float) $r->sales,
                ]);
        }

        // ── Top Selling Items ─────────────────────────────────────────────────
        $topItems = DB::table('order_items')
            ->join('orders',     'orders.id',     '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.order_type',    'pos')
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'menu_items.name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('qty')
            ->limit(8)
            ->get();

        // ── Modifiers Breakdown ───────────────────────────────────────────────
        // Counts how many times each modifier option was chosen across POS orders in range
        $modifiers = DB::table('order_item_modifiers')
            ->join('order_items', 'order_items.id', '=', 'order_item_modifiers.order_item_id')
            ->join('orders',      'orders.id',      '=', 'order_items.order_id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->where('orders.order_type',    'pos')
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'order_item_modifiers.modifier_option_name as option_name',
                'order_item_modifiers.modifier_group_name  as group_name',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('order_item_modifiers.modifier_option_name', 'order_item_modifiers.modifier_group_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // ── Recent Orders ─────────────────────────────────────────────────────
        $recent = (clone $baseQ)
            ->with(['items' => function ($q) {
                $q->with(['menuItem', 'modifiers']);
            }])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($o) => [
                'id'               => $o->id,
                'created_at'       => $o->created_at,
                'table_number'     => $o->table_number,
                'phone'            => $o->phone,
                'delivery_address' => $o->delivery_address,
                'total_price'      => $o->total_price,
                'delivery_fee'     => $o->delivery_fee,
                'items'            => $o->items->map(fn($i) => [
                    'name'      => $i->menuItem?->name ?? 'Item',
                    'quantity'  => $i->quantity,
                    'price'     => $i->price,
                    'modifiers' => $i->modifiers->map(fn($m) => [
                        'name'             => $m->modifier_option_name,
                        'additional_price' => $m->additional_price,
                    ]),
                ]),
            ]);

        return response()->json([
            'summary'   => [
                'total_sales' => round($totalSales, 2),
                'order_count' => $orderCount,
                'avg_order'   => round($avgOrder, 2),
            ],
            'chart'     => $chart->values(),
            'top_items' => $topItems,
            'modifiers' => $modifiers,
            'recent'    => $recent,
        ]);
    }
}
