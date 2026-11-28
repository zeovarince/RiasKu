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
use App\Models\StoreSettings;
use App\Models\TarifPengiriman;
use App\Models\UlasanProduk;
use App\Models\ZonaPengiriman;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin-s5@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->customer = Pelanggan::create([
        'username' => 'customer_s5',
        'email' => 'customer-s5@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->catLip = Kategori::create([
        'name' => 'Lip Care',
        'slug' => 'lip-care',
        'icon' => 'lip',
    ]);

    $this->catComp = Kategori::create([
        'name' => 'Complexion',
        'slug' => 'complexion',
        'icon' => 'complexion',
    ]);

    $this->prodLip = Produk::create([
        'kategori_id' => $this->catLip->id,
        'sku' => 'RSK-S5-LIP',
        'name' => 'Glaze Tinted Lip Oil',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 80000,
        'retail_price' => 95000,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->varLip = ProdukVarian::create([
        'produk_id' => $this->prodLip->id,
        'name' => 'Rose',
        'stock' => 3,
        'is_active' => true,
    ]);

    $this->prodComp = Produk::create([
        'kategori_id' => $this->catComp->id,
        'sku' => 'RSK-S5-CUSH',
        'name' => 'Velvet Matte Cushion',
        'brand' => 'RiasKu',
        'weight_grams' => 120,
        'base_price' => 150000,
        'retail_price' => 185000,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->varComp = ProdukVarian::create([
        'produk_id' => $this->prodComp->id,
        'name' => 'Natural 01',
        'stock' => 15,
        'is_active' => true,
    ]);
});

test('PPL-20: COD strictly rejects deceptive city strings containing surabaya like Bukan Surabaya', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Tester COD',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Raya Luar Kota No. 1',
        'shipping_city' => 'Bukan Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            [
                'produk_id' => $this->prodComp->id,
                'produk_varian_id' => $this->varComp->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_city']);
});

test('PPL-18/19: unified /api/checkout requires shipping zone and courier when active zones exist', function () {
    $zone = ZonaPengiriman::create([
        'province' => 'Jawa Timur',
        'city' => 'Sidoarjo',
        'district' => 'Waru',
        'postal_code' => '61256',
        'is_active' => true,
    ]);
    TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'price_per_kg' => 10000,
        'etd_min_days' => 1,
        'etd_max_days' => 2,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/checkout', [
        'customer_name' => 'Free Shipping Attacker',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Tanpa Ongkir No. 99',
        'shipping_city' => 'Sidoarjo',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->prodComp->id,
                'produk_varian_id' => $this->varComp->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['destination_zone_id', 'courier', 'service']);
});

test('PPL-19: MidtransService refuses silent mock fallback when production charge fails', function () {
    config([
        'services.midtrans.server_key' => 'Mid-server-PROD-TEST',
        'services.midtrans.is_production' => true,
        'services.midtrans.base_url' => 'https://api.midtrans.com',
    ]);

    Http::fake([
        'api.midtrans.com/v2/charge' => Http::response(['status_code' => '500', 'status_message' => 'Internal error'], 500),
    ]);

    app()->forgetInstance(MidtransService::class);
    $service = app(MidtransService::class);

    $order = Pesanan::create([
        'order_code' => 'RSK-PROD-FAIL-01',
        'pelanggan_id' => $this->customer->id,
        'customer_name' => 'Prod Buyer',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Prod No. 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'subtotal' => 95000,
        'shipping_cost' => 10000,
        'total_amount' => 105000,
    ]);

    expect(fn () => $service->charge($order, 'qris'))->toThrow(RuntimeException::class);
});

test('PPL-19: MidtransService rejects HTTP 200 payload with error status_code >= 400 in production', function () {
    config([
        'services.midtrans.server_key' => 'Mid-server-PROD-TEST',
        'services.midtrans.is_production' => true,
        'services.midtrans.base_url' => 'https://api.midtrans.com',
    ]);

    Http::fake([
        'api.midtrans.com/v2/charge' => Http::response([
            'status_code' => '406',
            'status_message' => 'Duplicate order ID',
        ], 200),
    ]);

    app()->forgetInstance(MidtransService::class);
    $service = app(MidtransService::class);

    $order = Pesanan::create([
        'order_code' => 'RSK-PROD-DUP-01',
        'pelanggan_id' => $this->customer->id,
        'customer_name' => 'Prod Buyer',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Prod No. 2',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'bca_va',
        'subtotal' => 95000,
        'shipping_cost' => 10000,
        'total_amount' => 105000,
    ]);

    expect(fn () => $service->charge($order, 'bca_va'))->toThrow(RuntimeException::class);
});

test('PPL-19: OrderController::store returns midtrans_mode in payment payload', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/orders', [
        'customer_name' => 'Mode Check',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Mode No. 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->prodComp->id,
                'produk_varian_id' => $this->varComp->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment.midtrans_mode', 'local_simulation');
});

test('PPL-19: createIntent creates new payment when channel changes and includes instructions on reuse', function () {
    Sanctum::actingAs($this->customer);

    $orderResponse = $this->postJson('/api/orders', [
        'customer_name' => 'Switch Channel',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Switch No. 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            [
                'produk_id' => $this->prodComp->id,
                'produk_varian_id' => $this->varComp->id,
                'quantity' => 1,
            ],
        ],
    ])->assertCreated();

    $orderId = $orderResponse->json('data.order.id');
    $firstPaymentId = $orderResponse->json('data.payment.id');

    // Reuse same channel -> same payment ID, must include instructions & midtrans_mode
    $reuseRes = $this->postJson("/api/orders/{$orderId}/payments", ['channel' => 'qris'])
        ->assertOk();
    expect($reuseRes->json('data.id'))->toBe($firstPaymentId)
        ->and($reuseRes->json('data.instructions'))->toBeArray()->not->toBeEmpty()
        ->and($reuseRes->json('data.midtrans_mode'))->toBe('local_simulation');

    // Switch channel to bca_va -> creates new bank_transfer payment
    $switchRes = $this->postJson("/api/orders/{$orderId}/payments", ['channel' => 'bca_va'])
        ->assertOk();
    expect($switchRes->json('data.id'))->not->toBe($firstPaymentId)
        ->and($switchRes->json('data.payment_type'))->toBe('bank_transfer')
        ->and($switchRes->json('data.bank'))->toBe('bca');
});

test('PPL-16: CheckoutController legacy path also clears customer cart after checkout', function () {
    $zone = ZonaPengiriman::create(['province' => 'Jawa Timur', 'city' => 'Surabaya', 'is_active' => true]);
    TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'price_per_kg' => 8000,
        'etd_min_days' => 1,
        'etd_max_days' => 2,
        'is_active' => true,
    ]);

    $cart = Keranjang::create(['pelanggan_id' => $this->customer->id]);
    KeranjangItem::create([
        'keranjang_id' => $cart->id,
        'produk_id' => $this->prodComp->id,
        'produk_varian_id' => $this->varComp->id,
        'quantity' => 1,
    ]);

    Sanctum::actingAs($this->customer);

    $this->postJson('/api/checkout', [
        'name' => 'Legacy Clear Cart',
        'phone' => '081234567890',
        'address' => 'Jl. Gubeng No. 5',
        'payment_method' => 'online',
        'destination_zone_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'items' => [
            [
                'product_id' => $this->prodComp->id,
                'variant_id' => $this->varComp->id,
                'quantity' => 1,
            ],
        ],
    ])->assertCreated();

    expect($cart->items()->count())->toBe(0);
});

test('PPL-15: catalog supports slug category, rating sort with unrated products, and server pagination', function () {
    UlasanProduk::create([
        'produk_id' => $this->prodLip->id,
        'pelanggan_id' => $this->customer->id,
        'user_name' => $this->customer->username,
        'rating' => 5,
        'comment' => 'Sangat bagus dan tahan lama!',
        'status' => 'approved',
    ]);

    $this->getJson('/api/products?category=lip-care')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sku', 'RSK-S5-LIP');

    $ratingRes = $this->getJson('/api/products?sort=rating')
        ->assertOk();
    expect($ratingRes->json('data.0.sku'))->toBe('RSK-S5-LIP')
        ->and($ratingRes->json('data.1.sku'))->toBe('RSK-S5-CUSH');

    $pageRes = $this->getJson('/api/products?per_page=1&page=2')
        ->assertOk();
    expect($pageRes->json('meta.current_page'))->toBe(2)
        ->and($pageRes->json('meta.last_page'))->toBe(2)
        ->and($pageRes->json('meta.total'))->toBe(2);
});

test('Admin Dashboard API enforces auth and returns live metrics, chart, category distribution, and recent orders', function () {
    // 401 unauthenticated
    $this->getJson('/api/admin/dashboard')->assertUnauthorized();

    // 403 customer token
    Sanctum::actingAs($this->customer);
    $this->getJson('/api/admin/dashboard')->assertForbidden();

    // Seed real orders: 1 paid/diproses, 1 pending, 1 cancelled (excluded from revenue)
    $paidOrder = Pesanan::create([
        'order_code' => 'RSK-DASH-PAID',
        'pelanggan_id' => $this->customer->id,
        'customer_name' => 'Buyer Lunas',
        'customer_phone' => '08123456789',
        'shipping_address' => 'Jl. Surabaya 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'payment_status' => 'dibayar',
        'order_status' => 'diproses',
        'subtotal' => 190000,
        'shipping_cost' => 10000,
        'total_amount' => 200000,
    ]);
    $paidOrder->items()->create([
        'produk_id' => $this->prodLip->id,
        'produk_varian_id' => $this->varLip->id,
        'product_name' => $this->prodLip->name,
        'variant_name' => $this->varLip->name,
        'unit_price' => 95000,
        'quantity' => 2,
        'line_total' => 190000,
        'subtotal' => 190000,
    ]);
    Pembayaran::create([
        'pesanan_id' => $paidOrder->id,
        'transaction_id' => 'TRX-DASH-1',
        'payment_type' => 'qris',
        'gross_amount' => 200000,
        'transaction_status' => 'settlement',
        'paid_at' => now(),
    ]);

    Pesanan::create([
        'order_code' => 'RSK-DASH-WAIT',
        'pelanggan_id' => $this->customer->id,
        'customer_name' => 'Buyer Pending',
        'customer_phone' => '08123456789',
        'shipping_address' => 'Jl. Surabaya 2',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'bca_va',
        'payment_status' => 'menunggu_pembayaran',
        'order_status' => 'menunggu_pembayaran',
        'subtotal' => 185000,
        'shipping_cost' => 10000,
        'total_amount' => 195000,
    ]);

    $cancelledOrder = Pesanan::create([
        'order_code' => 'RSK-DASH-CANCEL',
        'pelanggan_id' => $this->customer->id,
        'customer_name' => 'Buyer Batal',
        'customer_phone' => '08123456789',
        'shipping_address' => 'Jl. Surabaya 3',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'payment_status' => 'dibayar',
        'order_status' => 'dibatalkan',
        'subtotal' => 185000,
        'shipping_cost' => 10000,
        'total_amount' => 195000,
    ]);
    $cancelledOrder->items()->create([
        'produk_id' => $this->prodComp->id,
        'produk_varian_id' => $this->varComp->id,
        'product_name' => $this->prodComp->name,
        'variant_name' => $this->varComp->name,
        'unit_price' => 185000,
        'quantity' => 1,
        'line_total' => 185000,
        'subtotal' => 185000,
    ]);

    Sanctum::actingAs($this->admin);
    $res = $this->getJson('/api/admin/dashboard?days=7')->assertOk();

    expect($res->json('data.metrics.active_products'))->toBe(2)
        ->and($res->json('data.metrics.pending_payment_orders'))->toBe(1)
        ->and($res->json('data.metrics.processing_orders'))->toBe(1)
        ->and($res->json('data.metrics.monthly_revenue'))->toBe(190000)
        ->and($res->json('data.sales_chart.days'))->toBe(7)
        ->and($res->json('data.sales_chart.points'))->toHaveCount(7)
        ->and($res->json('data.sales_chart.total_revenue'))->toBe(190000)
        ->and($res->json('data.category_distribution.total_units'))->toBe(2)
        ->and($res->json('data.category_distribution.items.0.category'))->toBe('Lip Care')
        ->and($res->json('data.recent_orders'))->toHaveCount(3);

    $res30 = $this->getJson('/api/admin/dashboard?days=30')->assertOk();
    expect($res30->json('data.sales_chart.points'))->toHaveCount(30);
});

test('StoreSettings returns empty string for nullable store_address', function () {
    StoreSettings::query()->updateOrCreate(['id' => 1], [
        'store_name' => 'RiasKu',
        'store_address' => null,
        'is_whatsapp_active' => false,
    ]);

    $this->getJson('/api/store-settings')
        ->assertOk()
        ->assertJsonPath('data.store_address', '');
});

test('PPL-19: online checkout keeps cart until payment settles, while COD clears cart immediately', function () {
    $cart = Keranjang::create(['pelanggan_id' => $this->customer->id]);
    KeranjangItem::create([
        'keranjang_id' => $cart->id,
        'produk_id' => $this->prodComp->id,
        'produk_varian_id' => $this->varComp->id,
        'quantity' => 1,
    ]);

    Sanctum::actingAs($this->customer);

    // Online order: cart MUST remain intact while payment is pending
    $onlineRes = $this->postJson('/api/orders', [
        'customer_name' => 'Online Cart Keeper',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Gubeng No. 12',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            ['produk_id' => $this->prodComp->id, 'produk_varian_id' => $this->varComp->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect($cart->items()->count())->toBe(1);

    // Once payment settles via simulator/webhook, cart MUST be cleared automatically
    $paymentId = $onlineRes->json('data.payment.id');
    $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertOk();
    expect($cart->items()->count())->toBe(0);

    // COD order: cart MUST clear immediately upon order creation
    KeranjangItem::create([
        'keranjang_id' => $cart->id,
        'produk_id' => $this->prodComp->id,
        'produk_varian_id' => $this->varComp->id,
        'quantity' => 1,
    ]);

    $this->postJson('/api/orders', [
        'customer_name' => 'COD Cart Clear',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Gubeng No. 13',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            ['produk_id' => $this->prodComp->id, 'produk_varian_id' => $this->varComp->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect($cart->items()->count())->toBe(0);
});

test('PPL-19: customer can cancel own pending online order, restoring stock while preserving cart', function () {
    $cart = Keranjang::create(['pelanggan_id' => $this->customer->id]);
    KeranjangItem::create([
        'keranjang_id' => $cart->id,
        'produk_id' => $this->prodComp->id,
        'produk_varian_id' => $this->varComp->id,
        'quantity' => 2,
    ]);

    Sanctum::actingAs($this->customer);

    $res = $this->postJson('/api/orders', [
        'customer_name' => 'Cancel Pending Buyer',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Cancel No. 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            ['produk_id' => $this->prodComp->id, 'produk_varian_id' => $this->varComp->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    $orderCode = $res->json('data.order.order_code');
    expect($this->varComp->fresh()->stock)->toBe(13);

    $cancelRes = $this->postJson("/api/orders/{$orderCode}/cancel")->assertOk();
    expect($cancelRes->json('data.order_status'))->toBe('dibatalkan')
        ->and($this->varComp->fresh()->stock)->toBe(15)
        ->and($cart->items()->count())->toBe(1);

    // Cancelling again must be rejected with 422
    $this->postJson("/api/orders/{$orderCode}/cancel")->assertStatus(422);
});

test('PPL-19: sandbox simulator works when allow_sandbox_simulator is enabled, and stays strictly forbidden in production', function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-SANDBOX123',
        'services.midtrans.is_production' => false,
        'services.midtrans.allow_sandbox_simulator' => true,
        'services.midtrans.base_url' => 'https://api.sandbox.midtrans.com',
    ]);

    Http::fake([
        'api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'status_message' => 'Success, QRIS transaction is created',
            'transaction_id' => 'SBX-SIM-QRIS-01',
            'gross_amount' => '185000.00',
            'payment_type' => 'qris',
            'transaction_status' => 'pending',
            'qr_string' => '00020101021226620014COM.GO-JEK.WWW01199360091437959563449',
            'actions' => [
                ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/SBX-SIM-QRIS-01/qr-code'],
            ],
        ], 201),
    ]);

    app()->forgetInstance(MidtransService::class);
    Sanctum::actingAs($this->customer);

    $orderRes = $this->postJson('/api/orders', [
        'customer_name' => 'Sandbox Sim Buyer',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Sandbox No. 9',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            ['produk_id' => $this->prodComp->id, 'produk_varian_id' => $this->varComp->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    $paymentId = $orderRes->json('data.payment.id');
    $orderCode = $orderRes->json('data.order.order_code');

    expect($orderRes->json('data.payment.midtrans_mode'))->toBe('live_sandbox')
        ->and($orderRes->json('data.payment.can_simulate'))->toBeTrue();

    // Public tracking endpoint must NEVER expose can_simulate
    $trackRes = $this->getJson("/api/orders/{$orderCode}/track")->assertOk();
    $trackRes->assertJsonMissingPath('data.payment.can_simulate');

    // In production mode, simulate-pay MUST remain 403 even if allow_sandbox_simulator is true
    config(['services.midtrans.is_production' => true]);
    app()->forgetInstance(MidtransService::class);
    $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertForbidden();

    // Back to sandbox mode -> simulate-pay succeeds and settles order
    config(['services.midtrans.is_production' => false]);
    app()->forgetInstance(MidtransService::class);
    $simRes = $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertOk();
    expect($simRes->json('data.payment.transaction_status'))->toBe('settlement')
        ->and($simRes->json('data.order.payment_status'))->toBe('dibayar')
        ->and($simRes->json('data.order.order_status'))->toBe('diproses');
});

test('Performance: GET /api/orders has no N+1 review queries and POST /api/shipping/rates batches item lookups', function () {
    $zone = ZonaPengiriman::create([
        'province' => 'Jawa Timur',
        'city' => 'Surabaya',
        'district' => 'Gubeng',
        'postal_code' => '60281',
        'is_active' => true,
    ]);
    TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'price_per_kg' => 8000,
        'etd_min_days' => 1,
        'etd_max_days' => 2,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->customer);

    // Create 3 orders
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/orders', [
            'customer_name' => "Perf Buyer {$i}",
            'customer_phone' => '081234567890',
            'shipping_address' => 'Jl. Perf No. 1',
            'shipping_city' => 'Surabaya',
            'payment_method' => 'online',
            'payment_channel' => 'qris',
            'items' => [
                ['produk_id' => $this->prodComp->id, 'produk_varian_id' => $this->varComp->id, 'quantity' => 1],
                ['produk_id' => $this->prodLip->id, 'produk_varian_id' => $this->varLip->id, 'quantity' => 1],
            ],
        ])->assertCreated();
    }

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->getJson('/api/orders')->assertOk();
    $orderQueries = \Illuminate\Support\Facades\DB::getQueryLog();
    $reviewQueries = collect($orderQueries)->filter(fn ($q) => str_contains($q['query'], 'ulasan_produk') && ! str_contains($q['query'], 'from "produk"'));
    expect($reviewQueries->count())->toBe(0);

    \Illuminate\Support\Facades\DB::flushQueryLog();
    $this->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [
            ['product_id' => $this->prodComp->id, 'variant_id' => $this->varComp->id, 'quantity' => 1],
            ['product_id' => $this->prodLip->id, 'variant_id' => $this->varLip->id, 'quantity' => 1],
        ],
    ])->assertOk();
    $rateQueries = \Illuminate\Support\Facades\DB::getQueryLog();
    // Should batch product & variant lookups (<= 9 total queries even with validation)
    expect(count($rateQueries))->toBeLessThanOrEqual(9);
});
