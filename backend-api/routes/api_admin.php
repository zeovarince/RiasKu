<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\KategoriController;
use App\Http\Controllers\Api\Admin\ProdukController;
use App\Http\Controllers\Api\Admin\ProdukFotoController;
use App\Http\Controllers\Api\Admin\ProdukVarianController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Http\Request;
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

    // Kategori
    Route::get('/kategori', [KategoriController::class, 'index']);
    Route::post('/kategori', [KategoriController::class, 'store']);
    Route::get('/kategori/{kategori}', [KategoriController::class, 'show']);
    Route::put('/kategori/{kategori}', [KategoriController::class, 'update']);
    Route::delete('/kategori/{kategori}', [KategoriController::class, 'destroy']);

    // Produk
    Route::get('/produk', [ProdukController::class, 'index']);
    Route::post('/produk', [ProdukController::class, 'store']);
    Route::get('/produk/{produk}', [ProdukController::class, 'show']);
    Route::put('/produk/{produk}', [ProdukController::class, 'update']);
    Route::patch('/produk/{produk}/status', [ProdukController::class, 'updateStatus']);

    // Varian
    Route::post('/produk/{produk}/varian', [ProdukVarianController::class, 'store']);
    Route::put('/varian/{varian}', [ProdukVarianController::class, 'update']);
    Route::patch('/varian/{varian}', [ProdukVarianController::class, 'update']);
    Route::patch('/varian/{varian}/status', [ProdukVarianController::class, 'updateStatus']);

    // Foto
    Route::post('/produk/{produk}/foto', [ProdukFotoController::class, 'store']);
    Route::patch('/foto/{foto}', [ProdukFotoController::class, 'update']);
    Route::delete('/foto/{foto}', [ProdukFotoController::class, 'destroy']);

    // Aliases
    Route::get('/categories', [KategoriController::class, 'index']);
    Route::post('/categories', [KategoriController::class, 'store']);
    Route::put('/categories/{kategori}', [KategoriController::class, 'update']);
    Route::delete('/categories/{kategori}', [KategoriController::class, 'destroy']);
    Route::get('/products', [ProdukController::class, 'index']);
    Route::post('/products', [ProdukController::class, 'store']);
    Route::get('/products/{produk}', [ProdukController::class, 'show']);
    Route::put('/products/{produk}', [ProdukController::class, 'update']);
    Route::patch('/products/{produk}/status', [ProdukController::class, 'updateStatus']);
});
