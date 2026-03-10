<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class ShopRestaurantController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string',
            'category'              => 'required|string',
            'description'           => 'nullable|string',
            'delivery_fee'          => 'required|numeric',
            'minimum_order_amount'  => 'required|numeric',
            'phone_number'          => 'nullable|string',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'image'                 => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'open_time'          => 'required|date_format:H:i:s',
            'close_time'         => 'required|date_format:H:i:s|after:open_time',
            'preparation_time'   => 'required|integer|min:1|max:180',
        ]);

        $restaurant = new Restaurant();
        $restaurant->user_id              = $request->user()->id;
        $restaurant->name                 = $request->name;
        $restaurant->category             = $request->category;
        $restaurant->description          = $request->description;
        $restaurant->delivery_fee         = $request->delivery_fee;
        $restaurant->minimum_order_amount = $request->minimum_order_amount;
        $restaurant->phone_number         = $request->phone_number;
        $restaurant->is_open              = false;   // closed until approved
        $restaurant->is_approved          = false;
        $restaurant->latitude             = $request->latitude;
        $restaurant->longitude            = $request->longitude;
        $restaurant->open_time            = $request->open_time;
        $restaurant->close_time           = $request->close_time;
        $restaurant->preparation_time     = $request->preparation_time;


        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/restaurants'), $imageName);
            $restaurant->image = $imageName;
        }

        $restaurant->save();

        return response()->json([
            'message'    => 'Restaurant submitted. Waiting for admin approval.',
            'restaurant' => $restaurant,
        ]);
    }

    public function show(Request $request)
    {
        $restaurant = Restaurant::where('user_id', $request->user()->id)->first();
        if (!$restaurant) return response()->json(['message' => 'Restaurant not found'], 404);
        return response()->json($restaurant);
    }

    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);

        $request->validate([
            'name'                 => 'required|string',
            'category'             => 'required|string',
            'description'          => 'nullable|string',
            'delivery_fee'         => 'required|numeric',
            'minimum_order_amount' => 'required|numeric',
            'phone_number'         => 'nullable|string',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
            'image'                => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
             'open_time'          => 'required|date_format:H:i:s',
            'close_time'         => 'required|date_format:H:i:s|after:open_time',
            'preparation_time'   => 'required|integer|min:1|max:180',
        ]);

        $restaurant->name                 = $request->name;
        $restaurant->category             = $request->category;
        $restaurant->description          = $request->description;
        $restaurant->delivery_fee         = $request->delivery_fee;
        $restaurant->minimum_order_amount = $request->minimum_order_amount;
        $restaurant->phone_number         = $request->phone_number;
        $restaurant->latitude             = $request->latitude;
        $restaurant->longitude            = $request->longitude;
        $restaurant->open_time            = $request->open_time;
        $restaurant->close_time           = $request->close_time;
        $restaurant->preparation_time     = $request->preparation_time;

        if ($request->hasFile('image')) {
            if ($restaurant->image && file_exists(public_path('images/restaurants/' . $restaurant->image))) {
                unlink(public_path('images/restaurants/' . $restaurant->image));
            }
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/restaurants'), $imageName);
            $restaurant->image = $imageName;
        }

        $restaurant->save();

        return response()->json([
            'message'    => 'Restaurant updated successfully.',
            'restaurant' => $restaurant,
        ]);
    }

    public function destroy($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        if ($restaurant->image && file_exists(public_path('images/restaurants/' . $restaurant->image))) {
            unlink(public_path('images/restaurants/' . $restaurant->image));
        }
        $restaurant->delete();
        return response()->json(['message' => 'Restaurant deleted successfully.']);
    }
}
