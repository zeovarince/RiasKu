<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public user can list active categories', function () {
    $cat = Kategori::create([
        'name' => 'Lipstik',
        'slug' => 'lipstik',
        'icon' => 'lipstick',
    ]);

    $response = $this->getJson('/api/categories');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'slug', 'icon'],
            ],
        ])
        ->assertJsonFragment([
            'slug' => 'lipstik',
        ]);
});

test('public user can list active products with stock label and variants', function () {
    $cat = Kategori::create([
        'name' => 'Skincare',
        'slug' => 'skincare',
    ]);

    $prod = Produk::create([
        'kategori_id' => $cat->id,
        'sku' => 'SKIN-001',
        'name' => 'Gentle Sunscreen SPF 50',
        'brand' => 'RiasKu Essentials',
        'weight_grams' => 100,
        'base_price' => 50000,
        'retail_price' => 52000,
        'is_preorder' => false,
        'is_active' => true,
    ]);

    ProdukVarian::create([
        'produk_id' => $prod->id,
        'name' => '50ml',
        'stock' => 10,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/products');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'sku', 'retail_price', 'total_stock', 'stock_label'],
            ],
        ])
        ->assertJsonFragment([
            'name' => 'Gentle Sunscreen SPF 50',
            'stock_label' => 'Tersedia',
            'total_stock' => 10,
        ]);
});

test('public user can search and filter products by category', function () {
    $cat1 = Kategori::create(['name' => 'Complexion', 'slug' => 'complexion']);
    $cat2 = Kategori::create(['name' => 'Eye Makeup', 'slug' => 'eye-makeup']);

    Produk::create([
        'kategori_id' => $cat1->id,
        'sku' => 'CUSH-001',
        'name' => 'Velvet Matte Cushion',
        'brand' => 'RiasKu',
        'base_price' => 100000,
        'retail_price' => 102000,
        'is_active' => true,
    ]);

    Produk::create([
        'kategori_id' => $cat2->id,
        'sku' => 'LASH-001',
        'name' => 'Volume Mascara Waterproof',
        'brand' => 'RiasKu',
        'base_price' => 60000,
        'retail_price' => 62000,
        'is_active' => true,
    ]);

    // Search query
    $searchResponse = $this->getJson('/api/products?q=Cushion');
    $searchResponse->assertStatus(200);
    expect($searchResponse->json('data'))->toHaveCount(1);
    expect($searchResponse->json('data.0.name'))->toBe('Velvet Matte Cushion');

    // Category filter
    $catResponse = $this->getJson('/api/products?category=eye-makeup');
    $catResponse->assertStatus(200);
    expect($catResponse->json('data'))->toHaveCount(1);
    expect($catResponse->json('data.0.name'))->toBe('Volume Mascara Waterproof');
});

test('public user can view active product details with preorder status', function () {
    $cat = Kategori::create(['name' => 'Lipstik', 'slug' => 'lipstik']);

    $prod = Produk::create([
        'kategori_id' => $cat->id,
        'sku' => 'LIP-PO-01',
        'name' => 'Special Edition Tint',
        'brand' => 'RiasKu',
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_preorder' => true,
        'preorder_estimate_days' => 5,
        'is_active' => true,
    ]);

    ProdukVarian::create([
        'produk_id' => $prod->id,
        'name' => 'Rose Petal',
        'stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/products/{$prod->id}");

    $response->assertStatus(200)
        ->assertJsonFragment([
            'id' => $prod->id,
            'name' => 'Special Edition Tint',
            'is_preorder' => true,
            'preorder_estimate_days' => 5,
            'stock_label' => 'Pre-Order',
        ]);
});

test('inactive product returns 404 in public detail', function () {
    $cat = Kategori::create(['name' => 'Lainnya', 'slug' => 'lainnya']);

    $prod = Produk::create([
        'kategori_id' => $cat->id,
        'sku' => 'ARCH-001',
        'name' => 'Discontinued Item',
        'brand' => 'RiasKu',
        'base_price' => 30000,
        'retail_price' => 32000,
        'is_active' => false,
    ]);

    $response = $this->getJson("/api/products/{$prod->id}");
    $response->assertStatus(404);
});
