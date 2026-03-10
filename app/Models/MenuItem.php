<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'price',
        'image',
        'is_available',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    // ── NEW: modifier groups for this item ───────────────────────────────────
    public function modifierGroups()
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order');
    }

    // Helper: does this item have any modifier groups?
    public function hasModifiers(): bool
    {
        return $this->modifierGroups()->exists();
    }
}
