<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'shop_id',
        'rider_id',
        'shop_rating',
        'rider_rating',
        'comment',
    ];

    protected $casts = [
        'shop_rating'  => 'integer',
        'rider_rating' => 'integer',
    ];

    public function order()    { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function shop()     { return $this->belongsTo(Restaurant::class, 'shop_id'); }
    public function rider()    { return $this->belongsTo(Rider::class); }
}
