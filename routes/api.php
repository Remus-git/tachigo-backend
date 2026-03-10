<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\RestaurantController;
use App\Http\Controllers\API\ShopRestaurantController;
use App\Http\Controllers\API\ShopCategoryController;
use App\Http\Controllers\API\ShopMenuController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\ShopOrderController;
use App\Http\Controllers\API\MenuItemController;
use App\Http\Controllers\API\RiderController;
use App\Http\Controllers\RestaurantAnalyticsController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\Api\RestaurantStatusController;
use App\Http\Controllers\API\OtpController;
use App\Http\Controllers\API\PushTokenController;
use App\Http\Controllers\API\ModifierController;
use App\Http\Controllers\API\RatingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC ROUTES
// ─────────────────────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ✅ OTP moved here — user is not authenticated yet during phone verification
Route::post('/otp/send',   [OtpController::class, 'send']);
Route::post('/otp/verify', [OtpController::class, 'verify']);

Route::get('/restaurants',           [RestaurantController::class, 'index']);
Route::get('/restaurants/{id}',      [RestaurantController::class, 'show']);
Route::get('/restaurants/{id}/menu', [MenuItemController::class,   'getByRestaurant']);

// ─────────────────────────────────────────────────────────────────────────────
// AUTHENTICATED ROUTES
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── User profile ─────────────────────────────────────────────────────────
    Route::get('/user/profile',         [UserController::class, 'getProfile']);
    Route::put('/user/profile',         [UserController::class, 'updateProfile']);
    Route::put('/user/change-password', [UserController::class, 'changePassword']);
    Route::put('/user/location',        [UserController::class, 'updateLocation']);
    Route::post('/user/location',       [UserController::class, 'updateLocation']);

    // ── Menu item modifiers (customer-facing) ─────────────────────────────────
    Route::get('/menu-items/{menuItemId}/modifiers', function ($menuItemId) {
        $item = \App\Models\MenuItem::with(['modifierGroups.options' => function ($q) {
            $q->where('is_available', true);
        }])->findOrFail($menuItemId);
        return response()->json($item->modifierGroups);
    });

    // ── Cart ─────────────────────────────────────────────────────────────────
    Route::get('/cart',           [CartController::class, 'viewCart']);
    Route::post('/cart/add',      [CartController::class, 'addToCart']);
    Route::post('/cart/decrease', [CartController::class, 'decreaseQuantity']);
    Route::post('/cart/remove',   [CartController::class, 'removeFromCart']);
    Route::post('/cart/clear',    [CartController::class, 'clear']);

    // ── Customer orders ───────────────────────────────────────────────────────
    Route::post('/order',                        [OrderController::class, 'placeOrder']);
    Route::get('/order/{orderId}/track',         [OrderController::class, 'trackOrder']);
    Route::post('/orders/{id}/approve-modified', [OrderController::class, 'approveModified']);
    Route::post('/orders/{id}/cancel',           [OrderController::class, 'cancelOrder']);
    Route::get('/order/{id}/rider-location',     [OrderController::class, 'riderLocation']);
    Route::get('/my-orders', function (Request $request) {
        return response()->json(
            $request->user()->orders()->with('items.menuItem', 'restaurant')->latest()->get()
        );
    });

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/notifications',                 [NotificationController::class, 'index']);
    Route::post('/notifications/send',           [NotificationController::class, 'store']);
    Route::post('/notifications/mark-all-read',  [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);

    // ── Restaurant (shop owner) ───────────────────────────────────────────────
    Route::post('/shop/restaurant',                            [ShopRestaurantController::class, 'store']);
    Route::get('/restaurant/profile',                         [ShopRestaurantController::class, 'show']);
    Route::put('/restaurant/profile/{id}',                    [ShopRestaurantController::class, 'update']);
    Route::delete('/restaurant/profile/{id}',                 [ShopRestaurantController::class, 'destroy']);
    Route::post('/restaurant/create',                         [ShopRestaurantController::class, 'store']);
    Route::patch('/restaurant/{restaurant}/manual-close',     [RestaurantStatusController::class, 'manualClose']);
    Route::get('/restaurant/analytics',                       [RestaurantAnalyticsController::class, 'index']);

    // ── Shop menu & categories ────────────────────────────────────────────────
    Route::get('/shop/menu',         [ShopMenuController::class, 'index']);
    Route::post('/shop/menu',        [ShopMenuController::class, 'store']);
    Route::put('/shop/menu/{id}',    [ShopMenuController::class, 'update']);
    Route::delete('/shop/menu/{id}', [ShopMenuController::class, 'destroy']);
    Route::post('/shop/category',    [ShopCategoryController::class, 'store']);
    Route::get('/shop/getCategory',  [ShopCategoryController::class, 'index']);

    // ── Modifier groups ───────────────────────────────────────────────────────
    Route::get('/shop/menu/{menuItemId}/modifiers',       [ModifierController::class, 'index']);
    Route::post('/shop/menu/{menuItemId}/modifiers',      [ModifierController::class, 'store']);
    Route::put('/shop/menu/modifiers/{groupId}',          [ModifierController::class, 'update']);
    Route::delete('/shop/menu/modifiers/{groupId}',       [ModifierController::class, 'destroy']);

    // ── Modifier options ──────────────────────────────────────────────────────
    Route::post('/shop/menu/modifiers/{groupId}/options',    [ModifierController::class, 'addOption']);
    Route::put('/shop/menu/modifiers/options/{optionId}',    [ModifierController::class, 'updateOption']);
    Route::delete('/shop/menu/modifiers/options/{optionId}', [ModifierController::class, 'destroyOption']);

    // ── Shop orders ───────────────────────────────────────────────────────────
    Route::get('/shop/orders',                   [ShopOrderController::class, 'incomingOrders']);
    Route::post('/shop/orders/{orderId}/status', [ShopOrderController::class, 'updateStatus']);
    Route::post('/shop/order-items/{id}/reject', [ShopOrderController::class, 'rejectItem']);

    // ── Rider ─────────────────────────────────────────────────────────────────
    Route::prefix('rider')->group(function () {
        Route::post('/register',            [RiderController::class, 'register']);
        Route::get('/profile',              [RiderController::class, 'profile']);
        Route::post('/online',              [RiderController::class, 'setOnline']);
        Route::post('/location',            [RiderController::class, 'updateLocation']);
        Route::get('/earnings',             [RiderController::class, 'earnings']);

        // Orders — active must come before {id} to avoid route conflicts
        Route::get('/orders/active',        [RiderController::class, 'activeOrder']);
        Route::get('/orders/available',     [RiderController::class, 'availableOrders']);
        Route::post('/orders/{id}/accept',  [RiderController::class, 'acceptOrder']);
        Route::post('/orders/{id}/pickup',  [RiderController::class, 'pickupOrder']);
        Route::post('/orders/{id}/deliver', [RiderController::class, 'deliverOrder']);
    });

    Route::post('/ratings',                [RatingController::class, 'store']);
    Route::get('/ratings/check/{orderId}', [RatingController::class, 'check']);
    Route::get('/shop/ratings',            [RatingController::class, 'shopRatings']);
    Route::get('/rider/ratings',           [RatingController::class, 'riderRatings']);

    // ── Broadcasting auth ─────────────────────────────────────────────────────
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

    // ── Push tokens ───────────────────────────────────────────────────────────
    Route::post('/push-token', [PushTokenController::class, 'store']);
    Route::get('/pos/history', [App\Http\Controllers\API\PosController::class, 'history']);
    Route::get('/pos/menu',  [App\Http\Controllers\API\PosController::class, 'getMenu']);
Route::post('/pos/order', [App\Http\Controllers\API\PosController::class, 'createOrder']);
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN ROUTES
// ─────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // Shops
    Route::get('/shops',                 [AdminController::class, 'shops']);
    Route::post('/shops/{id}/approve',   [AdminController::class, 'approveShop']);
    Route::post('/shops/{id}/reject',    [AdminController::class, 'rejectShop']);
    Route::post('/shops/{id}/suspend',   [AdminController::class, 'suspendShop']);
    Route::post('/shops/{id}/reinstate', [AdminController::class, 'reinstateShop']);

    // Orders
    Route::get('/orders', [AdminController::class, 'orders']);

    // Users
    Route::get('/users',              [AdminController::class, 'users']);
    Route::post('/users/{id}/ban',    [AdminController::class, 'banUser']);
    Route::post('/users/{id}/unban',  [AdminController::class, 'unbanUser']);

    // Riders
    Route::get('/riders',               [AdminController::class, 'riders']);
    Route::post('/riders/{id}/approve', [AdminController::class, 'approveRider']);
    Route::post('/riders/{id}/suspend', [AdminController::class, 'suspendRider']);
});
