<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = [
    'user_id',
    'name',
    'description',
    'delivery_fee',
    'minimum_order_amount',
    'is_open',
    'open_time',
    'close_time',
    'preparation_time',
    'is_manually_closed',
    'is_approved',
    'phone_number',
    'image'
];
protected $casts = [
    'is_open'            => 'boolean',
    'is_manually_closed' => 'boolean',
    'preparation_time'   => 'integer',
];
    public function user()
{
    return $this->belongsTo(User::class);
}
    public function menuItems()
{
    return $this->hasMany(MenuItem::class);
}
public function categories()
{
    return $this->hasMany(Category::class);
}
public function orders()
{
    return $this->hasMany(Order::class);
}
public function ratings()
{
    return $this->hasMany(Rating::class, 'shop_id');
}
}
