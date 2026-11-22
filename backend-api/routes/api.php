<?php

use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Middleware\EnsurePelanggan;
use App\Services\ShippingCostService;
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

Route::get('/shipping/zones', [ShippingController::class, 'zones'])->middleware('throttle:60,1');

// Asisten Chat AI (PPL-28 - Public & Customer-Aware + Multi-Session History)
Route::prefix('ai')->group(function (): void {
    Route::get('/status', [AiAssistantController::class, 'status'])->middleware('throttle:ai-read');
    Route::post('/chat', [AiAssistantController::class, 'chat'])->middleware('throttle:ai-chat');
    Route::get('/history', [AiAssistantController::class, 'history'])->middleware('throttle:ai-read');
    Route::delete('/history', [AiAssistantController::class, 'clearHistory'])->middleware('throttle:ai-write');
    Route::get('/sessions', [AiAssistantController::class, 'sessions'])->middleware('throttle:ai-read');
    Route::patch('/sessions/{sessionToken}', [AiAssistantController::class, 'renameSession'])->middleware('throttle:ai-write');
    Route::delete('/sessions/{sessionToken}', [AiAssistantController::class, 'deleteSession'])->middleware('throttle:ai-write');
    Route::get('/suggestions', [AiAssistantController::class, 'suggestions'])->middleware('throttle:ai-read');
});

// Protected Customer Routes
Route::prefix('auth')->middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
});

// Public Tracking & Payment Webhooks (PPL-19, PPL-20)
Route::get('/orders/{orderCode}/track', [OrderController::class, 'track'])->middleware('throttle:order-track');

// Midtrans Webhook (URL resmi: POST /api/payments/midtrans/webhook)
Route::post('/payments/midtrans/webhook', [PaymentController::class, 'midtransWebhook']);
// Alias backward-compat untuk webhook lama
Route::post('/payments/midtrans-notification', [PaymentController::class, 'midtransWebhook']);

Route::middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    // Shipping Rates (PPL-17, PPL-18)
    Route::post('/shipping/rates', [ShippingController::class, 'rates']);

    // Orders & Checkout (PPL-17, 18, 19, 20)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/checkout', function (Request $request, ShippingCostService $shipping) {
        if ($request->has('destination_zone_id') && ! $request->has('payment_channel')) {
            return app(CheckoutController::class)->store($request, $shipping);
        }

        return app(OrderController::class)->store($request);
    });
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);
    Route::post('/orders/{id}/payments', [PaymentController::class, 'createIntent']);

    // Payments & Realtime Sync (PPL-19)
    Route::get('/payments/{id}', [PaymentController::class, 'status']);
    Route::post('/payments/{id}/simulate-pay', [PaymentController::class, 'simulateSuccess']);
    Route::post('/payments/{orderNumber}/sync-status', [PaymentController::class, 'syncStatus']);

    // Remote Cart Endpoints (PPL-16)
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'store']);
    Route::patch('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
});
