<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Rider;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    // ── POST /ratings  — submit rating after delivery ─────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'order_id'    => 'required|integer|exists:orders,id',
            'shop_rating' => 'required|integer|min:1|max:5',
            'rider_rating'=> 'required|integer|min:1|max:5',
            'comment'     => 'nullable|string|max:500',
        ]);

        $order = Order::with('restaurant')->findOrFail($request->order_id);

        // Must be the customer who placed the order
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Order must be delivered
        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'Order not delivered yet'], 422);
        }

        // One rating per order
        if (Rating::where('order_id', $order->id)->exists()) {
            return response()->json(['message' => 'Already rated'], 409);
        }

        if (!$order->rider_id) {
            return response()->json(['message' => 'No rider assigned to this order'], 422);
        }

        $rating = Rating::create([
            'order_id'    => $order->id,
            'customer_id' => $request->user()->id,
            'shop_id'     => $order->restaurant_id,
            'rider_id'    => $order->rider_id,
            'shop_rating' => $request->shop_rating,
            'rider_rating'=> $request->rider_rating,
            'comment'     => $request->comment,
        ]);

        // Update restaurant average rating
        $shopAvg = Rating::where('shop_id', $order->restaurant_id)->avg('shop_rating');
        Restaurant::where('id', $order->restaurant_id)->update([
            'rating'       => round($shopAvg, 2),
            'rating_count' => Rating::where('shop_id', $order->restaurant_id)->count(),
        ]);

        // Update rider average rating
        $riderAvg = Rating::where('rider_id', $order->rider_id)->avg('rider_rating');
        Rider::where('id', $order->rider_id)->update([
            'rating'       => round($riderAvg, 2),
            'rating_count' => Rating::where('rider_id', $order->rider_id)->count(),
        ]);

        return response()->json([
            'message' => 'Thank you for your rating!',
            'rating'  => $rating,
        ], 201);
    }

    // ── GET /ratings/check/{orderId}  — has customer already rated? ───────────
    public function check(Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rating = Rating::where('order_id', $orderId)->first();

        return response()->json([
            'rated'  => (bool) $rating,
            'rating' => $rating,
        ]);
    }

    // ── GET /shop/ratings  — shop owner sees their ratings ────────────────────
    public function shopRatings(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant) return response()->json(['message' => 'No restaurant found'], 404);

        $ratings = Rating::where('shop_id', $restaurant->id)
            ->with('customer:id,name', 'order:id,created_at')
            ->latest()
            ->paginate(20);

        $average = Rating::where('shop_id', $restaurant->id)->avg('shop_rating');
        $count   = Rating::where('shop_id', $restaurant->id)->count();

        return response()->json([
            'average'     => round($average, 1),
            'total'       => $count,
            'breakdown'   => $this->breakdown('shop_id', $restaurant->id, 'shop_rating'),
            'ratings'     => $ratings,
        ]);
    }

    // ── GET /rider/ratings  — rider sees their own ratings ────────────────────
    public function riderRatings(Request $request)
    {
        $rider = $request->user()->rider;
        if (!$rider) return response()->json(['message' => 'Not a rider'], 404);

        $ratings = Rating::where('rider_id', $rider->id)
            ->with('order:id,created_at')
            ->latest()
            ->paginate(20);

        $average = Rating::where('rider_id', $rider->id)->avg('rider_rating');
        $count   = Rating::where('rider_id', $rider->id)->count();

        return response()->json([
            'average'   => round($average, 1),
            'total'     => $count,
            'breakdown' => $this->breakdown('rider_id', $rider->id, 'rider_rating'),
            'ratings'   => $ratings,
        ]);
    }

    // ── Helper: star breakdown (5★: 10, 4★: 3 ...) ───────────────────────────
    private function breakdown(string $col, int $id, string $ratingCol): array
    {
        $rows = Rating::where($col, $id)
            ->select($ratingCol, DB::raw('count(*) as total'))
            ->groupBy($ratingCol)
            ->pluck('total', $ratingCol)
            ->toArray();

        $out = [];
        for ($i = 5; $i >= 1; $i--) {
            $out[$i] = $rows[$i] ?? 0;
        }
        return $out;
    }
}
