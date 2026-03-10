<?php
namespace App\Events;

use App\Models\Order;
use App\Models\Rider;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    public int   $userId;

    public function __construct(Order $order)
    {
        $this->userId  = $order->user_id;
        $this->payload = [
            'order_id'       => $order->id,
            'status'         => 'delivered',
            'total_price'    => $order->total_price,
            'delivered_at'   => now()->toISOString(),
            'restaurant_name'=> $order->restaurant->name,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->userId}")];
    }

    public function broadcastAs(): string { return 'OrderStatusUpdated'; } // reuse same event name so customer app picks it up automatically
    public function broadcastWith(): array { return $this->payload; }
}
