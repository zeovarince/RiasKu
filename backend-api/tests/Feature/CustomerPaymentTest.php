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
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

// ═══════════════════════════════════════════════════════════════════
// TEST BARU: Midtrans Core API Integration (HTTP Fake)
// ═══════════════════════════════════════════════════════════════════

test('PPL-19 Midtrans: Real charge QRIS via Midtrans Core API with HTTP fake', function () {
    // Set env keys agar MidtransService masuk mode real
    config(['services.midtrans.server_key' => 'SB-Mid-server-TEST123']);
    config(['services.midtrans.client_key' => 'SB-Mid-client-TEST123']);
    config(['services.midtrans.base_url' => 'https://api.sandbox.midtrans.com']);

    // Fake response Midtrans Core API /v2/charge untuk QRIS
    Http::fake([
        'api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'status_message' => 'Success, QRIS transaction is created',
            'transaction_id' => 'MIDTRANS-TRX-QRIS-001',
            'order_id' => 'RSK-20261119-ABCDE',
            'gross_amount' => '185000.00',
            'payment_type' => 'qris',
            'transaction_status' => 'pending',
            'transaction_time' => '2026-11-19 10:00:00',
            'expiry_time' => '2026-11-19 10:15:00',
            'qr_string' => '00020101021226680016COM.GO-JEK.WWW0218ID1021800000013900208RIASKU01',
            'acquirer' => 'gopay',
            'actions' => [
                [
                    'name' => 'generate-qr-code',
                    'method' => 'GET',
                    'url' => 'https://api.sandbox.midtrans.com/v2/gopay/MIDTRANS-TRX-QRIS-001/qr-code',
                ],
            ],
        ], 201),
    ]);

    Sanctum::actingAs($this->pelanggan);

    // Rebind MidtransService agar baca config baru
    app()->forgetInstance(MidtransService::class);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Test Midtrans QRIS',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Test QRIS No. 1',
        'shipping_city' => 'Surabaya',
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
    expect($response->json('data.payment.transaction_id'))->toBe('MIDTRANS-TRX-QRIS-001');
    expect($response->json('data.payment.payment_type'))->toBe('qris');
    expect($response->json('data.payment.qr_string'))->toContain('COM.GO-JEK');
    expect($response->json('data.payment.qr_url'))->toContain('qr-code');

    // Verify HTTP request was sent to Midtrans
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.sandbox.midtrans.com/v2/charge')
            && $request['payment_type'] === 'qris';
    });
});

test('PPL-19 Midtrans: Real charge BCA VA via Midtrans Core API with HTTP fake', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-TEST123']);
    config(['services.midtrans.base_url' => 'https://api.sandbox.midtrans.com']);

    Http::fake([
        'api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'status_message' => 'Success, Bank Transfer transaction is created',
            'transaction_id' => 'MIDTRANS-TRX-BCA-001',
            'order_id' => 'RSK-20261119-BCAVA',
            'gross_amount' => '185000.00',
            'payment_type' => 'bank_transfer',
            'transaction_status' => 'pending',
            'expiry_time' => '2026-11-20 10:00:00',
            'va_numbers' => [
                ['bank' => 'bca', 'va_number' => '12890081234567890'],
            ],
        ], 201),
    ]);

    Sanctum::actingAs($this->pelanggan);
    app()->forgetInstance(MidtransService::class);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Test Midtrans BCA',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Test BCA No. 2',
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
    expect($response->json('data.payment.transaction_id'))->toBe('MIDTRANS-TRX-BCA-001');
    expect($response->json('data.payment.payment_type'))->toBe('bank_transfer');
    expect($response->json('data.payment.bank'))->toBe('bca');
    expect($response->json('data.payment.va_number'))->toBe('12890081234567890');
});

test('PPL-19 Midtrans: Webhook verifies signature and updates status to settlement', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-TEST123']);

    // Buat pesanan dulu via simulasi lokal
    Sanctum::actingAs($this->pelanggan);
    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Test Webhook User',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Webhook No. 3',
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

    $orderCode = $orderResponse->json('data.order.order_code');
    $orderId = $orderResponse->json('data.order.id');

    // Hitung signature key yang valid
    $statusCode = '200';
    $grossAmount = '185000.00';
    $serverKey = 'SB-Mid-server-TEST123';
    $validSignature = hash('sha512', $orderCode . $statusCode . $grossAmount . $serverKey);

    // Rebind agar verifySignature gunakan key baru
    app()->forgetInstance(MidtransService::class);

    // Kirim webhook settlement dengan signature valid
    $webhookResponse = $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $validSignature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'MIDTRANS-TRX-WEBHOOK-001',
        'payment_type' => 'bank_transfer',
    ]);

    $webhookResponse->assertStatus(200);

    // Verifikasi database diupdate
    $order = Pesanan::find($orderId);
    expect($order->payment_status)->toBe('dibayar');
    expect($order->order_status)->toBe('diproses');

    $payment = Pembayaran::where('pesanan_id', $orderId)->latest()->first();
    expect($payment->transaction_status)->toBe('settlement');
    expect($payment->paid_at)->not->toBeNull();
});

test('PPL-19 Midtrans: Webhook rejects invalid signature with 403', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-TEST123']);

    Sanctum::actingAs($this->pelanggan);
    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Test Sig Fail',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Invalid Sig No. 4',
        'shipping_city' => 'Surabaya',
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
    app()->forgetInstance(MidtransService::class);

    // Kirim webhook dengan signature PALSU
    $webhookResponse = $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '200',
        'gross_amount' => '185000.00',
        'signature_key' => 'fake_invalid_signature_hash',
        'transaction_status' => 'settlement',
    ]);

    $webhookResponse->assertStatus(403);
});

test('PPL-19 Midtrans: Sync status endpoint works in simulation mode', function () {
    Sanctum::actingAs($this->pelanggan);

    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Test Sync',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Sync Test No. 5',
        'shipping_city' => 'Surabaya',
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

    // Sync status saat mode simulasi lokal (server key kosong)
    $syncResponse = $this->postJson("/api/payments/{$orderCode}/sync-status");
    $syncResponse->assertStatus(200);
    expect($syncResponse->json('data.synced'))->toBeFalse();
    expect($syncResponse->json('data.midtrans_mode'))->toBe('local_simulation');
});

test('PPL-19 Midtrans: Sync status endpoint detects settlement from Midtrans', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-TEST123']);
    config(['services.midtrans.base_url' => 'https://api.sandbox.midtrans.com']);

    Sanctum::actingAs($this->pelanggan);
    app()->forgetInstance(MidtransService::class);

    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Test Sync Real',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Sync Real No. 6',
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

    $orderCode = $orderResponse->json('data.order.order_code');
    $orderId = $orderResponse->json('data.order.id');

    // Fake Midtrans GET status endpoint → settlement
    Http::fake([
        "api.sandbox.midtrans.com/v2/{$orderCode}/status" => Http::response([
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '185000.00',
            'order_id' => $orderCode,
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'MIDTRANS-TRX-SYNC-001',
        ], 200),
    ]);

    app()->forgetInstance(MidtransService::class);

    $syncResponse = $this->postJson("/api/payments/{$orderCode}/sync-status");
    $syncResponse->assertStatus(200);
    expect($syncResponse->json('data.synced'))->toBeTrue();
    expect($syncResponse->json('data.midtrans_status'))->toBe('settlement');

    // Verifikasi database diupdate
    $order = Pesanan::find($orderId);
    expect($order->payment_status)->toBe('dibayar');
    expect($order->order_status)->toBe('diproses');
});

test('PPL-19: Webhook restores stock on expire/cancel/deny', function () {
    Sanctum::actingAs($this->pelanggan);

    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Test Stok Expire',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Expire No. 7',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 3,
            ],
        ],
    ]);

    $orderCode = $orderResponse->json('data.order.order_code');

    // Stok awal 10, dibeli 3, sisa harus 7
    $this->varian->refresh();
    expect($this->varian->stock)->toBe(7);

    // Kirim webhook expire (tanpa verifikasi signature karena server key kosong)
    $webhookResponse = $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '407',
        'gross_amount' => '555000.00',
        'signature_key' => '',
        'transaction_status' => 'expire',
    ]);

    $webhookResponse->assertStatus(200);

    // Stok harus kembali ke 10
    $this->varian->refresh();
    expect($this->varian->stock)->toBe(10);
});

test('PPL-19: Checkout rejected when stock insufficient', function () {
    Sanctum::actingAs($this->pelanggan);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Test Stok Habis',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Stok Habis No. 8',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->produk->id,
                'produk_varian_id' => $this->varian->id,
                'quantity' => 999, // Melebihi stok
            ],
        ],
    ]);

    $response->assertStatus(422);
});
