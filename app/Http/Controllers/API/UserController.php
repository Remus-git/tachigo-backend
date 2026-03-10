<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    // GET /user/profile
    public function getProfile(Request $request)
    {
        return response()->json($request->user());
    }

    // PUT /user/profile
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name'          => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'address'       => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender'        => 'nullable|in:male,female,other',
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->update($request->only([
            'name', 'phone', 'address', 'date_of_birth', 'gender'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user->fresh(),
        ]);
    }

    // PUT /user/change-password
    public function changePassword(Request $request)
    {
        $request->validate([
            'newPassword' => 'required|min:6',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->newPassword),
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    // PUT /user/location (your existing one, cleaned up)
    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'address'   => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        $user->update([
            'latitude'     => $request->latitude,
            'longitude'     => $request->longitude,
            'address' => $request->address,
        ]);

        return response()->json([
            'message' => 'Location updated successfully',
            'user'    => $user->fresh(),
        ]);
    }
}
