<?php

use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\KategoriController;
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

Route::middleware(['auth:sanctum', EnsurePelanggan::class])->group(function (): void {
    Route::get('/orders', fn () => response()->json(['message' => 'Pesanan saya', 'data' => []]));
    Route::post('/checkout', fn () => response()->json(['message' => 'Checkout berhasil', 'data' => []]));

    // Remote Cart Endpoints
    Route::get('/cart', fn () => response()->json(['message' => 'Keranjang belanja', 'data' => ['items' => []]]));
    Route::post('/cart/items', fn (Request $request) => response()->json(['message' => 'Item ditambahkan ke keranjang server', 'data' => $request->all()]));
    Route::patch('/cart/items/{id}', fn (Request $request, $id) => response()->json(['message' => 'Item keranjang diperbarui', 'data' => array_merge(['id' => $id], $request->all())]));
    Route::delete('/cart/items/{id}', fn ($id) => response()->json(['message' => 'Item keranjang dihapus', 'data' => ['id' => $id]]));
    Route::delete('/cart', fn () => response()->json(['message' => 'Keranjang server dikosongkan', 'data' => []]));
});
