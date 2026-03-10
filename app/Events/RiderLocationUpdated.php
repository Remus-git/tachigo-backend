<?php
namespace App\Events;

use App\Models\Order;
use App\Models\Rider;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    public int   $restaurantId;

    public function __construct(int $orderId, int $restaurantId, float $lat, float $lng, int $riderId)
    {
        $this->restaurantId = $restaurantId;
        $this->payload = [
            'order_id'  => $orderId,
            'rider_id'  => $riderId,
            'latitude'  => $lat,
            'longitude' => $lng,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->restaurantId}")];
    }

    public function broadcastAs(): string { return 'RiderLocationUpdated'; }
    public function broadcastWith(): array { return $this->payload; }

    // Don't queue location updates — fire immediately
    public function broadcastQueue(): string { return 'location'; }
}
