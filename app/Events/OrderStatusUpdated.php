<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Private channel per customer — only that user's app listens.
     * Channel name: orders.{user_id}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    /**
     * Event name the frontend listens for.
     */
    public function broadcastAs(): string
    {
        return 'OrderStatusUpdated';
    }

    /**
     * Payload sent to the frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id'         => $this->order->id,
            'status'           => $this->order->status,
            'total_price'      => $this->order->total_price,
            'subtotal'         => $this->order->subtotal,
            'delivery_fee'     => $this->order->delivery_fee,
            'restaurant_name'  => $this->order->restaurant?->name,
        ];
    }
}
