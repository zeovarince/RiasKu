<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin QA',
        'email' => 'admin-qa34@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->customer = Pelanggan::create([
        'username' => 'customer_qa34',
        'email' => 'customer-qa34@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $category = Kategori::create([
        'name' => 'Lip Care',
        'slug' => 'lip-care',
        'icon' => 'lip',
    ]);

    $this->product = Produk::create([
        'kategori_id' => $category->id,
        'sku' => 'RSK-QA34-LIP',
        'name' => 'QA Tinted Lip Oil',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 70000,
        'retail_price' => 95000,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->variant = ProdukVarian::create([
        'produk_id' => $this->product->id,
        'name' => 'Peach Glow',
        'stock' => 10,
        'is_active' => true,
    ]);

    $this->zone = ZonaPengiriman::create([
        'province' => 'Jawa Timur',
        'city' => 'Surabaya',
        'district' => 'Gubeng',
        'postal_code' => '60281',
        'is_active' => true,
    ]);

    TarifPengiriman::create([
        'zona_pengiriman_id' => $this->zone->id,
        'courier' => 'JNE',
        'service' => 'OKE',
        'price_per_kg' => 8000,
        'etd_min_days' => 2,
        'etd_max_days' => 3,
        'is_active' => true,
    ]);
});

test('PPL-19 QA #34: local simulation preserves e-wallet payment_type for gopay and shopeepay instead of mislabeling as qris', function () {
    Sanctum::actingAs($this->customer);

    $gopayRes = $this->postJson('/api/orders', [
        'customer_name' => 'QA Ewallet GoPay',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Gubeng No. 1',
        'shipping_city' => 'Surabaya',
        'destination_zone_id' => $this->zone->id,
        'courier' => 'JNE',
        'service' => 'OKE',
        'payment_method' => 'online',
        'payment_channel' => 'gopay',
        'items' => [
            ['produk_id' => $this->product->id, 'produk_varian_id' => $this->variant->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect($gopayRes->json('data.payment.payment_type'))->toBe('gopay');

    $orderId = $gopayRes->json('data.order.id');
    $shopeeRes = $this->postJson("/api/orders/{$orderId}/payments", ['channel' => 'shopeepay'])
        ->assertOk();

    expect($shopeeRes->json('data.payment_type'))->toBe('shopeepay');
});

test('PPL-20/21 QA #34: updating order status to dikirim with existing shipping_courier does not duplicate courier_service', function () {
    Sanctum::actingAs($this->customer);

    $orderRes = $this->postJson('/api/checkout', [
        'customer_name' => 'QA COD Buyer',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Dharmawangsa No. 15',
        'shipping_city' => 'Surabaya',
        'destination_zone_id' => $this->zone->id,
        'courier' => 'JNE',
        'service' => 'OKE',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            ['produk_id' => $this->product->id, 'produk_varian_id' => $this->variant->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    $orderCode = $orderRes->json('data.order.order_code');
    $initialCourier = $orderRes->json('data.order.shipping_courier');
    expect($initialCourier)->toBe('Kurir Toko RiasKu (COD) / JNE OKE');

    Sanctum::actingAs($this->admin);
    $updateRes = $this->patchJson("/api/admin/orders/{$orderCode}/status", [
        'status' => 'dikirim',
        'shipping_courier' => $initialCourier,
    ])->assertOk();

    expect($updateRes->json('data.shipping_courier'))->toBe('Kurir Toko RiasKu (COD) / JNE OKE')
        ->and($updateRes->json('data.courier'))->toBe('JNE');

    $trackRes = $this->getJson("/api/orders/{$orderCode}/track")->assertOk();
    expect($trackRes->json('data.shipping_courier'))->toBe('Kurir Toko RiasKu (COD) / JNE OKE');
});

test('KatalogSeeder populates all 10 products with public /images/products/ primary photos', function () {
    $this->seed(\Database\Seeders\KatalogSeeder::class);

    $seededSkus = [
        'RSK-CUSH-01',
        'RSK-LIP-04',
        'RSK-POWD-02',
        'RSK-BRV-010',
        'RSK-PO-01',
        'Kahf-001',
        'BRB-001',
        'RSK-EYE-01',
        'RSK-WIS-01',
        'RSK-BRSH-01',
    ];

    foreach ($seededSkus as $sku) {
        $product = Produk::where('sku', $sku)->with('primaryFoto')->first();
        expect($product)->not->toBeNull()
            ->and($product->image_url)->toStartWith('/images/products/');
    }
});
