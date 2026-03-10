<?php
namespace App\Events;

use App\Models\Order;
use App\Models\Rider;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderAcceptedOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(Order $order, Rider $rider)
    {
        $this->payload = [
            'order_id'          => $order->id,
            'rider_id'          => $rider->id,
            'rider_name'        => $rider->user->name,
            'rider_phone'       => $rider->phone,
            'rider_vehicle'     => $rider->vehicle_type,
            'rider_plate'       => $rider->vehicle_plate,
            'rider_lat'         => $rider->current_latitude,
            'rider_lng'         => $rider->current_longitude,
            'rider_rating'      => $rider->rating,
            'estimated_pickup'  => now()->addMinutes(5)->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("restaurant.{$this->payload['order_id']}")];
        // Note: we use order_id as a lookup — controller will use restaurant_id
    }

    public function broadcastAs(): string { return 'RiderAcceptedOrder'; }
    public function broadcastWith(): array { return $this->payload; }
}
