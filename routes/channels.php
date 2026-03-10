<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register the channel authorization callbacks that will be used when
| checking if an authenticated user has permission to listen to a channel.
|
*/

/**
 * Customer listens to their own order updates.
 * Channel: orders.{userId}
 * Only the matching authenticated user can subscribe.
 */
Broadcast::channel('orders.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Restaurant dashboard listens for new orders + customer actions.
 * Channel: restaurant.{restaurantId}
 * Only the user who OWNS that restaurant can subscribe.
 */
Broadcast::channel('restaurant.{restaurantId}', function ($user, $restaurantId) {
    return $user->restaurant && (int) $user->restaurant->id === (int) $restaurantId;
});
Broadcast::channel('rider.{riderId}', function ($user, $riderId) {
    $rider = $user->rider;
    return $rider && $rider->id === (int) $riderId && $rider->status === 'approved';
});
