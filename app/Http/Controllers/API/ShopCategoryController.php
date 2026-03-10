<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Restaurant;


class ShopCategoryController extends Controller
{
   public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $category = Category::create([
        'name' => $request->name,
        'restaurant_id' => $request->user()->restaurant->id,
    ]);

    return response()->json($category);
}
 public function index(Request $request)
{
    $restaurant = $request->user()->restaurant; // assuming user belongsTo restaurant

    $categories = $restaurant->categories() // hasMany relationship
        ->latest()
        ->get();

    return response()->json($categories);
}
}
