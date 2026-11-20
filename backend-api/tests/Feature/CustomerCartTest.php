<?php

use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pelanggan = Pelanggan::create([
        'username' => 'surya_test',
        'email' => 'surya@example.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->kategori = Kategori::create([
        'name' => 'Complexion',
        'slug' => 'complexion',
        'icon' => 'complexion',
    ]);

    $this->produk = Produk::create([
        'kategori_id' => $this->kategori->id,
        'sku' => 'RSK-CUSH-01',
        'name' => 'Velvet Matte Cushion SPF 35',
        'brand' => 'RiasKu',
        'base_price' => 183000,
        'retail_price' => 185000,
        'is_preorder' => false,
        'is_active' => true,
    ]);

    $this->varian1 = ProdukVarian::create([
        'produk_id' => $this->produk->id,
        'name' => 'Natural 01',
        'stock' => 8,
        'is_active' => true,
    ]);

    $this->varian2 = ProdukVarian::create([
        'produk_id' => $this->produk->id,
        'name' => 'Warm 02',
        'stock' => 12,
        'is_active' => true,
    ]);
});

test('unauthenticated guest cannot access cart api', function () {
    $this->getJson('/api/cart')->assertStatus(401);
    $this->postJson('/api/cart/items', [])->assertStatus(401);
});

test('authenticated customer can fetch empty cart', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->getJson('/api/cart');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'items',
            ],
        ]);

    expect($response->json('data.items'))->toBeArray()->toBeEmpty();
});

test('customer can add item to cart with variant and quantity', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian1->id,
        'quantity' => 2,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'cart_id',
                'product_id',
                'variant_id',
                'quantity',
                'unit_price',
                'product' => ['id', 'name', 'price', 'sku'],
                'variant' => ['id', 'name', 'stock'],
            ],
        ])
        ->assertJsonFragment([
            'product_id' => $this->produk->id,
            'variant_id' => $this->varian1->id,
            'quantity' => 2,
            'unit_price' => 185000,
        ]);

    expect($response->json('data.product.name'))->toBe('Velvet Matte Cushion SPF 35');
    expect($response->json('data.variant.name'))->toBe('Natural 01');
});

test('adding duplicate item to cart increments its quantity', function () {
    Sanctum::actingAs($this->pelanggan);

    $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian1->id,
        'quantity' => 2,
    ])->assertStatus(201);

    $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian1->id,
        'quantity' => 3,
    ])->assertStatus(201);

    $cartResponse = $this->getJson('/api/cart');
    $cartResponse->assertStatus(200);

    $items = $cartResponse->json('data.items');
    expect($items)->toHaveCount(1);
    expect($items[0]['quantity'])->toBe(5);
});

test('customer can update cart item quantity within stock limits', function () {
    Sanctum::actingAs($this->pelanggan);

    $addResponse = $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian1->id,
        'quantity' => 2,
    ]);

    $itemId = $addResponse->json('data.id');

    // Update to 4 (within stock of 8)
    $updateResponse = $this->patchJson("/api/cart/items/{$itemId}", [
        'quantity' => 4,
    ]);

    $updateResponse->assertStatus(200)
        ->assertJsonFragment([
            'id' => $itemId,
            'quantity' => 4,
        ]);

    // Update exceeding stock of 8 should return 422
    $overStockResponse = $this->patchJson("/api/cart/items/{$itemId}", [
        'quantity' => 99,
    ]);

    $overStockResponse->assertStatus(422);
});

test('customer can remove an item and clear cart', function () {
    Sanctum::actingAs($this->pelanggan);

    $item1 = $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian1->id,
        'quantity' => 1,
    ])->json('data.id');

    $item2 = $this->postJson('/api/cart/items', [
        'product_id' => $this->produk->id,
        'variant_id' => $this->varian2->id,
        'quantity' => 1,
    ])->json('data.id');

    // Remove item 1
    $deleteResponse = $this->deleteJson("/api/cart/items/{$item1}");
    $deleteResponse->assertStatus(200);

    $cartResponse = $this->getJson('/api/cart');
    expect($cartResponse->json('data.items'))->toHaveCount(1);
    expect($cartResponse->json('data.items.0.id'))->toBe($item2);

    // Clear entire cart
    $clearResponse = $this->deleteJson('/api/cart');
    $clearResponse->assertStatus(200);

    $emptyCartResponse = $this->getJson('/api/cart');
    expect($emptyCartResponse->json('data.items'))->toBeEmpty();
});
