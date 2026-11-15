<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', EnsureAdmin::class])->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
    });

    Route::get('/user', fn (Request $request) => $request->user());
});
