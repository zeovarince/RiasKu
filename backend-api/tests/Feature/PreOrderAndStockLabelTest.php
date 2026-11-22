<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePreorderAdmin(): Admin
{
    return Admin::create([
        'name' => 'Admin PO Test',
        'email' => 'admin-po@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
}

function makePreorderCustomer(string $suffix = '1'): Pelanggan
{
    return Pelanggan::create([
        'username' => 'po_customer_'.$suffix,
        'email' => "po_customer_{$suffix}@riasku.test",
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
}

test('PPL-22: total_stock excludes inactive variants and resolves hybrid variant stock labels', function () {
    $kategori = Kategori::create(['name' => 'Bibir PO', 'slug' => 'bibir-po']);

    // Produk non-PO dengan varian nonaktif berstok 20 dan varian aktif berstok 0 -> wajib Stok Habis
    $productOut = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'PO-TEST-OOS',
        'name' => 'Matte Lip Nonaktif Test',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 25000,
        'retail_price' => 27000,
        'is_preorder' => false,
        'is_active' => true,
    ]);
    ProdukVarian::create(['produk_id' => $productOut->id, 'name' => 'Arsip', 'stock' => 20, 'is_active' => false]);
    ProdukVarian::create(['produk_id' => $productOut->id, 'name' => 'Aktif Kosong', 'stock' => 0, 'is_active' => true]);

    $freshOut = Produk::with('activeVarians')->findOrFail($productOut->id);
    expect($freshOut->total_stock)->toBe(0)
        ->and($freshOut->stock_label)->toBe('Stok Habis');

    // Produk hybrid: is_preorder = true, Varian A stok 5 (Tersedia), Varian B stok 0 (Pre-Order)
    $hybrid = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'PO-TEST-HYBRID',
        'name' => 'Hybrid Cushion Tint',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 60000,
        'retail_price' => 62000,
        'is_preorder' => true,
        'preorder_estimate_days' => 4,
        'is_active' => true,
    ]);
    ProdukVarian::create(['produk_id' => $hybrid->id, 'name' => 'Shade A Ready', 'stock' => 5, 'is_active' => true]);
    ProdukVarian::create(['produk_id' => $hybrid->id, 'name' => 'Shade B PO', 'stock' => 0, 'is_active' => true]);

    $detailRes = $this->getJson("/api/products/{$hybrid->id}")->assertOk();
    expect($detailRes->json('data.stock_label'))->toBe('Tersedia')
        ->and($detailRes->json('data.total_stock'))->toBe(5)
        ->and($detailRes->json('data.variants.0.stock_label'))->toBe('Tersedia')
        ->and($detailRes->json('data.variants.0.is_preorder_item'))->toBeFalse()
        ->and($detailRes->json('data.variants.1.stock_label'))->toBe('Pre-Order')
        ->and($detailRes->json('data.variants.1.is_preorder_item'))->toBeTrue()
        ->and($detailRes->json('data.variants.1.preorder_estimate_days'))->toBe(4);
});

test('PPL-22: paginated stock_label filter on admin and public products queries SQL before pagination', function () {
    $kategori = Kategori::create(['name' => 'Pagination Test', 'slug' => 'pagination-test']);

    // Buat 2 produk Pre-Order terlebih dahulu (ID lebih kecil, seharusnya di halaman 2 jika tanpa filter)
    for ($i = 1; $i <= 2; $i++) {
        $po = Produk::create([
            'kategori_id' => $kategori->id,
            'sku' => "PG-PO-{$i}",
            'name' => "Produk PO Awal {$i}",
            'brand' => 'RiasKu',
            'weight_grams' => 30,
            'base_price' => 40000,
            'retail_price' => 42000,
            'is_preorder' => true,
            'preorder_estimate_days' => 3,
            'is_active' => true,
        ]);
        ProdukVarian::create(['produk_id' => $po->id, 'name' => 'Standar', 'stock' => 0, 'is_active' => true]);
    }

    // Buat 1 produk Stok Habis awal
    $oos = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'PG-OOS-1',
        'name' => 'Produk Habis Awal',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 40000,
        'retail_price' => 42000,
        'is_preorder' => false,
        'is_active' => true,
    ]);
    ProdukVarian::create(['produk_id' => $oos->id, 'name' => 'Standar', 'stock' => 0, 'is_active' => true]);

    // Buat 18 produk Tersedia (mengisi penuh halaman 1 dengan per_page=15)
    for ($i = 1; $i <= 18; $i++) {
        $ready = Produk::create([
            'kategori_id' => $kategori->id,
            'sku' => "PG-RDY-{$i}",
            'name' => "Produk Ready {$i}",
            'brand' => 'RiasKu',
            'weight_grams' => 30,
            'base_price' => 40000,
            'retail_price' => 42000,
            'is_preorder' => false,
            'is_active' => true,
        ]);
        ProdukVarian::create(['produk_id' => $ready->id, 'name' => 'Standar', 'stock' => 10, 'is_active' => true]);
    }

    // Uji Admin endpoint: filter Pre-Order wajib temukan tepat 2 item di halaman 1
    Sanctum::actingAs(makePreorderAdmin());
    $adminPo = $this->getJson('/api/admin/produk?stock_label=Pre-Order&per_page=15&page=1')->assertOk();
    expect($adminPo->json('meta.total'))->toBe(2)
        ->and($adminPo->json('meta.last_page'))->toBe(1)
        ->and($adminPo->json('data'))->toHaveCount(2);

    // Uji Public endpoint: filter Stok Habis & Pre-Order pada page=1&per_page=15
    $pubOos = $this->getJson('/api/products?stock_label=Stok+Habis&per_page=15&page=1')->assertOk();
    expect($pubOos->json('meta.total'))->toBe(1)
        ->and($pubOos->json('data'))->toHaveCount(1)
        ->and($pubOos->json('data.0.sku'))->toBe('PG-OOS-1');

    $pubPo = $this->getJson('/api/products?stock_label=Pre-Order&per_page=15&page=1')->assertOk();
    expect($pubPo->json('meta.total'))->toBe(2)
        ->and($pubPo->json('data'))->toHaveCount(2);
});

test('PPL-22: admin update product persists variant stocks and switches stock_label cleanly', function () {
    $admin = makePreorderAdmin();
    Sanctum::actingAs($admin);

    $kategori = Kategori::create(['name' => 'Edit PO', 'slug' => 'edit-po']);
    $createRes = $this->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'sku' => 'EDIT-PO-01',
        'name' => 'Blush On Edit',
        'brand' => 'RiasKu',
        'weight_grams' => 35,
        'base_price' => 50000,
        'retail_price' => 52000,
        'is_preorder' => false,
        'initial_stock' => 10,
    ])->assertCreated();

    $produkId = $createRes->json('data.id');
    expect($createRes->json('data.stock_label'))->toBe('Tersedia');

    // Update stok menjadi 0 dan aktifkan Pre-Order 6 hari dalam 1 call PUT
    $updateRes = $this->putJson("/api/admin/produk/{$produkId}", [
        'kategori_id' => $kategori->id,
        'sku' => 'EDIT-PO-01',
        'name' => 'Blush On Edit',
        'brand' => 'RiasKu',
        'weight_grams' => 35,
        'base_price' => 50000,
        'retail_price' => 52000,
        'is_preorder' => true,
        'preorder_estimate_days' => 6,
        'initial_stock' => 0,
    ])->assertOk();

    expect($updateRes->json('data.total_stock'))->toBe(0)
        ->and($updateRes->json('data.stock_label'))->toBe('Pre-Order')
        ->and($updateRes->json('data.preorder_estimate_days'))->toBe(6);

    // Tambah/ubah varian kustom lewat PUT juga bekerja dan varian yang tidak dikirim dinonaktifkan
    $customUpdate = $this->putJson("/api/admin/produk/{$produkId}", [
        'kategori_id' => $kategori->id,
        'sku' => 'EDIT-PO-01',
        'name' => 'Blush On Edit',
        'brand' => 'RiasKu',
        'weight_grams' => 35,
        'base_price' => 50000,
        'retail_price' => 52000,
        'is_preorder' => true,
        'preorder_estimate_days' => 2,
        'varians' => [
            ['name' => 'Coral Peach', 'stock' => 0],
            ['name' => 'Berry Plum', 'stock' => 4],
        ],
    ])->assertOk();

    expect($customUpdate->json('data.total_stock'))->toBe(4)
        ->and($customUpdate->json('data.stock_label'))->toBe('Tersedia')
        ->and(ProdukVarian::where('produk_id', $produkId)->where('is_active', true)->count())->toBe(2);
});

test('PPL-22: cart allows adding and incrementing Pre-Order items but rejects Stok Habis', function () {
    $customer = makePreorderCustomer('cart');
    Sanctum::actingAs($customer);

    $kategori = Kategori::create(['name' => 'Cart PO', 'slug' => 'cart-po']);

    $poProduct = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CART-PO-1',
        'name' => 'Velvet Lip PO',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 70000,
        'retail_price' => 72000,
        'is_preorder' => true,
        'preorder_estimate_days' => 5,
        'is_active' => true,
    ]);
    $poVariant = ProdukVarian::create(['produk_id' => $poProduct->id, 'name' => 'Burgundy', 'stock' => 0, 'is_active' => true]);

    $oosProduct = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CART-OOS-1',
        'name' => 'Velvet Lip Habis',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 70000,
        'retail_price' => 72000,
        'is_preorder' => false,
        'is_active' => true,
    ]);
    $oosVariant = ProdukVarian::create(['produk_id' => $oosProduct->id, 'name' => 'Rose', 'stock' => 0, 'is_active' => true]);

    // Tolak Stok Habis dengan 422
    $this->postJson('/api/cart/items', [
        'product_id' => $oosProduct->id,
        'variant_id' => $oosVariant->id,
        'quantity' => 1,
    ])->assertStatus(422);

    // Tambah Pre-Order 3 pcs -> berhasil dan membawa metadata PO
    $addRes = $this->postJson('/api/cart/items', [
        'product_id' => $poProduct->id,
        'variant_id' => $poVariant->id,
        'quantity' => 3,
    ])->assertCreated();

    expect($addRes->json('data.quantity'))->toBe(3)
        ->and($addRes->json('data.is_preorder_item'))->toBeTrue()
        ->and($addRes->json('data.preorder_estimate_days'))->toBe(5)
        ->and($addRes->json('data.stock_label'))->toBe('Pre-Order');

    // Update qty PO ke 6 pcs berhasil, > 99 ditolak 422
    $itemId = $addRes->json('data.id');
    $this->patchJson("/api/cart/items/{$itemId}", ['quantity' => 6])
        ->assertOk()
        ->assertJsonPath('data.quantity', 6);

    $this->patchJson("/api/cart/items/{$itemId}", ['quantity' => 100])->assertStatus(422);
});

test('PPL-22: both OrderController and CheckoutController handle Pre-Order and ready-first stock rules accurately', function () {
    $customer = makePreorderCustomer('checkout');
    Sanctum::actingAs($customer);

    $kategori = Kategori::create(['name' => 'CO PO', 'slug' => 'co-po']);
    $poProduct = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'CO-PO-01',
        'name' => 'Serum Glow PO',
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 80000,
        'retail_price' => 82000,
        'is_preorder' => true,
        'preorder_estimate_days' => 5,
        'is_active' => true,
    ]);
    $poVariantKosong = ProdukVarian::create(['produk_id' => $poProduct->id, 'name' => 'Variant PO', 'stock' => 0, 'is_active' => true]);
    $poVariantReady = ProdukVarian::create(['produk_id' => $poProduct->id, 'name' => 'Variant Ready', 'stock' => 5, 'is_active' => true]);

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

    // 1. CheckoutController (PR #49 path dengan destination_zone_id): dukung Pre-Order!
    $resZone = $this->postJson('/api/checkout', [
        'name' => 'Reno PO',
        'phone' => '081234567890',
        'address' => 'Jl. Dharmahusada No. 12',
        'payment_method' => 'online',
        'destination_zone_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'items' => [
            ['product_id' => $poProduct->id, 'variant_id' => $poVariantKosong->id, 'quantity' => 2],
            ['product_id' => $poProduct->id, 'variant_id' => $poVariantReady->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    expect($resZone->json('data.has_preorder_items'))->toBeTrue()
        ->and($resZone->json('data.preorder_estimate_days'))->toBe(5)
        ->and($resZone->json('data.items.0.is_preorder'))->toBeTrue()
        ->and($resZone->json('data.items.0.stock_deducted'))->toBeFalse()
        ->and($resZone->json('data.items.1.is_preorder'))->toBeFalse()
        ->and($resZone->json('data.items.1.stock_deducted'))->toBeTrue()
        ->and($poVariantKosong->fresh()->stock)->toBe(0)
        ->and($poVariantReady->fresh()->stock)->toBe(3);

    // 2. OrderController (PR #48 path): kirim PO online lalu simulasikan webhook expire -> anti phantom stock!
    $resOrder = $this->postJson('/api/orders', [
        'customer_name' => 'Reno Order',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Kertajaya No. 10',
        'shipping_city' => 'Kota Surabaya',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'items' => [
            ['produk_id' => $poProduct->id, 'produk_varian_id' => $poVariantKosong->id, 'quantity' => 3],
            ['produk_id' => $poProduct->id, 'produk_varian_id' => $poVariantReady->id, 'quantity' => 1],
        ],
    ])->assertCreated();

    expect($poVariantKosong->fresh()->stock)->toBe(0)
        ->and($poVariantReady->fresh()->stock)->toBe(2);

    $orderCode = $resOrder->json('data.order.order_code');

    // Webhook expire 1x -> varian ready kembali +1 (jadi 3), varian PO tetap 0 (tidak ciptakan phantom stock!)
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '407',
        'gross_amount' => '328000.00',
        'signature_key' => '',
        'transaction_status' => 'expire',
    ])->assertOk();

    expect($poVariantKosong->fresh()->stock)->toBe(0)
        ->and($poVariantReady->fresh()->stock)->toBe(3)
        ->and(Pesanan::where('order_code', $orderCode)->first()->order_status)->toBe('dibatalkan');

    // Webhook expire ke-2 -> idempotent, stok ready TIDAK bertambah lagi!
    $this->postJson('/api/payments/midtrans/webhook', [
        'order_id' => $orderCode,
        'status_code' => '407',
        'gross_amount' => '328000.00',
        'signature_key' => '',
        'transaction_status' => 'expire',
    ])->assertOk();

    expect($poVariantKosong->fresh()->stock)->toBe(0)
        ->and($poVariantReady->fresh()->stock)->toBe(3);
});

test('PPL-22 M8, L2 & L9: prevents lost update on admin stock edit, rejects unknown stock_label, and blocks deactivated cart updates', function () {
    $admin = makePreorderAdmin();
    $customer = makePreorderCustomer('m8');
    $kategori = Kategori::create(['name' => 'Cat M8', 'slug' => 'cat-m8']);

    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'M8-PROD-01',
        'name' => 'Compact M8',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_preorder' => false,
        'is_active' => true,
    ]);
    $varian = ProdukVarian::create(['produk_id' => $produk->id, 'name' => 'Beige', 'stock' => 10, 'is_active' => true]);

    // M8: Admin membuka form edit saat stok = 10, lalu pelanggan membeli 2 (stok menjadi 8), kemudian admin menambah +5 (10 -> 15)
    // dengan original_stock = 10 -> stok akhir harus menjadi 8 + (15 - 10) = 13, BUKAN 15!
    $varian->decrement('stock', 2);
    expect($varian->fresh()->stock)->toBe(8);

    Sanctum::actingAs($admin);
    $this->putJson("/api/admin/produk/{$produk->id}", [
        'kategori_id' => $kategori->id,
        'sku' => 'M8-PROD-01',
        'name' => 'Compact M8',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 45000,
        'retail_price' => 47000,
        'is_preorder' => false,
        'varians' => [
            ['id' => $varian->id, 'name' => 'Beige', 'stock' => 15, 'original_stock' => 10],
        ],
    ])->assertOk();

    expect($varian->fresh()->stock)->toBe(13);

    // L2: Label filter tak dikenal tidak boleh mengembalikan seluruh katalog
    $unknownFilter = $this->getJson('/api/products?stock_label=label-ngasal')->assertOk();
    expect($unknownFilter->json('data'))->toHaveCount(0);

    // L9: Update keranjang ditolak 422 bila varian atau produk dinonaktifkan
    Sanctum::actingAs($customer);
    $cartAdd = $this->postJson('/api/cart/items', [
        'product_id' => $produk->id,
        'variant_id' => $varian->id,
        'quantity' => 1,
    ])->assertCreated();
    $cartItemId = $cartAdd->json('data.id');

    $varian->update(['is_active' => false]);
    $this->patchJson("/api/cart/items/{$cartItemId}", ['quantity' => 2])->assertStatus(422);
});
