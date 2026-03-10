<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CartItem;
use App\Models\User;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function items()
{
    return $this->hasMany(CartItem::class);
}

public function user()
{
    return $this->belongsTo(User::class);
}

}
