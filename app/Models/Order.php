<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\OrderItem;

class Order extends Model
{
    protected $fillable = [
    'user_id',
    'restaurant_id',
    'subtotal',
    'delivery_fee',
    'total_price',
    'delivery_address',
    'phone',
    'status',
    'latitude',
    'longitude',
    'note',
    'rider_id', 'rider_accepted_at', 'picked_up_at', 'delivered_at',
    'rider_earnings', 'delivery_distance_km',
    'order_type', 'customer_name', 'table_number',
];

public function user()
{
    return $this->belongsTo(User::class);
}

public function restaurant()
{
    return $this->belongsTo(Restaurant::class);
}

public function items()
{
    return $this->hasMany(OrderItem::class);
}
public function rider()
{
    return $this->belongsTo(Rider::class, 'rider_id');
}
}
