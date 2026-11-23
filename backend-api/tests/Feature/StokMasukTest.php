<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\StokMasuk;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStockInAdminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin Gudang',
        'email' => 'gudang@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);

    return $admin->createToken('stok-masuk-token')->plainTextToken;
}

function createStockInCatalog(int $stock = 2, int $minStock = 5, string $sku = 'RSK-LIP-01'): array
{
    $kategori = Kategori::create([
        'name' => 'Lip Care '.$sku,
        'icon' => 'lip',
    ]);

    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => $sku,
        'name' => 'Glow Velvet Lip Tint '.$sku,
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 30000,
        'retail_price' => 52000,
        'min_stock' => $minStock,
        'is_active' => true,
    ]);

    $varian = $produk->varians()->create([
        'name' => 'Natural 01',
        'stock' => $stock,
        'is_active' => true,
    ]);

    return [$produk, $varian];
}

it('records stock in, increments the variant stock and updates the unit cost', function (): void {
    $token = createStockInAdminToken();
    [$produk, $varian] = createStockInCatalog();

    $response = $this->withToken($token)->postJson('/api/admin/inventory/incoming', [
        'produk_id' => $produk->id,
        'produk_varian_id' => $varian->id,
        'quantity' => 8,
        'unit_cost' => 31000,
        'supplier' => 'Supplier A',
        'notes' => 'Faktur INV-001',
        'tanggal_masuk' => '2026-11-23',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Barang masuk berhasil dicatat.')
        ->assertJsonPath('data.quantity', 8)
        ->assertJsonPath('data.recorded_by.name', 'Admin Gudang')
        ->assertJsonPath('data.tanggal_masuk', '2026-11-23T00:00:00.000000Z')
        ->assertJsonPath('product.total_stock', 10)
        ->assertJsonPath('product.needs_restock', false)
        ->assertJsonPath('product.restock_status', 'Stok Aman');

    expect(StokMasuk::count())->toBe(1)
        ->and($varian->fresh()->stock)->toBe(10)
        ->and($produk->fresh()->base_price)->toBe(31000)
        ->and($response->json('warnings'))->toBe([]);
});

it('returns 401 without a token and 403 for a customer token', function (): void {
    [$produk, $varian] = createStockInCatalog();
    $payload = [
        'produk_id' => $produk->id,
        'produk_varian_id' => $varian->id,
        'quantity' => 1,
    ];

    $this->postJson('/api/admin/inventory/incoming', $payload)->assertUnauthorized();
    $this->getJson('/api/admin/inventory/incoming')->assertUnauthorized();

    $customer = Pelanggan::create([
        'username' => 'pembeli',
        'email' => 'pembeli@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);
    $customerToken = $customer->createToken('customer-token')->plainTextToken;

    $this->withToken($customerToken)->postJson('/api/admin/inventory/incoming', $payload)->assertForbidden();
    $this->withToken($customerToken)->getJson('/api/admin/inventory/incoming')->assertForbidden();
});

it('rejects an empty payload with validation errors', function (): void {
    $token = createStockInAdminToken();

    $this->withToken($token)->postJson('/api/admin/inventory/incoming', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['produk_id', 'produk_varian_id', 'quantity']);

    expect(StokMasuk::count())->toBe(0);
});

it('rejects a variant that does not belong to the selected product', function (): void {
    $token = createStockInAdminToken();
    [$produk] = createStockInCatalog();
    [, $otherVarian] = createStockInCatalog(4, 5, 'RSK-LIP-02');

    $this->withToken($token)->postJson('/api/admin/inventory/incoming', [
        'produk_id' => $produk->id,
        'produk_varian_id' => $otherVarian->id,
        'quantity' => 3,
    ])->assertUnprocessable()->assertJsonValidationErrors(['produk_varian_id']);

    expect(StokMasuk::count())->toBe(0)
        ->and($produk->varians()->first()->stock)->toBe(2)
        ->and($otherVarian->fresh()->stock)->toBe(4);
});

it('filters stock in history by product, date and pagination', function (): void {
    $token = createStockInAdminToken();
    [$firstProduct, $firstVarian] = createStockInCatalog(1, 5, 'RSK-LIP-01');
    [$secondProduct, $secondVarian] = createStockInCatalog(1, 5, 'RSK-LIP-02');

    foreach ([
        [$firstProduct, $firstVarian, '2026-11-01', 4],
        [$firstProduct, $firstVarian, '2026-11-20', 6],
        [$secondProduct, $secondVarian, '2026-11-20', 10],
    ] as [$product, $varian, $tanggal, $quantity]) {
        $this->withToken($token)->postJson('/api/admin/inventory/incoming', [
            'produk_id' => $product->id,
            'produk_varian_id' => $varian->id,
            'quantity' => $quantity,
            'tanggal_masuk' => $tanggal,
        ])->assertCreated();
    }

    $this->withToken($token)
        ->getJson('/api/admin/inventory/incoming?produk_id='.$firstProduct->id.'&date_from=2026-11-10&per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.quantity', 6)
        ->assertJsonPath('data.0.produk.id', $firstProduct->id);

    $this->withToken($token)
        ->getJson('/api/admin/inventory/restock')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('lists only products at or below the minimum stock threshold', function (): void {
    $token = createStockInAdminToken();
    [$habis] = createStockInCatalog(0, 5, 'RSK-LIP-01');
    [$menipis] = createStockInCatalog(5, 5, 'RSK-LIP-02');
    [$aman] = createStockInCatalog(6, 5, 'RSK-LIP-03');

    $response = $this->withToken($token)->getJson('/api/admin/inventory/restock?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $menipis->id)
        ->assertJsonPath('data.1.id', $habis->id)
        ->assertJsonPath('data.0.restock_status', 'Stok Menipis')
        ->assertJsonPath('data.1.restock_status', 'Stok Habis')
        ->assertJsonMissingPath('data.2');

    expect($aman->fresh()->needs_restock)->toBeFalse();

    $this->withToken($token)->getJson('/api/admin/produk?restock=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('rolls back the history row and stock increment when saving fails', function (): void {
    $this->withoutExceptionHandling();

    $token = createStockInAdminToken();
    [$produk, $varian] = createStockInCatalog();

    $shouldThrow = true;
    StokMasuk::created(function () use (&$shouldThrow): void {
        if ($shouldThrow) {
            $shouldThrow = false;

            throw new RuntimeException('Simulasi kegagalan penyimpanan.');
        }
    });

    expect(fn () => $this->withToken($token)->postJson('/api/admin/inventory/incoming', [
        'produk_id' => $produk->id,
        'produk_varian_id' => $varian->id,
        'quantity' => 7,
    ]))->toThrow(RuntimeException::class);

    expect(StokMasuk::count())->toBe(0)
        ->and($varian->fresh()->stock)->toBe(2)
        ->and($produk->fresh()->base_price)->toBe(30000);
});
