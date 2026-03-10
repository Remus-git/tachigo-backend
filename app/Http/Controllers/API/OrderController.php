<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\DB;

// ─── Broadcast Events ────────────────────────────────────────────────────────
use App\Events\NewOrderPlaced;
use App\Events\OrderModifiedByCustomer;

class OrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DELIVERY FEE  (tiered by distance)
    |--------------------------------------------------------------------------
    | 0   – 1 km   → ฿20
    | 1   – 2 km   → ฿30
    | 2   – 2.5 km → ฿40
    | 2.5 – 4 km   → ฿50
    | 4 km+        → ฿60 (maximum)
    |--------------------------------------------------------------------------
    */
    private function calculateDeliveryFee(float $distanceKm): int

    {   if ($distanceKm <= 0.5) return 0;
        if ($distanceKm <= 1)   return 20;
        if ($distanceKm <= 2)   return 30;
        if ($distanceKm <= 2.5) return 40;
        if ($distanceKm <= 4)   return 50;
        return 60;
    }

    /*
    |--------------------------------------------------------------------------
    | HAVERSINE DISTANCE
    |--------------------------------------------------------------------------
    */
    private function calculateDistanceKm(
        float $latFrom, float $lonFrom,
        float $latTo,   float $lonTo
    ): float {
        $earthRadius = 6371;
        $latFrom     = deg2rad($latFrom);
        $lonFrom     = deg2rad($lonFrom);
        $latTo       = deg2rad($latTo);
        $lonTo       = deg2rad($lonTo);

        return $earthRadius * 2 * asin(sqrt(
            pow(sin(($latTo - $latFrom) / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin(($lonTo - $lonFrom) / 2), 2)
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | PLACE ORDER
    |--------------------------------------------------------------------------
    */
    public function placeOrder(Request $request)
    {
        $request->validate([
            'delivery_address' => 'required|string',
            'phone'            => 'required|string',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
            'note'             => 'nullable|string',
        ]);

        $user = $request->user();

        $cart = Cart::with([
            'items.menuItem.category.restaurant',
            'items.modifiers.option.group',
        ])->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        DB::beginTransaction();
        try {
            $subtotal   = 0;
            $restaurant = null;

            // ── Calculate subtotal including modifier prices ───────────────
            foreach ($cart->items as $item) {
                $menuItem   = $item->menuItem;
                $restaurant = $menuItem->category->restaurant;

                $basePrice     = $menuItem->price * $item->quantity;
                $modifierTotal = 0;

                if ($item->modifiers && $item->modifiers->count() > 0) {
                    foreach ($item->modifiers as $mod) {
                        $modifierTotal += ($mod->option->additional_price ?? 0);
                    }
                    $modifierTotal *= $item->quantity;
                }

                $subtotal += $basePrice + $modifierTotal;
            }

            // ── Distance + tiered delivery fee ────────────────────────────
            $distance    = $this->calculateDistanceKm(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) $restaurant->latitude,
                (float) $restaurant->longitude
            );
            $deliveryFee = $this->calculateDeliveryFee($distance);
            $total       = $subtotal + $deliveryFee;

            // ── Create Order ──────────────────────────────────────────────
            $order = Order::create([
                'user_id'          => $user->id,
                'restaurant_id'    => $restaurant->id,
                'subtotal'         => $subtotal,
                'delivery_fee'     => $deliveryFee,
                'total_price'      => $total,
                'delivery_address' => $request->delivery_address,
                'phone'            => $request->phone,
                'latitude'         => $request->latitude,
                'longitude'        => $request->longitude,
                'note'             => $request->note,
                'status'           => 'pending',
            ]);

            // ── Create OrderItems + OrderItemModifiers ────────────────────
            foreach ($cart->items as $item) {
                $menuItem = $item->menuItem;

                $modifierAdditional = 0;
                if ($item->modifiers && $item->modifiers->count() > 0) {
                    foreach ($item->modifiers as $mod) {
                        $modifierAdditional += ($mod->option->additional_price ?? 0);
                    }
                }

                $orderItem = OrderItem::create([
                    'order_id'     => $order->id,
                    'menu_item_id' => $item->menu_item_id,
                    'quantity'     => $item->quantity,
                    'price'        => $menuItem->price + $modifierAdditional,
                    'status'       => 'pending',
                ]);

                // Snapshot modifier selections
                if ($item->modifiers && $item->modifiers->count() > 0) {
                    foreach ($item->modifiers as $mod) {
                        $option = $mod->option;
                        if (!$option) continue;

                        OrderItemModifier::create([
                            'order_item_id'        => $orderItem->id,
                            'modifier_option_id'   => $option->id,
                            'modifier_group_name'  => $option->group->name ?? '',
                            'modifier_option_name' => $option->name,
                            'additional_price'     => $option->additional_price,
                        ]);
                    }
                }
            }

            // ── Clear cart ────────────────────────────────────────────────
            $cart->items()->each(function ($item) {
                $item->modifiers()->delete();
                $item->delete();
            });

            DB::commit();

            broadcast(new NewOrderPlaced($order))->toOthers();

            return response()->json([
                'message' => 'Order placed successfully',
                'order'   => $order->load('items.modifiers'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRACK ORDER
    |--------------------------------------------------------------------------
    */
    public function trackOrder(Request $request, $orderId)
    {
        $user  = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->with([
                'items.menuItem',
                'items.modifiers',
                'restaurant',
                'rider' => fn($q) => $q->with('user'),
            ])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER APPROVES MODIFIED ORDER
    |--------------------------------------------------------------------------
    */
    public function approveModified(Request $request, $orderId)
    {
        $user  = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->with('items')
            ->firstOrFail();

        if ($order->status !== 'needs_customer_action') {
            return response()->json(['message' => 'Invalid order state'], 400);
        }

        DB::beginTransaction();
        try {
            $order->items()->where('status', 'rejected')->delete();

            $newSubtotal = $order->items()->sum(DB::raw('price * quantity'));

            if ($newSubtotal <= 0) {
                $order->status       = 'cancelled';
                $order->subtotal     = 0;
                $order->delivery_fee = 0;
                $order->total_price  = 0;
            } else {
                $order->subtotal    = $newSubtotal;
                $order->total_price = $newSubtotal + $order->delivery_fee;
                $order->status      = 'confirmed';
            }

            $order->save();
            DB::commit();

            $action = $order->status === 'cancelled' ? 'cancelled' : 'approved';
            broadcast(new OrderModifiedByCustomer($order, $action));

            return response()->json([
                'message' => 'Order updated successfully',
                'order'   => $order,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Approval failed', 'error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER CANCEL ORDER
    |--------------------------------------------------------------------------
    */
    public function cancelOrder(Request $request, $orderId)
    {
        $user  = $request->user();
        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (!in_array($order->status, ['pending', 'needs_customer_action', 'confirmed'])) {
            return response()->json(['message' => 'Order cannot be cancelled at this stage'], 400);
        }

        $order->status = 'cancelled';
        $order->save();

        broadcast(new OrderModifiedByCustomer($order, 'cancelled'));

        return response()->json(['message' => 'Order cancelled successfully']);
    }

    /*
    |--------------------------------------------------------------------------
    | RIDER LOCATION
    |--------------------------------------------------------------------------
    */
    public function riderLocation(Request $request, int $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['on_the_way'])
            ->with('rider')
            ->first();

        if (!$order || !$order->rider) {
            return response()->json(['latitude' => null, 'longitude' => null]);
        }

        return response()->json([
            'latitude'      => $order->rider->current_latitude,
            'longitude'     => $order->rider->current_longitude,
            'rider_name'    => $order->rider->user->name ?? 'Your Rider',
            'vehicle_type'  => $order->rider->vehicle_type,
            'vehicle_plate' => $order->rider->vehicle_plate,
            'rating'        => $order->rider->rating,
            'last_seen_at'  => $order->rider->last_seen_at,
        ]);
    }
}
