<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mini Order Management API  v1
|--------------------------------------------------------------------------
|
| Rate limiters:
|   auth     — 10 req/min by IP  (brute-force protection)
|   products — 60 req/min by user/IP
|   orders   — 20 req/min by user/IP
|
*/

Route::prefix('v1')->group(function () {

    // ── Auth ────────────────────────────────────────────────────────────────
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);
        });
    });

    // ── Products (public read, admin write) ─────────────────────────────────
    Route::prefix('products')->middleware('throttle:products')->group(function () {
        Route::get('/',          [ProductController::class, 'index']);
        Route::get('/{product}', [ProductController::class, 'show']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/',             [ProductController::class, 'store']);
            Route::put('/{product}',     [ProductController::class, 'update']);
            Route::patch('/{product}',   [ProductController::class, 'update']);
            Route::delete('/{product}',  [ProductController::class, 'destroy']);
        });
    });

    // ── Orders (authenticated only) ─────────────────────────────────────────
    Route::prefix('orders')
        ->middleware(['auth:sanctum', 'throttle:orders'])
        ->group(function () {
            Route::post('/',         [OrderController::class, 'store']);
            Route::get('/',          [OrderController::class, 'index']);
            Route::get('/{order}',   [OrderController::class, 'show']);
        });
});
