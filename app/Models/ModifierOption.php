<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierOption extends Model
{
    protected $fillable = [
        'modifier_group_id',
        'name',
        'additional_price',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'additional_price' => 'float',
        'is_available'     => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
