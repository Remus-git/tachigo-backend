<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CartItemModifier;
use App\Models\MenuItem;
use App\Models\Cart;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'menu_item_id',
        'quantity',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function modifiers()
    {
        return $this->hasMany(CartItemModifier::class);
    }
}
