<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['restaurant_id', 'name'];

public function restaurant()
{
    return $this->belongsTo(Restaurant::class);
}

public function menuItems()
{
    return $this->hasMany(MenuItem::class);
}

}
