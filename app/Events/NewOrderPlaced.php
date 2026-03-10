<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Private channel per restaurant.
     * Channel name: restaurant.{restaurant_id}
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->order->restaurant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewOrderPlaced';
    }

    public function broadcastWith(): array
    {
        // Load relations if not already loaded
        $this->order->loadMissing(['items.menuItem', 'user']);

        return [
            'id'               => $this->order->id,
            'status'           => $this->order->status,
            'total_price'      => $this->order->total_price,
            'subtotal'         => $this->order->subtotal,
            'delivery_fee'     => $this->order->delivery_fee,
            'delivery_address' => $this->order->delivery_address,
            'phone'            => $this->order->phone,
            'note'             => $this->order->note,
            'created_at'       => $this->order->created_at,
            'user' => [
                'id'        => $this->order->user?->id,
                'name'      => $this->order->user?->name,
                'latitude'  => $this->order->latitude,
                'longitude' => $this->order->longitude,
            ],
            'items' => $this->order->items->map(fn($item) => [
                'id'        => $item->id,
                'quantity'  => $item->quantity,
                'price'     => $item->price,
                'status'    => $item->status,
                'menu_item' => ['name' => $item->menuItem?->name],
            ])->toArray(),
        ];
    }
}
