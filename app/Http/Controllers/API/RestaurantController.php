<?php

namespace App\Http\Controllers\API;
use App\Models\Restaurant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
 public function index(Request $request)
{
    $lat = $request->lat;
    $lng = $request->lng;

    $query = Restaurant::where('is_approved', true);

    if ($lat && $lng) {
        $query->selectRaw("
            *,
            (6371 * acos(
                cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude))
            )) AS distance
        ", [$lat, $lng, $lat])
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->having("distance", "<=", 10)
        ->orderBy("distance");
    }

    return $query->get();
}
public function show($id)
{
    return Restaurant::with('menuItems')->findOrFail($id);
}
}
