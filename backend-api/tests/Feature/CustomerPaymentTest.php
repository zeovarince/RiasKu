<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Keranjang;
use App\Models\KeranjangItem;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pelanggan = Pelanggan::create([
        'username' => 'surya_buyer',
        'email' => 'surya_buyer@example.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->admin = Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@riasku.com',
        'password_hash' => bcrypt('admin123'),
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

    $this->varian = ProdukVarian::create([
        'produk_id' => $this->produk->id,
        'name' => 'Natural 01',
        'stock' => 10,
        'is_active' => true,
    ]);
});

test('PPL-20: COD checkout outside Surabaya is strictly rejected with 422', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Malioboro No. 12',
        'shipping_city' => 'Kota Yogyakarta',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_city'])
        ->assertJsonFragment([
            'message' => 'Metode pembayaran COD hanya tersedia untuk pengiriman wilayah Kota Surabaya.',
        ]);
});

test('PPL-20: COD checkout within Surabaya succeeds and sets order to diproses', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Nadia Safitri',
        'customer_phone' => '081398765432',
        'shipping_address' => 'Jl. Manyar Kertajaya No. 45',
        'shipping_city' => 'Kota Surabaya',
        'shipping_district' => 'Gubeng',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'order' => ['id', 'order_code', 'customer_name', 'total_amount', 'order_status'],
                'payment' => ['id', 'payment_type', 'gross_amount', 'instructions'],
            ],
        ]);

    expect($response->json('data.order.order_status'))->toBe('diproses');
    expect($response->json('data.order.payment_status'))->toBe('menunggu_pembayaran_cod');
    expect($response->json('data.order.total_amount'))->toBe(370000);
});

test('PPL-19: Online checkout with QRIS generates QR code and payment instructions', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Siti Nurhaliza',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Dharmawangsa No. 20',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('data.payment.payment_type'))->toBe('qris');
    expect($response->json('data.payment.qr_string'))->not->toBeNull();
    expect($response->json('data.payment.qr_url'))->not->toBeNull();
    expect($response->json('data.payment.instructions'))->toBeArray()->not->toBeEmpty();
});

test('PPL-19: Online checkout with BCA Virtual Account generates VA number', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Megan Utami',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Pemuda No. 10',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'bca_va',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('data.payment.payment_type'))->toBe('bank_transfer');
    expect($response->json('data.payment.bank'))->toBe('bca');
    expect($response->json('data.payment.va_number'))->toStartWith('12890');
});

test('PPL-19: Online checkout with Mandiri generates bill key and biller code', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Rika Maulana',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Basuki Rahmat No. 5',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'mandiri_va',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('data.payment.payment_type'))->toBe('echannel');
    expect($response->json('data.payment.biller_code'))->toBe('70012');
    expect($response->json('data.payment.bill_key'))->not->toBeNull();
});

test('PPL-19: Local Simulator marks payment as settlement and order as diproses', function () {
    Sanctum::actingAs($this->pelanggan);

    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Megan Utami',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Pemuda No. 10',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'bca_va',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $paymentId = $orderResponse->json('data.payment.id');

    $simulateResponse = $this->postJson("/api/payments/{$paymentId}/simulate-pay");
    $simulateResponse->assertStatus(200);

    expect($simulateResponse->json('data.payment.transaction_status'))->toBe('settlement');
    expect($simulateResponse->json('data.order.payment_status'))->toBe('dibayar');
    expect($simulateResponse->json('data.order.order_status'))->toBe('diproses');
});

test('PPL-20: Admin can confirm COD payment receipt via confirm-cod-payment', function () {
    // Buat pesanan COD terlebih dahulu
    Sanctum::actingAs($this->pelanggan);
    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Nadia Safitri',
        'customer_phone' => '081398765432',
        'shipping_address' => 'Jl. Manyar Kertajaya No. 45',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);
    $orderId = $orderResponse->json('data.order.id');

    // Admin login dan klik "Uang Tunai Diterima"
    Sanctum::actingAs($this->admin);
    $confirmResponse = $this->postJson("/api/admin/orders/{$orderId}/confirm-cod-payment");

    $confirmResponse->assertStatus(200);
    expect($confirmResponse->json('data.payment_status'))->toBe('dibayar');
    expect($confirmResponse->json('data.order_status'))->toBe('selesai');
    expect($confirmResponse->json('data.latest_pembayaran.transaction_status'))->toBe('settlement');
});

test('Public user can track order by order code', function () {
    Sanctum::actingAs($this->pelanggan);
    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Siti Nurhaliza',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Dharmawangsa No. 20',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 1,
            ],
        ],
    ]);
    $orderCode = $orderResponse->json('data.order.order_code');

    // Public guest user tracking
    $trackResponse = $this->getJson("/api/orders/{$orderCode}/track");
    $trackResponse->assertStatus(200)
        ->assertJsonFragment([
            'order_code' => $orderCode,
            'customer_name' => 'Siti Nurhaliza',
            'shipping_city' => 'Kota Surabaya',
            'payment_channel' => 'qris',
        ]);
});
