<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;

class MenuItemController extends Controller
{
    public function getByRestaurant($id)
    {
        $menuItems = MenuItem::with('category') // 🔥 IMPORTANT
            ->where('restaurant_id', $id)
            ->get();

        return response()->json($menuItems);
    }
}
