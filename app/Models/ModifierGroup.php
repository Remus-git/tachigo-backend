<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    protected $fillable = [
        'menu_item_id',
        'name',
        'required',
        'min_selections',
        'max_selections',
        'sort_order',
    ];

    protected $casts = [
        'required'       => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order'     => 'integer',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order');
    }
}
