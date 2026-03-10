<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OtpController extends Controller
{
    // POST /otp/send
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        // ✅ Find user by phone instead of $request->user() (route is now public)
        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'No account found with this phone number'], 404);
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save to user, expires in 10 minutes
        $user->update([
            'otp_code'       => $otp,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // ─── DEV MODE: log it instead of sending SMS ───────────────
        Log::info("OTP for {$request->phone}: {$otp}");

        // ─── PRODUCTION: swap the line above with your SMS provider ─
        // SmsService::send($request->phone, "Your TachiGo OTP is: {$otp}");

        return response()->json([
            'message' => 'OTP sent successfully',
        ]);
    }

    // POST /otp/verify
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        // ✅ Find user by phone instead of $request->user()
        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'No account found with this phone number'], 404);
        }

        // Check expired
        if (!$user->otp_expires_at || Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired'], 422);
        }

        // Check match
        if ($user->otp_code !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        // Mark phone as verified, clear OTP
        $user->update([
            'phone_verified_at' => Carbon::now(),
            'otp_code'          => null,
            'otp_expires_at'    => null,
        ]);

        return response()->json([
            'message' => 'Phone verified successfully',
            'user'    => $user->fresh(),
        ]);
    }
}
