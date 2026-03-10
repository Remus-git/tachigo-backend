<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    protected $fillable = [
        'user_id', 'phone', 'vehicle_type', 'vehicle_plate',
        'id_card_number', 'profile_image', 'status',
        'is_online', 'is_available', 'last_seen_at',
        'current_latitude', 'current_longitude',
        'total_earnings', 'total_deliveries', 'rating', 'approved_at',
    ];

    protected $casts = [
        'is_online'         => 'boolean',
        'is_available'      => 'boolean',
        'total_earnings'    => 'float',
        'rating'            => 'float',
        'approved_at'       => 'datetime',
        'last_seen_at'      => 'datetime',
        'current_latitude'  => 'float',
        'current_longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    public function ratings()
{
    return $this->hasMany(Rating::class, 'rider_id');
}
    public function activeOrder()
    {
        return $this->orders()
            ->whereIn('status', ['confirmed', 'preparing', 'on_the_way'])
            ->whereNotNull('rider_id')
            ->latest()
            ->first();
    }

    // ─── Haversine distance in km ─────────────────────────────────────────────
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ─── Rider pay: ฿10/km, minimum ฿20 ─────────────────────────────────────
    public static function calculateEarnings(float $distanceKm): float
    {
        return max(20, round($distanceKm * 10, 2));
    }

    // ─── Find available riders within radius, nearby first ───────────────────
    public static function findAvailable(float $lat, float $lng, float $radiusKm = 5): \Illuminate\Support\Collection
    {
        return static::where('status', 'approved')
            ->where('is_online', true)
            ->where('is_available', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get()
            ->map(function ($rider) use ($lat, $lng) {
                $rider->distance_km = static::distanceKm(
                    $lat, $lng,
                    $rider->current_latitude,
                    $rider->current_longitude
                );
                return $rider;
            })
            ->filter(fn($r) => $r->distance_km <= $radiusKm)
            ->sortBy('distance_km')
            ->values();
    }
}
