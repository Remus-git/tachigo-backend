<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderModifiedByCustomer implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order, public string $action)
    {
        // $action = 'approved' | 'cancelled'
    }

    /**
     * Restaurant listens on their private channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->order->restaurant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderModifiedByCustomer';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'    => $this->order->id,
            'status'      => $this->order->status,
            'action'      => $this->action,
            'total_price' => $this->order->total_price,
            'subtotal'    => $this->order->subtotal,
        ];
    }
}
