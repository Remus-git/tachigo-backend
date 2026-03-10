<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        PushToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $request->token],
            ['last_used_at' => now()]
        );

        return response()->json(['ok' => true]);
    }
}
