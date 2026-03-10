<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public static function sendToUser(
        int    $userId,
        string $title,
        string $body,
        array  $data  = [],
        string $sound = 'default',
    ): void {
        $tokens = PushToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return;

        self::send($tokens, $title, $body, $data, $sound);
    }

    public static function sendToUsers(
        array  $userIds,
        string $title,
        string $body,
        array  $data  = [],
        string $sound = 'default',
    ): void {
        $tokens = PushToken::whereIn('user_id', $userIds)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return;

        self::send($tokens, $title, $body, $data, $sound);
    }

    private static function send(
        array  $tokens,
        string $title,
        string $body,
        array  $data,
        string $sound,
    ): void {
        $chunks = array_chunk($tokens, 100);

        foreach ($chunks as $chunk) {
            $messages = array_map(fn($token) => [
                'to'        => $token,
                'title'     => $title,
                'body'      => $body,
                'data'      => $data,
                'sound'     => $sound,
                'priority'  => 'high',
                'channelId' => $data['channel'] ?? 'default',
            ], $chunk);

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])->post(self::EXPO_PUSH_URL, $messages);

                Log::info('[Push] Sent batch', [
                    'count'    => count($chunk),
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);

                $results = $response->json('data') ?? [];
                foreach ($results as $i => $result) {
                    if (
                        isset($result['status']) &&
                        $result['status'] === 'error' &&
                        isset($result['details']['error']) &&
                        in_array($result['details']['error'], ['DeviceNotRegistered', 'InvalidCredentials'])
                    ) {
                        PushToken::where('token', $chunk[$i])->delete();
                    }
                }
            } catch (\Exception $e) {
                Log::error('[Push] Failed to send', ['error' => $e->getMessage()]);
            }
        }
    }
}
