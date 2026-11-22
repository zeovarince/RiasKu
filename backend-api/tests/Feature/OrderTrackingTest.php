<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('PPL-21: public order tracking masks phone numbers safely, hides detail address, and includes tracking number & logs', function () {
    $customer = Pelanggan::create([
        'username' => 'track_cust',
        'email' => 'track_cust@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $admin = Admin::create([
        'name' => 'Admin Track',
        'email' => 'admin-track@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $kategori = Kategori::create(['name' => 'Cat Track', 'slug' => 'cat-track']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'TRK-01',
        'name' => 'Cushion Tracking',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 90000,
        'retail_price' => 95000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Natural', 'stock' => 10, 'is_active' => true]);

    Sanctum::actingAs($customer);
    $res = $this->postJson('/api/orders', [
        'customer_name' => 'Sari Indah',
        'customer_phone' => '081398765432',
        'shipping_address' => 'Jl. Rahasia Rumah Pribadi No. 99',
        'shipping_city' => 'Kota Surabaya',
        'shipping_district' => 'Gubeng',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 2]],
    ])->assertCreated();

    $orderId = $res->json('data.order.id');
    $orderCode = $res->json('data.order.order_code');

    // Admin proses dan kirim dengan nomor resi
    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'diproses',
        'note' => 'Pembayaran terverifikasi manual oleh admin.',
    ])->assertOk();
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dikirim',
        'tracking_number' => 'JNE-SBY-554433',
    ])->assertOk();

    // Tamu (tanpa Sanctum token) melacak kode pesanan
    app('auth')->forgetGuards();
    $trackRes = $this->getJson("/api/orders/{$orderCode}/track")->assertOk();

    expect($trackRes->json('data.order_code'))->toBe($orderCode)
        ->and($trackRes->json('data.customer_phone'))->toBe('0813****432')
        ->and($trackRes->json('data.tracking_number'))->toBe('JNE-SBY-554433')
        ->and($trackRes->json('data.order_status'))->toBe('dikirim')
        ->and($trackRes->json('data.status_logs'))->toHaveCount(3)
        ->and($trackRes->json('data.items.0.unit_price'))->toBe(95000)
        ->and(array_key_exists('shipping_address', $trackRes->json('data')))->toBeFalse();

    $trackRes->assertJsonMissingPath('data.status_logs.0.actor_name')
        ->assertJsonMissingPath('data.status_logs.0.actor_id')
        ->assertJsonMissingPath('data.items.0.stock_deducted');

    // Catatan internal verifikasi manual admin tidak boleh bocor pada endpoint publik /track
    expect(json_encode($trackRes->json('data.status_logs')))
        ->not->toContain('verifikasi manual oleh admin');

    // Endpoint publik katalog dan detail pesanan pelanggan tidak boleh membocorkan base_price / price_warning
    $pubProd = $this->getJson("/api/products/{$produk->id}")->assertOk();
    $pubProd->assertJsonMissingPath('data.base_price')
        ->assertJsonMissingPath('data.price_warning');

    Sanctum::actingAs($customer);
    $custOrder = $this->getJson("/api/orders/{$orderCode}")->assertOk();
    $custOrder->assertJsonMissingPath('data.items.0.produk.base_price');
});

test('PPL-21: authenticated customer can filter their order list and confirm order completion safely', function () {
    $owner = Pelanggan::create([
        'username' => 'owner_cust',
        'email' => 'owner@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $stranger = Pelanggan::create([
        'username' => 'stranger_cust',
        'email' => 'stranger@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $admin = Admin::create([
        'name' => 'Admin Complete',
        'email' => 'admin-complete@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $kategori = Kategori::create(['name' => 'Cat Complete', 'slug' => 'cat-complete']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CMP-01',
        'name' => 'Powder Complete',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 45000,
        'retail_price' => 48000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Standar', 'stock' => 10, 'is_active' => true]);

    Sanctum::actingAs($owner);
    $res = $this->postJson('/api/orders', [
        'customer_name' => 'Dina Pemilik',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Raya No. 1',
        'shipping_city' => 'Kota Bandung',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();

    $orderId = $res->json('data.order.id');
    $orderCode = $res->json('data.order.order_code');

    // Belum dikirim -> complete ditolak 422
    $this->postJson("/api/orders/{$orderCode}/complete")->assertStatus(422);

    // Admin proses -> kirim
    Sanctum::actingAs($admin);
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'diproses',
        'note' => 'Pembayaran terverifikasi manual oleh admin.',
    ])->assertOk();
    $this->patchJson("/api/admin/orders/{$orderId}/status", [
        'status' => 'dikirim',
        'tracking_number' => 'JNT-BDG-1122',
    ])->assertOk();

    // Stranger mencoba menyelesaikan order orang lain -> ditolak 404
    Sanctum::actingAs($stranger);
    $this->postJson("/api/orders/{$orderCode}/complete")->assertNotFound();

    // Pemilik konfirmasi diterima -> selesai
    Sanctum::actingAs($owner);
    $completeRes = $this->postJson("/api/orders/{$orderCode}/complete")->assertOk();
    expect($completeRes->json('data.order_status'))->toBe('selesai');

    // Filter daftar pesanan saya by status=selesai
    $listRes = $this->getJson('/api/orders?status=selesai')->assertOk();
    expect($listRes->json('data'))->toHaveCount(1)
        ->and($listRes->json('data.0.order_code'))->toBe($orderCode);
});

test('PPL-21 C1: prevents payment IDOR across customers and guards simulateSuccess outside sandbox', function () {
    $owner = Pelanggan::create([
        'username' => 'pay_owner',
        'email' => 'pay_owner@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $attacker = Pelanggan::create([
        'username' => 'pay_attacker',
        'email' => 'pay_attacker@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $kategori = Kategori::create(['name' => 'Cat IDOR', 'slug' => 'cat-idor']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'IDOR-01',
        'name' => 'Lip IDOR Test',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Rose', 'stock' => 10, 'is_active' => true]);

    config(['services.midtrans.server_key' => '']);
    app()->forgetInstance(MidtransService::class);

    Sanctum::actingAs($owner);
    $res = $this->postJson('/api/orders', [
        'customer_name' => 'Owner Pay',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Raya 1',
        'shipping_city' => 'Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [['produk_id' => $produk->id, 'produk_varian_id' => $varian->id, 'quantity' => 1]],
    ])->assertCreated();

    $paymentId = $res->json('data.payment.id');
    $orderCode = $res->json('data.order.order_code');

    // Pelanggan B tidak boleh melihat, mensimulasikan, atau menyinkronkan pembayaran milik Pelanggan A (403 selaras dengan PPL-19)
    Sanctum::actingAs($attacker);
    $this->getJson("/api/payments/{$paymentId}")->assertForbidden();
    $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertForbidden();
    $this->postJson("/api/payments/{$orderCode}/sync-status")->assertForbidden();

    // Pemilik tidak boleh memanggil simulate-pay ketika mode live Midtrans aktif
    Sanctum::actingAs($owner);
    config(['services.midtrans.server_key' => 'SB-Mid-server-LIVE']);
    app()->forgetInstance(MidtransService::class);
    $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertForbidden();

    expect(Pembayaran::find($paymentId)->transaction_status)->toBe('pending');

    // Batalkan pesanan -> simulate-pay dan createIntent pada pesanan dibatalkan wajib ditolak 422
    config(['services.midtrans.server_key' => '']);
    app()->forgetInstance(MidtransService::class);
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '407',
        'gross_amount' => '47000.00',
        'transaction_status' => 'expire',
    ])->assertOk();

    $this->postJson("/api/payments/{$paymentId}/simulate-pay")->assertStatus(422);
    $this->postJson("/api/orders/{$orderCode}/payments")->assertStatus(422);
});
