<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\StokMasuk;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('calculates paid sales, estimated cost, and Jakarta daily totals for the selected month', function (): void {
    $token = createMonthlyReportAdminToken();
    $customer = createMonthlyReportCustomer();
    [$product, $variant] = createMonthlyReportProduct();

    StokMasuk::create([
        'produk_id' => $product->id,
        'produk_varian_id' => $variant->id,
        'quantity' => 10,
        'unit_cost' => 30000,
        'tanggal_masuk' => '2026-10-01',
        'recorded_by' => Admin::query()->where('email', 'monthly-admin@riasku.test')->value('id'),
    ]);
    StokMasuk::create([
        'produk_id' => $product->id,
        'produk_varian_id' => $variant->id,
        'quantity' => 8,
        'unit_cost' => 30000,
        'tanggal_masuk' => '2026-11-23',
        'recorded_by' => Admin::query()->where('email', 'monthly-admin@riasku.test')->value('id'),
    ]);

    $unpricedProduct = Produk::create([
        'kategori_id' => $product->kategori_id,
        'sku' => 'RSK-MONTHLY-LIP-02',
        'name' => 'Lip Balm Tanpa Riwayat Modal',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 15000,
        'retail_price' => 40000,
        'is_active' => true,
    ]);
    createMonthlyReportOrder($customer, $product, 'RSK-PAID-NOV-01', 'diproses', 'dibayar', 2, 38000, '2026-10-20 00:00:00', '2026-10-20 00:00:00', '2026-11-01 00:30:00', 'settlement');
    createMonthlyReportOrder($customer, $product, 'RSK-SETTLED-NOV-06', 'diproses', 'menunggu_pembayaran', 1, 10000, '2026-11-14 00:00:00', '2026-11-14 00:00:00', '2026-11-15 03:00:00', 'settlement');
    createMonthlyReportOrder($customer, $unpricedProduct, 'RSK-BASE-COST-NOV-07', 'diproses', 'dibayar', 2, 40000, '2026-11-14 00:00:00', '2026-11-14 00:00:00', '2026-11-15 03:00:00', 'settlement');
    createMonthlyReportOrder($customer, $product, 'RSK-DONE-NOV-02', 'selesai', 'menunggu_pembayaran', 1, 52000, '2026-10-25 00:00:00', '2026-11-30 16:30:00');
    createMonthlyReportOrder($customer, $product, 'RSK-CANCEL-NOV-03', 'dibatalkan', 'dibayar', 7, 100000, '2026-11-10 00:00:00', '2026-11-10 00:00:00', '2026-11-10 10:00:00', 'settlement');
    createMonthlyReportOrder($customer, $product, 'RSK-PENDING-NOV-04', 'menunggu_pembayaran', 'menunggu_pembayaran', 3, 80000, '2026-11-12 00:00:00', '2026-11-12 00:00:00', null, 'pending');
    createMonthlyReportOrder($customer, $product, 'RSK-PAID-OCT-05', 'diproses', 'dibayar', 4, 90000, '2026-10-20 00:00:00', '2026-10-20 00:00:00', '2026-10-31 16:59:59', 'settlement');

    $response = $this->withToken($token)->getJson('/api/admin/reports/monthly?month=11&year=2026');

    $response->assertOk()
        ->assertJsonPath('data.summary.gross_revenue', 218000)
        ->assertJsonPath('data.summary.successful_orders', 4)
        ->assertJsonPath('data.summary.products_sold', 6)
        ->assertJsonPath('data.summary.stock_expense', 240000)
        ->assertJsonPath('data.summary.estimated_cogs', 150000)
        ->assertJsonPath('data.summary.gross_profit', 68000)
        ->assertJsonPath('data.summary.average_order_value', 54500)
        ->assertJsonPath('data.daily_trend.0.date', '2026-11-01')
        ->assertJsonPath('data.daily_trend.0.revenue', 76000)
        ->assertJsonPath('data.daily_trend.14.revenue', 90000)
        ->assertJsonPath('data.daily_trend.29.date', '2026-11-30')
        ->assertJsonPath('data.daily_trend.29.revenue', 52000)
        ->assertJsonPath('data.period.timezone', 'Asia/Jakarta');
});

it('paginates monthly transactions and searches order and stock descriptions', function (): void {
    $token = createMonthlyReportAdminToken();
    $customer = createMonthlyReportCustomer();
    [$product, $variant] = createMonthlyReportProduct();
    $adminId = Admin::query()->where('email', 'monthly-admin@riasku.test')->value('id');

    createMonthlyReportOrder($customer, $product, 'RSK-SEARCH-NOV-01', 'diproses', 'dibayar', 1, 52000, '2026-11-02 00:00:00', '2026-11-02 00:00:00', '2026-11-02 02:00:00', 'settlement');
    createMonthlyReportOrder($customer, $product, 'RSK-SEARCH-NOV-02', 'selesai', 'dibayar', 2, 52000, '2026-11-03 00:00:00', '2026-11-03 00:00:00', '2026-11-03 02:00:00', 'settlement');
    StokMasuk::create([
        'produk_id' => $product->id,
        'produk_varian_id' => $variant->id,
        'quantity' => 5,
        'unit_cost' => 30000,
        'supplier' => 'Supplier Laporan',
        'notes' => 'Invoice Search 01',
        'tanggal_masuk' => '2026-11-01',
        'recorded_by' => $adminId,
    ]);

    $firstPage = $this->withToken($token)->getJson('/api/admin/reports/monthly/transactions?month=11&year=2026&per_page=1&page=1');
    $thirdPage = $this->withToken($token)->getJson('/api/admin/reports/monthly/transactions?month=11&year=2026&per_page=1&page=3');
    $search = $this->withToken($token)->getJson('/api/admin/reports/monthly/transactions?month=11&year=2026&search=Supplier%20Laporan');

    $firstPage->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('data.0.transaction_id', 'RSK-SEARCH-NOV-02');
    $thirdPage->assertOk()
        ->assertJsonPath('data.0.transaction_id', 'STK-1')
        ->assertJsonPath('data.0.type', 'Pengeluaran');
    $search->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.description', 'Glow Velvet Lip Tint Monthly (Natural 01) · Supplier Laporan');
});

it('downloads a UTF-8 CSV containing income and expense rows', function (): void {
    $token = createMonthlyReportAdminToken();
    $customer = createMonthlyReportCustomer();
    [$product, $variant] = createMonthlyReportProduct();
    $product->update(['name' => '=HYPERLINK("https://example.test")']);

    createMonthlyReportOrder($customer, $product, 'RSK-CSV-NOV-01', 'selesai', 'dibayar', 1, 52000, '2026-11-10 00:00:00', '2026-11-10 00:00:00', '2026-11-10 02:00:00', 'settlement');
    StokMasuk::create([
        'produk_id' => $product->id,
        'produk_varian_id' => $variant->id,
        'quantity' => 2,
        'unit_cost' => 30000,
        'supplier' => 'Supplier CSV',
        'tanggal_masuk' => '2026-11-09',
        'recorded_by' => Admin::query()->where('email', 'monthly-admin@riasku.test')->value('id'),
    ]);

    $response = $this->withToken($token)->get('/api/admin/reports/monthly/export?month=11&year=2026');

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=riasku-rekap-keuangan-2026-11.csv');
    $csv = $response->streamedContent();
    $headerLine = strtok(ltrim($csv, "\xEF\xBB\xBF"), "\r\n");
    expect(str_getcsv($headerLine))->toBe([
        'Tanggal',
        'Jenis',
        'ID Transaksi',
        'Keterangan',
        'Jumlah Item',
        'Pendapatan (Rp)',
        'Pengeluaran (Rp)',
        'Status',
    ]);
    expect($csv)
        ->toContain('RSK-CSV-NOV-01')
        ->toContain("'=HYPERLINK")
        ->toContain('STK-1');
});

it('defaults the report period to the current Jakarta month', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-30 17:30:00', 'UTC'));
    $token = createMonthlyReportAdminToken();

    $response = $this->withToken($token)->getJson('/api/admin/reports/monthly');

    $response->assertOk()
        ->assertJsonPath('data.period.month', 12)
        ->assertJsonPath('data.period.year', 2026)
        ->assertJsonPath('data.period.start_date', '2026-12-01')
        ->assertJsonPath('data.period.end_date', '2026-12-31')
        ->assertJsonPath('data.period.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.daily_trend.30.date', '2026-12-31');
});

it('returns 401 without a token and 403 for a customer on monthly report endpoints', function (string $path): void {
    $this->getJson($path)->assertUnauthorized();

    $customerToken = createMonthlyReportCustomer()->createToken('monthly-report-customer')->plainTextToken;
    $this->withToken($customerToken)->getJson($path)->assertForbidden();
})->with([
    'summary' => ['/api/admin/reports/monthly'],
    'transactions' => ['/api/admin/reports/monthly/transactions'],
    'CSV export' => ['/api/admin/reports/monthly/export'],
]);

it('returns 422 for an invalid month, year, or pagination filter', function (string $path, array $query, array $fields): void {
    $token = createMonthlyReportAdminToken();

    $response = $this->withToken($token)->getJson($path.'?'.http_build_query($query));

    $response->assertUnprocessable()->assertJsonValidationErrors($fields);
})->with([
    'month outside the calendar range' => ['/api/admin/reports/monthly', ['month' => 13], ['month']],
    'year before supported history' => ['/api/admin/reports/monthly/export', ['year' => 1999], ['year']],
    'invalid pagination bounds' => ['/api/admin/reports/monthly/transactions', ['per_page' => 0, 'page' => 0], ['per_page', 'page']],
]);

function createMonthlyReportAdminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin Laporan',
        'email' => 'monthly-admin@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);

    return $admin->createToken('monthly-report-token')->plainTextToken;
}

function createMonthlyReportCustomer(): Pelanggan
{
    return Pelanggan::create([
        'username' => 'monthly-customer',
        'email' => 'monthly-customer@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);
}

/**
 * @return array{0:Produk,1:ProdukVarian}
 */
function createMonthlyReportProduct(): array
{
    $category = Kategori::create([
        'name' => 'Monthly Reports',
        'icon' => 'lip',
    ]);
    $product = Produk::create([
        'kategori_id' => $category->id,
        'sku' => 'RSK-MONTHLY-LIP-01',
        'name' => 'Glow Velvet Lip Tint Monthly',
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 30000,
        'retail_price' => 52000,
        'is_active' => true,
    ]);
    $variant = $product->varians()->create([
        'name' => 'Natural 01',
        'stock' => 20,
        'is_active' => true,
    ]);

    return [$product, $variant];
}

function createMonthlyReportOrder(
    Pelanggan $customer,
    Produk $product,
    string $orderCode,
    string $orderStatus,
    string $paymentStatus,
    int $quantity,
    int $unitPrice,
    string $createdAt,
    string $updatedAt,
    ?string $paidAt = null,
    ?string $transactionStatus = null,
): Pesanan {
    $total = $quantity * $unitPrice;
    $order = Pesanan::create([
        'pelanggan_id' => $customer->id,
        'order_code' => $orderCode,
        'customer_name' => 'Pelanggan Laporan',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jalan Mawar 1',
        'shipping_city' => 'Surabaya',
        'shipping_courier' => 'Kurir RiasKu',
        'shipping_cost' => 10000,
        'subtotal' => $total,
        'total_product_amount' => $total,
        'total' => $total + 10000,
        'total_amount' => $total + 10000,
        'payment_method' => 'transfer',
        'payment_channel' => 'qris',
        'payment_status' => $paymentStatus,
        'order_status' => $orderStatus,
        'status' => $orderStatus,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);
    DB::table('pesanan')->where('id', $order->id)->update([
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);
    PesananItem::create([
        'pesanan_id' => $order->id,
        'produk_id' => $product->id,
        'product_name' => $product->name,
        'variant_name' => 'Natural 01',
        'unit_price' => $unitPrice,
        'quantity' => $quantity,
        'line_total' => $total,
        'subtotal' => $total,
    ]);

    if ($transactionStatus !== null) {
        Pembayaran::create([
            'pesanan_id' => $order->id,
            'transaction_id' => 'PAY-'.$orderCode,
            'payment_type' => 'qris',
            'gross_amount' => $total + 10000,
            'transaction_status' => $transactionStatus,
            'paid_at' => $paidAt,
        ]);
    }

    return $order;
}
