<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\PesananStatusLog;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeOrderAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin Pesanan Test',
        'email' => 'admin-order@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
}

function makeOrderCustomer(string $suffix = '1'): Pelanggan
{
    return Pelanggan::create([
        'username' => 'order_cust_'.$suffix,
        'email' => "order_cust_{$suffix}@riasku.test",
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
}

test('PPL-21: admin orders endpoint supports server-side search, filters, pagination, and accurate summary counts', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('list');

    $kategori = Kategori::create(['name' => 'Complexion List', 'slug' => 'complexion-list']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'ORD-LIST-1',
        'name' => 'Cushion Test Order',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 90000,
        'retail_price' => 95000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Standar', 'stock' => 50, 'is_active' => true]);

    // Buat 18 pesanan lewat variasi PR #48 & PR #49
    for ($i = 1; $i <= 12; $i++) {
        Pesanan::create([
            'pelanggan_id' => $customer->id,
            'order_code' => sprintf('RSK-LIST-%02d', $i),
            'customer_name' => $i === 1 ? 'Zelda Spesial' : "Pembeli Online {$i}",
            'customer_phone' => '081234567890',
            'shipping_address' => 'Jl. Melati No. 1',
            'shipping_city' => 'Kota Surabaya',
            'payment_method' => 'online',
            'payment_channel' => 'qris',
            'payment_status' => 'menunggu_pembayaran',
            'order_status' => 'menunggu_pembayaran',
            'total_product_amount' => 95000,
            'total_amount' => 95000,
        ]);
    }

    for ($j = 13; $j <= 18; $j++) {
        $codOrder = Pesanan::create([
            'pelanggan_id' => $customer->id,
            'order_code' => sprintf('RSK-COD-%02d', $j),
            'recipient_name' => "Pembeli COD {$j}",
            'recipient_phone' => '081399988877',
            'shipping_address' => 'Jl. Gubeng No. 5',
            'destination_city' => 'Surabaya',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'subtotal' => 95000,
            'shipping_cost' => 0,
            'total' => 95000,
            'tracking_number' => $j === 18 ? 'KURIR-SBY-18' : null,
            'has_preorder_items' => $j === 18,
            'preorder_estimate_days' => $j === 18 ? 4 : null,
        ]);
        $codOrder->items()->create([
            'produk_id' => $produk->id,
            'produk_varian_id' => $varian->id,
            'product_name' => $produk->name,
            'variant_name' => $varian->name,
            'unit_price' => 95000,
            'quantity' => 1,
            'line_total' => 95000,
        ]);
    }

    // Customer ditolak 403
    Sanctum::actingAs($customer);
    $this->getJson('/api/admin/orders')->assertForbidden();

    // Admin pagination + summary
    Sanctum::actingAs($admin);
    $page1 = $this->getJson('/api/admin/orders?per_page=15&page=1')->assertOk();
    expect($page1->json('data'))->toHaveCount(15)
        ->and($page1->json('meta.total'))->toBe(18)
        ->and($page1->json('meta.last_page'))->toBe(2)
        ->and($page1->json('summary.all'))->toBe(18)
        ->and($page1->json('summary.menunggu_pembayaran'))->toBe(12)
        ->and($page1->json('summary.diproses'))->toBe(6)
        ->and($page1->json('summary.action_required_count'))->toBe(18);

    // Search lintas recipient_name / customer_name / tracking_number
    $searchName = $this->getJson('/api/admin/orders?search=Zelda')->assertOk();
    expect($searchName->json('meta.total'))->toBe(1)
        ->and($searchName->json('data.0.order_code'))->toBe('RSK-LIST-01');

    $searchResi = $this->getJson('/api/admin/orders?search=KURIR-SBY-18&is_preorder=1')->assertOk();
    expect($searchResi->json('meta.total'))->toBe(1)
        ->and($searchResi->json('data.0.order_code'))->toBe('RSK-COD-18');
});

test('PPL-21: enforces state machine transitions, tracking number on dikirim, and audit logs', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('trans');

    $kategori = Kategori::create(['name' => 'Cat Trans', 'slug' => 'cat-trans']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'TRN-01',
        'name' => 'Lip Serum Trans',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 30000,
        'retail_price' => 32000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Clear', 'stock' => 10, 'is_active' => true]);

    $zone = ZonaPengiriman::create(['province' => 'Jawa Timur', 'city' => 'Malang', 'is_active' => true]);
    TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'price_per_kg' => 12000,
        'etd_min_days' => 2,
        'etd_max_days' => 3,
        'is_active' => true,
    ]);

    // Customer buat order Online via CheckoutController
    Sanctum::actingAs($customer);
    $coRes = $this->postJson('/api/checkout', [
        'name' => 'Rina Malang',
        'phone' => '081234567890',
        'address' => 'Jl. Ijen No. 1',
        'payment_method' => 'online',
        'destination_zone_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'items' => [['product_id' => $produk->id, 'variant_id' => $varian->id, 'quantity' => 2]],
    ])->assertCreated();

    $orderId = $coRes->json('data.id');
    expect(PesananStatusLog::where('pesanan_id', $orderId)->count())->toBe(1);

    Sanctum::actingAs($admin);

    // Lompat ilegal menunggu_pembayaran -> selesai ditolak 422
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'selesai',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);

    // Non-COD belum bayar -> pindah ke diproses tanpa catatan verifikasi manual ditolak 422 (N-M1)
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'diproses',
    ])->assertStatus(422)->assertJsonValidationErrors(['note']);

    // Ubah ke diproses dengan catatan verifikasi -> berhasil & menandai pembayaran dibayar (N-M1)
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'diproses',
        'note' => 'Pembayaran terverifikasi, sedang dikemas.',
    ])->assertOk()
        ->assertJsonPath('data.order_status', 'diproses')
        ->assertJsonPath('data.payment_status', 'dibayar');

    // Non-COD pindah ke dikirim TANPA resi -> ditolak 422
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dikirim',
    ])->assertStatus(422)->assertJsonValidationErrors(['tracking_number']);

    // Non-COD pindah ke dikirim DENGAN resi -> berhasil & tercatat di log
    $shipRes = $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dikirim',
        'tracking_number' => 'JNE-MLG-998877',
        'note' => 'Diserahkan ke agen JNE.',
    ])->assertOk();

    expect($shipRes->json('data.order_status'))->toBe('dikirim')
        ->and($shipRes->json('data.tracking_number'))->toBe('JNE-MLG-998877')
        ->and($shipRes->json('data.status_logs'))->toHaveCount(3);

    // Selesaikan pesanan & pastikan status selesai adalah terminal (tidak bisa diubah lagi)
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'selesai',
    ])->assertOk()->assertJsonPath('data.order_status', 'selesai');

    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'diproses',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

test('PPL-21: admin cancellation restores regular stock idempotently and never inflates Pre-Order stock', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('cancel');

    $kategori = Kategori::create(['name' => 'Cat Cancel', 'slug' => 'cat-cancel']);
    $readyProduct = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CNL-RDY',
        'name' => 'Bedak Ready',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 40000,
        'retail_price' => 42000,
        'is_preorder' => false,
        'is_active' => true,
    ]);
    $readyVariant = ProdukVarian::create(['produk_id' => $readyProduct->id, 'name' => 'Standar', 'stock' => 10, 'is_active' => true]);

    $poProduct = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CNL-PO',
        'name' => 'Tint PO',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 50000,
        'retail_price' => 52000,
        'is_preorder' => true,
        'preorder_estimate_days' => 5,
        'is_active' => true,
    ]);
    $poVariant = ProdukVarian::create(['produk_id' => $poProduct->id, 'name' => 'Rose', 'stock' => 0, 'is_active' => true]);

    Sanctum::actingAs($customer);
    $orderRes = $this->postJson('/api/orders', [
        'customer_name' => 'Budi Cancel',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Darmawangsa No. 1',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [
            ['produk_id' => $readyProduct->id, 'produk_varian_id' => $readyVariant->id, 'quantity' => 3],
            ['produk_id' => $poProduct->id, 'produk_varian_id' => $poVariant->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    expect($readyVariant->fresh()->stock)->toBe(7)
        ->and($poVariant->fresh()->stock)->toBe(0);

    $orderId = $orderRes->json('data.order.id');

    Sanctum::actingAs($admin);
    $cancelRes = $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dibatalkan',
        'note' => 'Pelanggan meminta batal via WhatsApp.',
    ])->assertOk();

    expect($cancelRes->json('data.order_status'))->toBe('dibatalkan')
        ->and($readyVariant->fresh()->stock)->toBe(10)
        ->and($poVariant->fresh()->stock)->toBe(0);

    // Batal ulang atau ubah status dari dibatalkan -> ditolak 422 & stok tetap 10
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dibatalkan',
    ])->assertStatus(422);
    expect($readyVariant->fresh()->stock)->toBe(10);
});

test('PPL-21: COD Surabaya order can transition diproses -> dikirim without resi and record COD completion log', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('codship');

    $kategori = Kategori::create(['name' => 'Cat COD', 'slug' => 'cat-cod']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'COD-SHIP-1',
        'name' => 'Bedak COD',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Standar', 'stock' => 10, 'is_active' => true]);

    Sanctum::actingAs($customer);
    $res = $this->postJson('/api/orders', [
        'customer_name' => 'Siti COD',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Manyar No. 8',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();

    $orderId = $res->json('data.order.id');

    Sanctum::actingAs($admin);
    // COD dikirim tanpa tracking_number diperbolehkan (kurir toko Surabaya)
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dikirim',
        'note' => 'Diantar langsung oleh kurir toko Surabaya.',
    ])->assertOk()->assertJsonPath('data.order_status', 'dikirim');

    // Konfirmasi uang tunai COD diterima -> selesai & dibayar & log tercatat
    $confirmRes = $this->postJson("/api/admin/orders/{$orderId}/confirm-cod-payment")->assertOk();
    expect($confirmRes->json('data.payment_status'))->toBe('dibayar')
        ->and($confirmRes->json('data.order_status'))->toBe('selesai')
        ->and($confirmRes->json('data.status_logs'))->toHaveCount(3);
});

test('PPL-21 H1: Midtrans late settlement after expire and duplicate settlement while shipped still return 200 and log refund notice', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('h1');

    $kategori = Kategori::create(['name' => 'Cat H1', 'slug' => 'cat-h1']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'H1-01',
        'name' => 'Foundation H1',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 80000,
        'retail_price' => 82000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Ivory', 'stock' => 10, 'is_active' => true]);

    config(['services.midtrans.server_key' => '']);

    // Kasus A: expire terlebih dulu -> dibatalkan & stok kembali -> settlement terlambat tetap HTTP 200 dan catat log perlu refund
    Sanctum::actingAs($customer);
    $orderA = $this->postJson('/api/orders', [
        'customer_name' => 'Kasus A',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. H1 No. 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 2]],
    ])->assertCreated();

    $orderACode = $orderA->json('data.order.order_code');
    $orderAId = $orderA->json('data.order.id');
    expect($varian->fresh()->stock)->toBe(8);

    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderACode,
        'status_code' => '407',
        'gross_amount' => '164000.00',
        'transaction_status' => 'expire',
    ])->assertOk();

    expect($varian->fresh()->stock)->toBe(10);

    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderACode,
        'status_code' => '200',
        'gross_amount' => '164000.00',
        'transaction_status' => 'settlement',
    ])->assertOk();

    // Settlement duplikat setelah dibatalkan tidak boleh menggandakan log refund (N-L1)
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderACode,
        'status_code' => '200',
        'gross_amount' => '164000.00',
        'transaction_status' => 'settlement',
    ])->assertOk();

    $freshA = Pesanan::with('statusLogs')->findOrFail($orderAId);
    $refundLogs = $freshA->statusLogs->filter(
        fn ($log): bool => str_contains(strtolower((string) $log->note), 'perlu refund')
    );

    expect($freshA->resolvedOrderStatus())->toBe('dibatalkan')
        ->and($freshA->payment_status)->toBe('dibayar')
        ->and($varian->fresh()->stock)->toBe(10)
        ->and($refundLogs)->toHaveCount(1);

    // Kasus B: settlement ulang saat pesanan sudah dikirim -> tetap HTTP 200 dan status tetap dikirim
    $orderB = $this->postJson('/api/orders', [
        'customer_name' => 'Kasus B',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. H1 No. 2',
        'shipping_city' => 'Malang',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();

    $orderBCode = $orderB->json('data.order.order_code');
    $orderBId = $orderB->json('data.order.id');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/orders/{$orderBId}/status", [
        'status' => 'diproses',
        'note' => 'Verifikasi pembayaran manual untuk uji H1.',
    ])->assertOk();
    $this->patchJson("/api/admin/orders/{$orderBId}/status", [
        'status' => 'dikirim',
        'tracking_number' => 'JNE-H1-001',
    ])->assertOk();

    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderBCode,
        'status_code' => '200',
        'gross_amount' => '82000.00',
        'transaction_status' => 'settlement',
    ])->assertOk();

    expect(Pesanan::findOrFail($orderBId)->resolvedOrderStatus())->toBe('dikirim');
});

test('PPL-21 M3 & M4: confirmCodPayment respects state machine inside lock and Pesanan syncs aliases bidirectionally', function () {
    $admin = makeOrderAdmin();
    $customer = makeOrderCustomer('m3m4');

    $kategori = Kategori::create(['name' => 'Cat M3M4', 'slug' => 'cat-m3m4']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'M3M4-01',
        'name' => 'Lip M3M4',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Nude', 'stock' => 10, 'is_active' => true]);

    // M3: COD yang dibatalkan tidak bisa di-confirm-cod-payment (ditolak 422)
    Sanctum::actingAs($customer);
    $resCancel = $this->postJson('/api/orders', [
        'customer_name' => 'Budi Batal COD',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. COD Batal No. 1',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();
    $cancelledId = $resCancel->json('data.order.id');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/orders/{$cancelledId}/status", ['status' => 'dibatalkan'])->assertOk();
    $this->postJson("/api/admin/orders/{$cancelledId}/confirm-cod-payment")->assertStatus(422);

    // M3: COD langsung dari diproses mencatat log dikirim sebelum selesai (tidak melompati audit trail)
    Sanctum::actingAs($customer);
    $resDirect = $this->postJson('/api/orders', [
        'customer_name' => 'Rani Direct COD',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. COD Direct No. 2',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();
    $directId = $resDirect->json('data.order.id');

    Sanctum::actingAs($admin);
    $directConfirm = $this->postJson("/api/admin/orders/{$directId}/confirm-cod-payment")->assertOk();
    expect($directConfirm->json('data.order_status'))->toBe('selesai')
        ->and($directConfirm->json('data.status_logs'))->toHaveCount(3)
        ->and($directConfirm->json('data.status_logs.1.to_status'))->toBe('dikirim')
        ->and($directConfirm->json('data.status_logs.2.to_status'))->toBe('selesai');

    // M4: Sinkronisasi alias dua arah ketika status/total diupdate langsung di model Pesanan
    $order = Pesanan::findOrFail($directId);
    $order->update(['status' => 'diproses', 'total' => 99000]);
    expect($order->fresh()->order_status)->toBe('diproses')
        ->and($order->fresh()->total_amount)->toBe(99000);

    $order->update(['order_status' => 'selesai', 'total_amount' => 123000]);
    expect($order->fresh()->status)->toBe('selesai')
        ->and($order->fresh()->total)->toBe(123000);
});

test('PPL-21 security hardening: Midtrans expire cannot cancel paid diproses/COD orders, validates gross_amount & fraud_status, and blocks channel/duplicate-item bypasses', function () {
    $customer = makeOrderCustomer('sec');
    $kategori = Kategori::create(['name' => 'Cat Sec', 'slug' => 'cat-sec']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'SEC-01',
        'name' => 'Cushion Sec',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 80000,
        'retail_price' => 82000,
        'is_preorder' => true,
        'preorder_estimate_days' => 3,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Ivory', 'stock' => 10, 'is_active' => true]);
    $poVarian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'PO Shade', 'stock' => 0, 'is_active' => true]);

    config(['services.midtrans.server_key' => '']);

    Sanctum::actingAs($customer);

    // 1. Tolak channel CASH (case-insensitive) untuk metode online & gabungkan baris duplikat PO (> 99 pcs ditolak 422)
    $this->postJson('/api/orders', [
        'customer_name' => 'Bypass Test',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Sudirman 1',
        'shipping_city' => 'Jakarta',
        'payment_method' => 'online',
        'payment_channel' => 'CASH',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertStatus(422);

    $this->postJson('/api/orders', [
        'customer_name' => 'Dup PO Test',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Sudirman 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            ['produk_id' => $produk->id, 'produk_varian_id' => $poVarian->id, 'quantity' => 60],
            ['produk_id' => $produk->id, 'produk_varian_id' => $poVarian->id, 'quantity' => 60],
        ],
    ])->assertStatus(422);

    // 2. Buat pesanan online -> fraud_status challenge tidak melunaskan -> settlement -> delayed expire TIDAK boleh membatalkan pesanan lunas!
    $orderRes = $this->postJson('/api/orders', [
        'customer_name' => 'Paid Order Sec',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Gubeng 9',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 2]],
    ])->assertCreated();

    $orderCode = $orderRes->json('data.order.order_code');
    $orderId = $orderRes->json('data.order.id');
    expect($varian->fresh()->stock)->toBe(8);

    // Nominal tidak cocok ditolak 422
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '200',
        'gross_amount' => '1000.00',
        'transaction_status' => 'settlement',
    ])->assertStatus(422);

    // Capture dengan fraud_status=challenge tidak boleh melunaskan
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '200',
        'gross_amount' => '164000.00',
        'transaction_status' => 'capture',
        'fraud_status' => 'challenge',
    ])->assertOk();
    expect(Pesanan::findOrFail($orderId)->resolvedOrderStatus())->toBe('menunggu_pembayaran');

    // Settlement resmi melunaskan & memproses pesanan
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '200',
        'gross_amount' => '164000.00',
        'transaction_status' => 'settlement',
    ])->assertOk();
    expect(Pesanan::findOrFail($orderId)->resolvedOrderStatus())->toBe('diproses');

    // Delayed expire webhook pada pesanan yang sudah diproses/dibayar HARUS diabaikan (tidak batal, tidak ubah payment, tidak kembalikan stok!)
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '407',
        'gross_amount' => '164000.00',
        'transaction_status' => 'expire',
    ])->assertOk();

    $freshPaid = Pesanan::with('latestPembayaran')->findOrFail($orderId);
    expect($freshPaid->resolvedOrderStatus())->toBe('diproses')
        ->and($freshPaid->payment_status)->toBe('dibayar')
        ->and($freshPaid->latestPembayaran->transaction_status)->toBe('settlement')
        ->and($varian->fresh()->stock)->toBe(8);

    // 3. Webhook Midtrans pada pesanan COD ditolak 422
    $codRes = $this->postJson('/api/orders', [
        'customer_name' => 'COD Guard',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Gubeng 10',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'cod',
        'payment_channel' => 'cash',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();

    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $codRes->json('data.order.order_code'),
        'status_code' => '407',
        'gross_amount' => '82000.00',
        'transaction_status' => 'expire',
    ])->assertStatus(422);
});
