<?php

namespace App\Jobs;

use App\Events\NewDeliveryAvailable;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BroadcastToAllRiders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        // If already has a rider — someone nearby accepted, skip
        if (!$order || $order->rider_id !== null) {
            Log::info("[Rider] Order #{$this->orderId} already has a rider — skipping all-broadcast");
            return;
        }

        // Get ALL available riders
        $allRiders = Rider::where('status', 'approved')
            ->where('is_online', true)
            ->where('is_available', true)
            ->pluck('id')
            ->toArray();

        if (empty($allRiders)) {
            Log::warning("[Rider] No available riders at all for order #{$this->orderId}");
            return;
        }

        broadcast(new NewDeliveryAvailable($order, $allRiders));
        $count = count($allRiders);
        Log::info("[Rider] Broadcasted order #{$this->orderId} to ALL {$count} riders");
    }
}
