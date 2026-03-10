<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Storage;

class ShopMenuController extends Controller
{
    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        $menu = $restaurant->menuItems()
            ->with('category')
            ->latest()
            ->get();

        // Add full image URLs to each item
        $menu->transform(function ($item) {
            if ($item->image) {
                $item->image_url = asset('storage/' . $item->image);
            } else {
                $item->image_url = null;
            }
            return $item;
        });

        return response()->json($menu);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'boolean',
        ]);

        $restaurant = $request->user()->restaurant;

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('menu', 'public');
        }

        $menuItem = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'category_id'   => $validated['category_id'],
            'name'          => $validated['name'],
            'description'   => $validated['description'],
            'price'         => $validated['price'],
            'image'         => $imagePath,
            'is_available'  => $validated['is_available'] ?? true,
        ]);

        // Load category relationship and add image URL
        $menuItem->load('category');
        if ($menuItem->image) {
            $menuItem->image_url = asset('storage/' . $menuItem->image);
        }

        return response()->json($menuItem, 201);
    }

    public function update(Request $request, $id)
    {
        $restaurant = $request->user()->restaurant;
        $menuItem = $restaurant->menuItems()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'boolean',
        ]);

        // Handle new image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
                Storage::disk('public')->delete($menuItem->image);
            }

            $validated['image'] = $request->file('image')->store('menu', 'public');
        }

        $menuItem->update($validated);

        // Load category relationship and add image URL
        $menuItem->load('category');
        if ($menuItem->image) {
            $menuItem->image_url = asset('storage/' . $menuItem->image);
        } else {
            $menuItem->image_url = null;
        }

        return response()->json($menuItem);
    }

    public function destroy(Request $request, $id)
    {
        $restaurant = $request->user()->restaurant;
        $menuItem = $restaurant->menuItems()->findOrFail($id);

        // Delete image file if exists
        if ($menuItem->image && Storage::disk('public')->exists($menuItem->image)) {
            Storage::disk('public')->delete($menuItem->image);
        }

        $menuItem->delete();

        return response()->json(['message' => 'Menu item deleted successfully']);
    }
}
