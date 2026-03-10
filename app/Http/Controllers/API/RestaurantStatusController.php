<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestaurantStatusController extends Controller
{
    public function manualClose(Request $request, Restaurant $restaurant)
    {
        // Make sure the authenticated user owns this restaurant
        /** @var \App\Models\User $user */
$user = Auth::user();
if ($restaurant->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'is_manually_closed' => 'required|boolean',
        ]);

        $isManuallyClosed = $request->boolean('is_manually_closed');

        $restaurant->update([
            'is_manually_closed' => $isManuallyClosed,
            'is_open'            => !$isManuallyClosed, // immediately flip
        ]);

        return response()->json([
            'message'            => $isManuallyClosed ? 'Restaurant closed.' : 'Restaurant re-opened.',
            'is_open'            => $restaurant->is_open,
            'is_manually_closed' => $restaurant->is_manually_closed,
        ]);
    }
}
