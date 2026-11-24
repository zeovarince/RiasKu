<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Produk;
use App\Models\UlasanProduk;
use App\Services\BestSellerService;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createBestSellerCategory(string $suffix = '01'): Kategori
{
    return Kategori::create([
        'name' => 'Best Seller '.$suffix,
        'icon' => 'lip',
    ]);
}

function createBestSellerProduct(string $sku, string $name, int $stock = 20, ?Kategori $category = null): Produk
{
    $category ??= createBestSellerCategory($sku);

    $product = Produk::create([
        'kategori_id' => $category->id,
        'sku' => $sku,
        'name' => $name,
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 30000,
        'retail_price' => 52000,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $product->varians()->create([
        'name' => 'Natural 01',
        'stock' => $stock,
        'is_active' => true,
    ]);

    return $product;
}

function createBestSellerCustomer(string $email = 'best-seller@riasku.test'): Pelanggan
{
    return Pelanggan::create([
        'username' => 'best-seller-customer',
        'email' => $email,
        'password_hash' => 'secret',
        'is_active' => true,
    ]);
}

function createBestSellerOrder(
    Pelanggan $customer,
    Produk $product,
    string $orderCode,
    int $quantity,
    string $orderStatus,
    string $paymentStatus,
    ?string $updatedAt = null,
): Pesanan {
    $total = 52000 * $quantity;

    $order = Pesanan::create([
        'pelanggan_id' => $customer->id,
        'order_code' => $orderCode,
        'customer_name' => 'Pelanggan Best Seller',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jalan Melati 1',
        'shipping_city' => 'Surabaya',
        'shipping_courier' => 'Kurir RiasKu',
        'shipping_cost' => 0,
        'subtotal' => $total,
        'total_product_amount' => $total,
        'total' => $total,
        'total_amount' => $total,
        'payment_method' => 'transfer',
        'payment_channel' => 'qris',
        'payment_status' => $paymentStatus,
        'order_status' => $orderStatus,
        'status' => $orderStatus,
    ]);

    PesananItem::create([
        'pesanan_id' => $order->id,
        'produk_id' => $product->id,
        'product_name' => $product->name,
        'variant_name' => 'Natural 01',
        'unit_price' => 52000,
        'quantity' => $quantity,
        'line_total' => $total,
        'subtotal' => $total,
    ]);

    if ($updatedAt !== null) {
        DB::table('pesanan')->where('id', $order->id)->update([
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    return $order;
}

function createBestSellerAdminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin Best Seller',
        'email' => 'best-seller-admin@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);

    return $admin->createToken('best-seller-token')->plainTextToken;
}

it('calculates weighted best seller scores from paid and completed orders only', function (): void {
    $customer = createBestSellerCustomer();
    $best = createBestSellerProduct('RSK-BS-01', 'Best Seller Lip Tint');
    $runnerUp = createBestSellerProduct('RSK-BS-02', 'Runner Up Cushion');
    $newProduct = createBestSellerProduct('RSK-BS-03', 'Produk Baru');
    $oldProduct = createBestSellerProduct('RSK-BS-04', 'Produk Lama');

    createBestSellerOrder($customer, $best, 'RSK-BS-PAID-01', 10, 'diproses', 'dibayar');
    createBestSellerOrder($customer, $best, 'RSK-BS-DONE-01', 5, 'selesai', 'menunggu_pembayaran');
    createBestSellerOrder($customer, $runnerUp, 'RSK-BS-PAID-02', 4, 'diproses', 'dibayar');
    createBestSellerOrder($customer, $best, 'RSK-BS-PENDING-01', 100, 'menunggu_pembayaran', 'menunggu_pembayaran');
    createBestSellerOrder($customer, $best, 'RSK-BS-CANCEL-01', 100, 'dibatalkan', 'dibayar');
    createBestSellerOrder($customer, $oldProduct, 'RSK-BS-OLD-01', 200, 'selesai', 'dibayar', now()->subDays(120)->toDateTimeString());

    UlasanProduk::create([
        'produk_id' => $best->id,
        'pelanggan_id' => $customer->id,
        'user_name' => $customer->username,
        'rating' => 5,
        'comment' => 'Produk favorit saya.',
        'status' => 'approved',
    ]);

    $result = app(BestSellerService::class)->refresh();

    expect($result['updated'])->toBe(4);

    $best->refresh();
    $runnerUp->refresh();
    $newProduct->refresh();
    $oldProduct->refresh();

    expect($best->best_seller_units_sold)->toBe(15)
        ->and($best->best_seller_orders_count)->toBe(2)
        ->and($best->best_seller_score)->toBeGreaterThan(0)
        ->and($runnerUp->best_seller_units_sold)->toBe(4)
        ->and($runnerUp->best_seller_orders_count)->toBe(1)
        ->and($best->best_seller_score)->toBeGreaterThan((float) $runnerUp->best_seller_score)
        ->and($newProduct->best_seller_score)->toBe(0.0)
        ->and($newProduct->best_seller_units_sold)->toBe(0)
        ->and($oldProduct->best_seller_score)->toBe(0.0)
        ->and($oldProduct->best_seller_units_sold)->toBe(0);
});

it('recalculates catalog scores when an order changes the normalization maximum', function (): void {
    $customer = createBestSellerCustomer('best-seller-normalization@riasku.test');
    $establishedLeader = createBestSellerProduct('RSK-BS-NORM-01', 'Established Leader');
    $runnerUp = createBestSellerProduct('RSK-BS-NORM-02', 'Runner Up');
    $newLeader = createBestSellerProduct('RSK-BS-NORM-03', 'New Leader');

    createBestSellerOrder($customer, $establishedLeader, 'RSK-BS-NORM-ORDER-01', 10, 'selesai', 'dibayar');
    createBestSellerOrder($customer, $runnerUp, 'RSK-BS-NORM-ORDER-02', 5, 'selesai', 'dibayar');

    $bestSellers = app(BestSellerService::class);
    $bestSellers->refresh();

    expect((float) $establishedLeader->fresh()->best_seller_score)->toBe(85.0)
        ->and((float) $runnerUp->fresh()->best_seller_score)->toBe(72.5);

    $newOrder = createBestSellerOrder($customer, $newLeader, 'RSK-BS-NORM-ORDER-03', 20, 'selesai', 'dibayar');
    $bestSellers->refreshForOrder($newOrder);

    expect((float) $establishedLeader->fresh()->best_seller_score)->toBe(72.5)
        ->and((float) $runnerUp->fresh()->best_seller_score)->toBe(66.25)
        ->and((float) $newLeader->fresh()->best_seller_score)->toBe(85.0);
});

it('ranks products bought in more orders above a larger one-time purchase', function (): void {
    $customer = createBestSellerCustomer('best-seller-frequency@riasku.test');
    $frequentlyBought = createBestSellerProduct('RSK-BS-FREQ-01', 'Frequently Bought Product');
    $bulkPurchase = createBestSellerProduct('RSK-BS-FREQ-02', 'Bulk Purchase Product');

    createBestSellerOrder($customer, $frequentlyBought, 'RSK-BS-FREQ-ORDER-01', 1, 'selesai', 'dibayar');
    createBestSellerOrder($customer, $frequentlyBought, 'RSK-BS-FREQ-ORDER-02', 1, 'selesai', 'dibayar');
    createBestSellerOrder($customer, $frequentlyBought, 'RSK-BS-FREQ-ORDER-03', 1, 'selesai', 'dibayar');
    createBestSellerOrder($customer, $bulkPurchase, 'RSK-BS-FREQ-ORDER-04', 10, 'selesai', 'dibayar');

    app(BestSellerService::class)->refresh();

    expect($frequentlyBought->fresh()->best_seller_orders_count)->toBe(3)
        ->and($bulkPurchase->fresh()->best_seller_orders_count)->toBe(1)
        ->and((float) $frequentlyBought->fresh()->best_seller_score)->toBe(67.5)
        ->and((float) $bulkPurchase->fresh()->best_seller_score)->toBe(45.0)
        ->and((float) $frequentlyBought->fresh()->best_seller_score)->toBeGreaterThan((float) $bulkPurchase->fresh()->best_seller_score)
        ->and(app(BestSellerService::class)->topSellers()->first()->id)->toBe($frequentlyBought->id);

    $this->getJson('/api/products?sort=best_seller&per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.id', $frequentlyBought->id);
});

it('serves the public best seller endpoint with rank, score and restock signals', function (): void {
    $customer = createBestSellerCustomer();
    $best = createBestSellerProduct('RSK-BS-PUB-01', 'Glow Velvet Lip Tint', 2);
    $runnerUp = createBestSellerProduct('RSK-BS-PUB-02', 'Soft Matte Cushion', 10);
    createBestSellerProduct('RSK-BS-PUB-03', 'Produk Tanpa Penjualan', 10);

    createBestSellerOrder($customer, $best, 'RSK-BS-PUB-PAID-01', 8, 'selesai', 'dibayar');
    createBestSellerOrder($customer, $runnerUp, 'RSK-BS-PUB-PAID-02', 3, 'diproses', 'dibayar');

    app(BestSellerService::class)->refresh();

    $response = $this->getJson('/api/products/best-sellers?limit=2');

    $response->assertOk()
        ->assertJsonPath('message', 'Daftar produk best seller berhasil dimuat.')
        ->assertJsonPath('data.0.name', 'Glow Velvet Lip Tint')
        ->assertJsonPath('data.0.best_seller_rank', 1)
        ->assertJsonPath('data.0.best_seller_units_sold', 8)
        ->assertJsonPath('data.0.needs_restock', true)
        ->assertJsonPath('data.0.restock_status', 'Stok Menipis')
        ->assertJsonPath('data.1.best_seller_rank', 2)
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.window_days', 90);

    expect($response->json('data.0'))->not->toHaveKey('base_price');

    expect($response->json('data.0.best_seller_score'))->toBeGreaterThan(0)
        ->and($response->json('data.1.best_seller_score'))->toBeGreaterThan(0);

    $sorted = $this->getJson('/api/products?sort=best_seller&per_page=10');

    $sorted->assertOk()
        ->assertJsonPath('data.0.name', 'Glow Velvet Lip Tint')
        ->assertJsonPath('data.0.is_best_seller', true);

    $filtered = $this->getJson('/api/products?best_seller=1&per_page=10');

    $filtered->assertOk();
    expect($filtered->json('data'))->toHaveCount(2);
});

it('exposes best seller ranking and score-building metrics on the admin dashboard endpoint', function (): void {
    $token = createBestSellerAdminToken();
    $customer = createBestSellerCustomer('best-seller-admin-customer@riasku.test');
    $best = createBestSellerProduct('RSK-BS-ADM-01', 'Admin Best Seller Lip Oil', 3);
    createBestSellerOrder($customer, $best, 'RSK-BS-ADM-PAID-01', 6, 'selesai', 'dibayar');

    app(BestSellerService::class)->refresh();

    $response = $this->withToken($token)->getJson('/api/admin/produk/best-sellers?limit=5');

    $response->assertOk()
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.product_id', $best->id)
        ->assertJsonPath('data.0.units_sold', 6)
        ->assertJsonPath('data.0.orders_count', 1)
        ->assertJsonPath('data.0.needs_restock', true)
        ->assertJsonPath('summary.products', 1)
        ->assertJsonPath('summary.total_units_sold', 6)
        ->assertJsonPath('summary.needs_restock', 1)
        ->assertJsonPath('meta.window_days', 90);

    expect($response->json('data.0.best_seller_score'))->toBeGreaterThan(0);

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/admin/produk/best-sellers')->assertUnauthorized();
});

it('refreshes the best seller score automatically when a COD order is marked paid', function (): void {
    $customer = createBestSellerCustomer('best-seller-cod-customer@riasku.test');
    $product = createBestSellerProduct('RSK-BS-COD-01', 'COD Best Seller Powder');

    $order = createBestSellerOrder($customer, $product, 'RSK-BS-COD-01', 4, 'diproses', 'menunggu_pembayaran_cod');
    $order->update(['payment_method' => 'cod', 'payment_channel' => 'cash']);

    expect((float) $product->fresh()->best_seller_score)->toBe(0.0);

    app(OrderStatusService::class)->markCodPaid($order, [
        'actor_type' => 'admin',
        'actor_name' => 'Admin Best Seller',
    ]);

    $product->refresh();

    expect($product->best_seller_units_sold)->toBe(4)
        ->and($product->best_seller_orders_count)->toBe(1)
        ->and((float) $product->best_seller_score)->toBeGreaterThan(0)
        ->and($product->best_seller_rank)->toBeNull();
});
