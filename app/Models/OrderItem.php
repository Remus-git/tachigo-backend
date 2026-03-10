<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'price',
        'status',
        'reject_reason',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    // ── NEW: selected modifiers for this order item ──────────────────────────
    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
