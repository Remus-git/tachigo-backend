<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Rider;
use App\Events\NewDeliveryAvailable;
use App\Events\RiderAcceptedOrder;
use App\Events\RiderLocationUpdated;
use App\Events\OrderDelivered;
use App\Events\OrderStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;

class RiderController extends Controller
{
    // ─── Register as rider ────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'phone'          => 'required|string',
            'vehicle_type'   => 'required|in:motorcycle,bicycle,car',
            'vehicle_plate'  => 'nullable|string',
            'id_card_number' => 'nullable|string',
        ]);

        $existing = Rider::where('user_id', $request->user()->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Already registered', 'rider' => $existing]);
        }

        $rider = Rider::create([
            'user_id'        => $request->user()->id,
            'phone'          => $request->phone,
            'vehicle_type'   => $request->vehicle_type,
            'vehicle_plate'  => $request->vehicle_plate,
            'id_card_number' => $request->id_card_number,
            'status'         => 'pending',
        ]);

        return response()->json([
            'message' => 'Registration submitted. Awaiting admin approval.',
            'rider'   => $rider,
        ], 201);
    }

    // ─── Get rider profile ────────────────────────────────────────────────────
    public function profile(Request $request)
    {
        $rider = $request->user()->rider;
        if (!$rider) return response()->json(['message' => 'Not a rider'], 404);

        return response()->json([
            'rider'        => $rider,
            'active_order' => $rider->activeOrder()?->load('restaurant', 'user', 'items.menuItem'),
        ]);
    }

    // ─── Go online / offline ──────────────────────────────────────────────────
    public function setOnline(Request $request)
    {
        $rider = $this->approvedRider($request);

        $request->validate([
            'is_online' => 'required|boolean',
            'latitude'  => 'required_if:is_online,true|nullable|numeric',
            'longitude' => 'required_if:is_online,true|nullable|numeric',
        ]);

        $updateData = [
            'is_online'    => $request->is_online,
            'last_seen_at' => now(),
        ];

        if ($request->is_online) {
            $hasActiveOrder = Order::where('rider_id', $rider->id)
                ->whereIn('status', ['confirmed', 'preparing', 'ready', 'on_the_way'])
                ->exists();

            $updateData['is_available'] = !$hasActiveOrder;

            if ($request->latitude && $request->longitude) {
                $updateData['current_latitude']  = $request->latitude;
                $updateData['current_longitude'] = $request->longitude;
            }
        } else {
            $updateData['is_available'] = false;
        }

        $rider->update($updateData);

        return response()->json([
            'message'      => $request->is_online ? 'You are now online' : 'You are now offline',
            'is_online'    => $rider->is_online,
            'is_available' => $rider->is_available,
        ]);
    }

    // ─── Update rider GPS location ────────────────────────────────────────────
    public function updateLocation(Request $request)
    {
        $rider = $this->approvedRider($request);

        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'order_id'  => 'nullable|integer',
        ]);

        $rider->update([
            'current_latitude'  => $request->latitude,
            'current_longitude' => $request->longitude,
            'last_seen_at'      => now(),
        ]);

        if ($request->order_id) {
            $order = Order::find($request->order_id);
            if ($order && $order->rider_id === $rider->id) {
                broadcast(new RiderLocationUpdated(
                    $order->id,
                    $order->restaurant->user_id,
                    $request->latitude,
                    $request->longitude,
                    $rider->id,
                ))->toOthers();
            }
        }

        return response()->json(['ok' => true]);
    }

    // ─── Get active order (called on app resume) ──────────────────────────────
    public function activeOrder(Request $request)
    {
        $rider = $request->user()->rider;
        if (!$rider) return response()->json(['order' => null], 404);

        $order = Order::where('rider_id', $rider->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready', 'on_the_way'])
            ->with(['items.menuItem', 'items.modifiers', 'restaurant', 'user'])
            ->latest()
            ->first();

        if (!$order) return response()->json(['order' => null], 404);

        return response()->json([
            'order'          => $order,
            'rider_earnings' => $order->rider_earnings ?? $order->rider_pay ?? 0,
        ]);
    }

    // ─── Accept a delivery ────────────────────────────────────────────────────
    public function acceptOrder(Request $request, int $orderId)
    {
        $rider = $this->approvedRider($request);

        // Check if rider already has a different active order
        $existingActive = Order::where('rider_id', $rider->id)
            ->whereIn('status', ['confirmed', 'preparing', 'ready', 'on_the_way'])
            ->where('id', '!=', $orderId)
            ->with(['items.menuItem', 'restaurant', 'user'])
            ->first();

        if ($existingActive) {
            return response()->json([
                'message'        => 'You already have an active delivery.',
                'active_order'   => $existingActive,
                'rider_earnings' => $existingActive->rider_earnings ?? $existingActive->rider_pay ?? 0,
            ], 409);
        }

        // Reset is_available in case of stale state
        DB::table('riders')->where('id', $rider->id)->update(['is_available' => true]);
        $rider->refresh();

        $result = DB::transaction(function () use ($orderId, $rider) {
            $order = Order::lockForUpdate()->find($orderId);

            if (!$order) return ['status' => 'not_found'];
            if ($order->rider_id !== null && $order->rider_id !== $rider->id) return ['status' => 'taken'];
            if (!in_array($order->status, ['confirmed', 'preparing'])) return ['status' => 'taken'];

            // Already accepted by this same rider — idempotent
            if ($order->rider_id === $rider->id) return ['status' => 'ok'];

            $distanceKm = Rider::distanceKm(
                $rider->current_latitude,
                $rider->current_longitude,
                $order->latitude,
                $order->longitude,
            );

            $earnings = Rider::calculateEarnings($distanceKm);

            DB::table('orders')->where('id', $orderId)->update([
                'rider_id'             => $rider->id,
                'rider_accepted_at'    => now(),
                'delivery_distance_km' => round($distanceKm, 2),
                'rider_earnings'       => $earnings,
            ]);

            DB::table('riders')->where('id', $rider->id)->update([
                'is_available' => false,
            ]);

            return ['status' => 'ok'];
        });

        if ($result['status'] === 'not_found') return response()->json(['message' => 'Order not found'], 404);
        if ($result['status'] === 'taken')     return response()->json(['message' => 'Order already taken'], 409);

        $order = Order::with(['restaurant', 'user', 'items.menuItem'])->find($orderId);

        Log::info('acceptOrder: assigned rider', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
        ]);

        broadcast(new RiderAcceptedOrder($order, $rider));

        Notification::create([
            'user_id' => $order->user_id,
            'title'   => '🛵 Rider Assigned!',
            'message' => "{$rider->user->name} is picking up your order #{$order->id}",
            'type'    => 'rider_assigned',
            'data'    => ['order_id' => $order->id, 'rider_id' => $rider->id],
        ]);

        PushNotificationService::sendToUser(
            userId: $order->user_id,
            title:  '🛵 Rider Assigned!',
            body:   "{$rider->user->name} is picking up your order #{$order->id}",
            data:   ['type' => 'rider_assigned', 'order_id' => $order->id, 'channel' => 'orders'],
        );

        return response()->json([
            'message'        => 'Order accepted!',
            'order'          => $order,
            'rider_earnings' => $order->rider_earnings,
            'distance_km'    => $order->delivery_distance_km,
        ]);
    }

    // ─── Mark order as picked up from restaurant ──────────────────────────────
    public function pickupOrder(Request $request, int $orderId)
    {
        $rider = $this->approvedRider($request);
        $order = Order::findOrFail($orderId);

        Log::info('pickupOrder called', [
            'order_id'       => $orderId,
            'order_rider_id' => $order->rider_id,
            'rider_id'       => $rider->id,
            'order_status'   => $order->status,
        ]);

        // Fix Eloquent caching bug — force assign if null
        if (is_null($order->rider_id)) {
            DB::table('orders')->where('id', $orderId)->update(['rider_id' => $rider->id]);
            $order->refresh();
        }

        if ($order->rider_id !== $rider->id) {
            return response()->json([
                'message' => 'This order does not belong to you.',
                'debug'   => [
                    'order_rider_id' => $order->rider_id,
                    'your_rider_id'  => $rider->id,
                ],
            ], 403);
        }

        if ($order->status === 'on_the_way') {
            return response()->json(['message' => 'Order already picked up.', 'order' => $order], 200);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'status'       => 'on_the_way',
            'picked_up_at' => now(),
        ]);

        $order->refresh();

        broadcast(new OrderStatusUpdated($order));

        Notification::create([
            'user_id' => $order->user_id,
            'title'   => '🛵 On the Way!',
            'message' => "Your order #{$order->id} has been picked up and is heading to you!",
            'type'    => 'order_status',
            'data'    => ['order_id' => $order->id, 'status' => 'on_the_way'],
        ]);

        PushNotificationService::sendToUser(
            userId: $order->user_id,
            title:  '🛵 On the Way!',
            body:   "Your order #{$order->id} has been picked up and is on its way!",
            data:   ['type' => 'order_status', 'order_id' => $order->id, 'channel' => 'orders'],
        );

        return response()->json(['message' => 'Order picked up', 'order' => $order]);
    }

    // ─── Mark order as delivered ──────────────────────────────────────────────
    public function deliverOrder(Request $request, int $orderId)
    {
        $rider = $this->approvedRider($request);
        $order = Order::findOrFail($orderId);

        if ($order->rider_id !== $rider->id) {
            return response()->json(['message' => 'This order does not belong to you.'], 403);
        }

        if ($order->status === 'delivered') {
            return response()->json([
                'message'      => 'Order already delivered.',
                'earned'       => $order->rider_earnings,
                'total_earned' => $rider->total_earnings,
            ], 200);
        }

        $order->load('restaurant');

        DB::table('orders')->where('id', $orderId)->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        DB::table('riders')->where('id', $rider->id)->update([
            'total_earnings'   => DB::raw('total_earnings + ' . ($order->rider_earnings ?? 0)),
            'total_deliveries' => DB::raw('total_deliveries + 1'),
            'is_available'     => true,
            'is_online'        => true,
        ]);

        $order->refresh();
        $rider->refresh();

        broadcast(new OrderDelivered($order));

        Notification::create([
            'user_id' => $order->user_id,
            'title'   => '🎉 Order Delivered!',
            'message' => "Your order #{$order->id} from {$order->restaurant->name} has arrived. Enjoy!",
            'type'    => 'order_status',
            'data'    => ['order_id' => $order->id, 'status' => 'delivered'],
        ]);

        PushNotificationService::sendToUser(
            userId: $order->user_id,
            title:  '🎉 Order Delivered!',
            body:   "Your order #{$order->id} from {$order->restaurant->name} has arrived!",
            data:   ['type' => 'order_status', 'order_id' => $order->id, 'channel' => 'orders'],
        );

        return response()->json([
            'message'      => 'Order delivered!',
            'earned'       => $order->rider_earnings,
            'total_earned' => $rider->total_earnings,
        ]);
    }

    // ─── Get available deliveries near rider ──────────────────────────────────
    public function availableOrders(Request $request)
    {
        $rider = $this->approvedRider($request);

        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $orders = Order::whereIn('status', ['confirmed', 'preparing'])
            ->whereNull('rider_id')
            ->with('restaurant', 'items.menuItem')
            ->latest()
            ->get()
            ->map(function ($order) use ($request) {
                $order->distance_km = round(Rider::distanceKm(
                    $request->latitude,
                    $request->longitude,
                    $order->latitude,
                    $order->longitude,
                ), 1);
                $order->estimated_earnings = Rider::calculateEarnings($order->distance_km);
                return $order;
            })
            ->sortBy('distance_km')
            ->values();

        return response()->json($orders);
    }

    // ─── Rider earnings history ───────────────────────────────────────────────
    public function earnings(Request $request)
    {
        $rider = $this->approvedRider($request);

        $today = Order::where('rider_id', $rider->id)->whereDate('delivered_at', today())->sum('rider_earnings');
        $week  = Order::where('rider_id', $rider->id)->whereBetween('delivered_at', [now()->startOfWeek(), now()])->sum('rider_earnings');
        $month = Order::where('rider_id', $rider->id)->whereMonth('delivered_at', now()->month)->sum('rider_earnings');

        $recent = Order::where('rider_id', $rider->id)
            ->where('status', 'delivered')
            ->with('restaurant')
            ->latest('delivered_at')
            ->take(20)
            ->get()
            ->map(fn($o) => [
                'order_id'     => $o->id,
                'restaurant'   => $o->restaurant->name,
                'earned'       => $o->rider_earnings,
                'distance_km'  => $o->delivery_distance_km,
                'delivered_at' => $o->delivered_at,
            ]);

        return response()->json([
            'total_earnings'   => $rider->total_earnings,
            'total_deliveries' => $rider->total_deliveries,
            'today'            => $today,
            'this_week'        => $week,
            'this_month'       => $month,
            'recent'           => $recent,
        ]);
    }

    // ─── Helper: get approved rider or abort ──────────────────────────────────
    private function approvedRider(Request $request): Rider
    {
        $rider = $request->user()->rider;
        abort_if(!$rider, 404, 'Rider profile not found');
        abort_if($rider->status !== 'approved', 403, 'Rider account not approved yet');
        return $rider;
    }
}
