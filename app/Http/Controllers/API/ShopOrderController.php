<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Notification;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\OrderStatusUpdated;
use App\Events\NewDeliveryAvailable;
use App\Jobs\BroadcastToAllRiders;

class ShopOrderController extends Controller
{
    // ─── Status labels for customer notifications ─────────────────────────────
    private const STATUS_MESSAGES = [
        'confirmed'  => 'Your order has been confirmed! We\'re getting it ready.',
        'preparing'  => 'The restaurant is now preparing your food.',
        'on_the_way' => 'Your order is on the way!',
        'delivered'  => 'Your order has been delivered. Enjoy your meal!',
        'cancelled'  => 'Your order has been cancelled.',
    ];

    /*
    |--------------------------------------------------------------------------
    | INCOMING ORDERS
    |--------------------------------------------------------------------------
    */
  public function incomingOrders(Request $request)
{
    $user = $request->user();

    if (!$user->restaurant) {
        return response()->json(['message' => 'No restaurant found for this user'], 404);
    }

    $orders = $user->restaurant
        ->orders()
        ->with(['items.menuItem', 'user','items.modifiers'])
        ->orderBy('created_at', 'desc')
        ->limit(40)
        ->get();

    return response()->json($orders);
}

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */
    public function updateStatus(Request $request, $orderId)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,on_the_way,delivered,cancelled',
        ]);

        $user       = $request->user();
        $restaurant = $user->restaurant;
        $order      = $restaurant->orders()->where('id', $orderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->status = $request->status;
        $order->save();



        // Notify customer via WebSocket
        broadcast(new OrderStatusUpdated($order));

        // Save notification to customer's notification list
        $this->notifyCustomer($order);

        // When confirmed → find riders and broadcast delivery alert
        if ($request->status === 'preparing') {
            $this->broadcastToRiders($order);
        }

        return response()->json(['message' => 'Order status updated', 'order' => $order]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT ITEM
    |--------------------------------------------------------------------------
    */
    public function rejectItem(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $user      = $request->user();
        $orderItem = OrderItem::with('order.restaurant', 'menuItem')->findOrFail($id);

        if ($orderItem->order->restaurant->id !== $user->restaurant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = $orderItem->order;

        DB::beginTransaction();

        try {
            $orderItem->status        = 'rejected';
            $orderItem->reject_reason = $request->reason;
            $orderItem->save();

            $activeItems = $order->items()->where('status', '!=', 'rejected')->get();

            if ($activeItems->count() === 0) {
                $order->status       = 'cancelled';
                $order->subtotal     = 0;
                $order->delivery_fee = 0;
                $order->total_price  = 0;
            } else {
                $newSubtotal        = $activeItems->sum(fn($i) => $i->price * $i->quantity);
                $order->subtotal    = $newSubtotal;
                $order->total_price = $newSubtotal + $order->delivery_fee;
                $order->status      = 'needs_customer_action';
            }

            $order->save();

            Notification::create([
                'user_id' => $order->user_id,
                'title'   => '⚠️ Item Unavailable',
                'message' => "{$orderItem->menuItem->name} is no longer available. Please review your order.",
                'type'    => 'order_update',
                'data'    => ['order_id' => $order->id, 'order_item_id' => $orderItem->id],
            ]);

            DB::commit();

            broadcast(new OrderStatusUpdated($order));

            return response()->json([
                'message'      => 'Item rejected successfully',
                'order_status' => $order->status,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to reject item', 'error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    // Save a notification to the customer's notification list
    private function notifyCustomer(Order $order): void
    {
        $message = self::STATUS_MESSAGES[$order->status] ?? null;
        if (!$message) return;

        $titles = [
            'confirmed'  => '✅ Order Confirmed!',
            'preparing'  => '👨‍🍳 Being Prepared',
            'on_the_way' => '🛵 On the Way!',
            'delivered'  => '🎉 Order Delivered!',
            'cancelled'  => '❌ Order Cancelled',
        ];

        Notification::create([
            'user_id' => $order->user_id,
            'title'   => $titles[$order->status] ?? 'Order Update',
            'message' => $message,
            'type'    => 'order_status',
            'data'    => ['order_id' => $order->id, 'status' => $order->status],
        ]);
    }

    // Broadcast new delivery to nearby riders — all riders after 30s fallback
    private function broadcastToRiders(Order $order): void
    {
        $order->load('restaurant');
        $restaurant = $order->restaurant;

        if (!$restaurant->latitude || !$restaurant->longitude) {
            Log::warning("[Rider] Restaurant #{$restaurant->id} has no coordinates — broadcasting to all riders");
            BroadcastToAllRiders::dispatch($order->id);
            return;
        }

        $nearbyRiders = Rider::findAvailable(
            (float) $restaurant->latitude,
            (float) $restaurant->longitude,
            5 // km radius
        );

        if ($nearbyRiders->isNotEmpty()) {
            $riderIds = $nearbyRiders->pluck('id')->toArray();
            broadcast(new NewDeliveryAvailable($order, $riderIds));
            Log::info("[Rider] Broadcasted to {$nearbyRiders->count()} nearby riders for order #{$order->id}");
        } else {
            // No nearby riders — broadcast to everyone after 30 seconds
            BroadcastToAllRiders::dispatch($order->id)->delay(now()->addSeconds(30));
            Log::info("[Rider] No nearby riders for order #{$order->id} — will broadcast to all in 30s");
        }
    }
}
