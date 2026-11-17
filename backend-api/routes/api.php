<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Middleware\EnsurePelanggan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Auth
Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [CustomerAuthController::class, 'register']);
    Route::post('/login', [CustomerAuthController::class, 'login']);
    Route::post('/google', [CustomerAuthController::class, 'google']);
});

// Public Catalog (Kategori & Produk)
Route::get('/categories', [KategoriController::class, 'index']);
Route::get('/kategori', [KategoriController::class, 'index']);

Route::get('/products', [ProdukController::class, 'index']);
Route::get('/produk', [ProdukController::class, 'index']);

Route::get('/products/{id}', [ProdukController::class, 'show']);
Route::get('/produk/{id}', [ProdukController::class, 'show']);

// Protected Customer Routes
Route::prefix('auth')->middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
});

// Public Tracking & Payment Webhook (PPL-19, PPL-20)
Route::get('/orders/{orderCode}/track', [OrderController::class, 'track']);
Route::post('/payments/midtrans-notification', [PaymentController::class, 'midtransNotification']);

Route::middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    // Orders & Checkout (PPL-19, PPL-20)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/checkout', function (Request $request) {
        if ($request->has('customer_name')) {
            return app(OrderController::class)->store($request);
        }
        return response()->json(['message' => 'Checkout berhasil', 'data' => []]);
    });
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/payments', [PaymentController::class, 'createIntent']);

    // Payments & Local Simulator (PPL-19)
    Route::get('/payments/{id}', [PaymentController::class, 'status']);
    Route::post('/payments/{id}/simulate-pay', [PaymentController::class, 'simulateSuccess']);

    // Remote Cart Endpoints (PPL-16)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
});
