<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
});
Route::middleware(['auth', 'shop'])->prefix('shop')->group(function () {
    Route::get('/dashboard', function () {
        return view('shop.dashboard');
    });
});
Route::get('/admin/restaurants/{id}/approve', [AdminController::class, 'approveRestaurant']);

