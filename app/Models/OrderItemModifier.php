<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    protected $fillable = [
        'order_item_id',
        'modifier_option_id',
        'modifier_group_name',
        'modifier_option_name',
        'additional_price',
    ];

    protected $casts = [
        'additional_price' => 'float',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function option()
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }
}
