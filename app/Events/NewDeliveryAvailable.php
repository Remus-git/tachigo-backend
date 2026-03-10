<?php
// ═══════════════════════════════════════════════════════════════════════════════
// EVENT 1: NewDeliveryAvailable
// Broadcast to riders when an order is ready for pickup
// Place in: app/Events/NewDeliveryAvailable.php
// ═══════════════════════════════════════════════════════════════════════════════
namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDeliveryAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(Order $order, array $riderIds)
    {
        $order->load('restaurant', 'items.menuItem');

        $this->payload = [
            'order_id'           => $order->id,
            'restaurant_name'    => $order->restaurant->name,
            'restaurant_address' => $order->restaurant->address ?? '',
            'restaurant_lat'     => $order->restaurant->latitude,
            'restaurant_lng'     => $order->restaurant->longitude,
            'delivery_address'   => $order->delivery_address,
            'customer_lat'       => $order->latitude,
            'customer_lng'       => $order->longitude,
            'total_price'        => $order->total_price,
            'delivery_fee'       => $order->delivery_fee,
            'items_count'        => $order->items->count(),
            'items_summary'      => $order->items->take(2)->map(fn($i) => $i->menuItem->name)->join(', '),
            'rider_ids'          => $riderIds, // only these riders should show the alert
        ];
    }

    // Broadcast on each rider's private channel
    public function broadcastOn(): array
    {
        return array_map(
            fn($id) => new PrivateChannel("rider.{$id}"),
            $this->payload['rider_ids']
        );
    }

    public function broadcastAs(): string { return 'NewDeliveryAvailable'; }
    public function broadcastWith(): array { return $this->payload; }
}
