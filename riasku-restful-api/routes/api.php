<?php

use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Middleware\EnsurePelanggan;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
    Route::post('/google', [CustomerAuthController::class, 'google']);
});

Route::prefix('auth')->middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
});
