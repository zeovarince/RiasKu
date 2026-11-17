<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\ProdukFoto;
use App\Models\ProdukVarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

function createAdminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);

    return $admin->createToken('admin-token')->plainTextToken;
}

function createCustomerToken(): string
{
    $customer = Pelanggan::create([
        'username' => 'customer_user',
        'email' => 'cust@riasku.test',
        'password_hash' => 'secret',
        'is_active' => true,
    ]);

    return $customer->createToken('cust-token')->plainTextToken;
}

it('rejects unauthenticated requests and customer tokens on all catalog routes', function (): void {
    // 401 unauthenticated
    $this->getJson('/api/admin/kategori')->assertUnauthorized();
    $this->postJson('/api/admin/kategori', ['name' => 'Bibir'])->assertUnauthorized();
    $this->getJson('/api/admin/produk')->assertUnauthorized();
    $this->postJson('/api/admin/produk', [])->assertUnauthorized();

    // 403 token pelanggan
    $customerToken = createCustomerToken();
    $this->withToken($customerToken)->getJson('/api/admin/kategori')->assertForbidden();
    $this->withToken($customerToken)->postJson('/api/admin/kategori', ['name' => 'Bibir'])->assertForbidden();
    $this->withToken($customerToken)->getJson('/api/admin/produk')->assertForbidden();
    $this->withToken($customerToken)->postJson('/api/admin/produk', [])->assertForbidden();
});

it('manages kategori and rejects deletion if it still has products', function (): void {
    $token = createAdminToken();

    // Tambah kategori
    $res = $this->withToken($token)->postJson('/api/admin/kategori', [
        'name' => 'Wajah',
    ])->assertCreated();

    $kategoriId = $res->json('data.id');
    expect(Kategori::count())->toBe(1);

    // Tolak tambah nama duplikat
    $this->withToken($token)->postJson('/api/admin/kategori', [
        'name' => 'Wajah',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

    // Ubah nama kategori
    $this->withToken($token)->putJson("/api/admin/kategori/{$kategoriId}", [
        'name' => 'Tata Rias Wajah',
    ])->assertOk()->assertJsonPath('data.name', 'Tata Rias Wajah');

    // Buat produk di kategori ini
    $produk = Produk::create([
        'kategori_id' => $kategoriId,
        'sku' => 'RSK-CUSH-01',
        'name' => 'Dewy Cushion',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 50000,
        'retail_price' => 51500,
        'is_active' => true,
    ]);

    // Hapus kategori yang masih punya produk WAJIB ditolak 422
    $this->withToken($token)->deleteJson("/api/admin/kategori/{$kategoriId}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Kategori tidak dapat dihapus karena masih memiliki produk terkait.');

    expect(Kategori::count())->toBe(1);

    // Hapus produk terlebih dahulu, lalu hapus kategori berhasil
    $produk->delete();
    $this->withToken($token)->deleteJson("/api/admin/kategori/{$kategoriId}")
        ->assertOk();

    expect(Kategori::count())->toBe(0);
});

it('creates product and automatically creates standard variant when no variants provided', function (): void {
    $token = createAdminToken();
    $kategori = Kategori::create(['name' => 'Bibir', 'slug' => 'bibir']);

    $response = $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Velvet Lip Tint',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 20000,
        'retail_price' => 21500,
        'initial_stock' => 25,
    ])->assertCreated();

    $produkId = $response->json('data.id');
    $produk = Produk::with('varians')->findOrFail($produkId);

    expect($produk->varians)->toHaveCount(1)
        ->and($produk->varians->first()->name)->toBe('Standar')
        ->and($produk->varians->first()->stock)->toBe(25)
        ->and($produk->total_stock)->toBe(25)
        ->and($produk->stock_label)->toBe('Tersedia');
});

it('validates retail price warning and strictly rejects pack price outside modal and retail', function (): void {
    $token = createAdminToken();
    $kategori = Kategori::create(['name' => 'Skincare', 'slug' => 'skincare']);

    // Modal: 30.000. Ideal eceran: 31.000 s/d 32.000.
    // Eceran 40.000 (di luar rentang): HANYA PERINGATAN (bukan penolakan, tetap 201)
    $res = $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Hydrating Toner',
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 30000,
        'retail_price' => 40000,
        'initial_stock' => 10,
    ])->assertCreated();

    expect($res->json('warnings'))->not->toBeEmpty();

    // Harga pack <= modal (29.000 <= 30.000): DITOLAK 422
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Sunscreen SPF 50',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 30000,
        'retail_price' => 32000,
        'pack_quantity' => 5,
        'pack_price' => 29000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['pack_price']);

    // Harga pack >= eceran (32.000 >= 32.000): DITOLAK 422
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Sunscreen SPF 50',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 30000,
        'retail_price' => 32000,
        'pack_quantity' => 5,
        'pack_price' => 32500,
    ])->assertUnprocessable()->assertJsonValidationErrors(['pack_price']);

    // Harga pack valid: 31.000 (di antara modal 30.000 dan eceran 32.000): DITERIMA 201
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Sunscreen SPF 50',
        'brand' => 'RiasKu',
        'weight_grams' => 50,
        'base_price' => 30000,
        'retail_price' => 32000,
        'pack_quantity' => 5,
        'pack_price' => 31000,
    ])->assertCreated();
});

it('calculates stock label accurately across all 3 states', function (): void {
    $kategori = Kategori::create(['name' => 'Mata', 'slug' => 'mata']);

    // 1. Tersedia (total stock > 0)
    $p1 = Produk::create([
        'kategori_id' => $kategori->id,
        'name' => 'Mascara Volume',
        'brand' => 'RiasKu',
        'weight_grams' => 20,
        'base_price' => 25000,
        'retail_price' => 26500,
        'is_preorder' => false,
    ]);
    $p1->varians()->create(['name' => 'Hitam', 'stock' => 5]);
    expect($p1->stock_label)->toBe('Tersedia');

    // 2. Pre-Order (total stock == 0 dan is_preorder == true)
    $p2 = Produk::create([
        'kategori_id' => $kategori->id,
        'name' => 'Eyeshadow Palette',
        'brand' => 'RiasKu',
        'weight_grams' => 80,
        'base_price' => 70000,
        'retail_price' => 71800,
        'is_preorder' => true,
        'preorder_estimate_days' => 4,
    ]);
    $p2->varians()->create(['name' => 'Nude Rose', 'stock' => 0]);
    expect($p2->stock_label)->toBe('Pre-Order');

    // 3. Stok Habis (total stock == 0 dan is_preorder == false)
    $p3 = Produk::create([
        'kategori_id' => $kategori->id,
        'name' => 'Eyeliner Waterproof',
        'brand' => 'RiasKu',
        'weight_grams' => 15,
        'base_price' => 18000,
        'retail_price' => 19500,
        'is_preorder' => false,
    ]);
    $p3->varians()->create(['name' => 'Standar', 'stock' => 0]);
    expect($p3->stock_label)->toBe('Stok Habis');
});

it('validates preorder days must be 1 to 6 days when preorder is active', function (): void {
    $token = createAdminToken();
    $kategori = Kategori::create(['name' => 'Wajah', 'slug' => 'wajah']);

    // Pre-order aktif tanpa estimasi -> ditolak 422
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Loose Powder',
        'brand' => 'RiasKu',
        'weight_grams' => 40,
        'base_price' => 35000,
        'retail_price' => 36500,
        'is_preorder' => true,
        'preorder_estimate_days' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors(['preorder_estimate_days']);

    // Pre-order estimasi 7 hari (melebihi 6) -> ditolak 422
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Loose Powder',
        'brand' => 'RiasKu',
        'weight_grams' => 40,
        'base_price' => 35000,
        'retail_price' => 36500,
        'is_preorder' => true,
        'preorder_estimate_days' => 7,
    ])->assertUnprocessable()->assertJsonValidationErrors(['preorder_estimate_days']);

    // Pre-order estimasi 3 hari -> valid
    $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $kategori->id,
        'name' => 'Loose Powder',
        'brand' => 'RiasKu',
        'weight_grams' => 40,
        'base_price' => 35000,
        'retail_price' => 36500,
        'is_preorder' => true,
        'preorder_estimate_days' => 3,
    ])->assertCreated();
});

it('handles product activation/deactivation without permanent deletion', function (): void {
    $token = createAdminToken();
    $kategori = Kategori::create(['name' => 'Bibir', 'slug' => 'bibir']);

    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'name' => 'Lip Cream Matte',
        'brand' => 'RiasKu',
        'weight_grams' => 25,
        'base_price' => 22000,
        'retail_price' => 23500,
        'is_active' => true,
    ]);

    // Nonaktifkan
    $this->withToken($token)->patchJson("/api/admin/produk/{$produk->id}/status", [
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    expect(Produk::find($produk->id)->is_active)->toBeFalse();

    // Aktifkan kembali
    $this->withToken($token)->patchJson("/api/admin/produk/{$produk->id}/status", [
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.is_active', true);

    expect(Produk::find($produk->id)->is_active)->toBeTrue();
});

it('uploads and manages product photos', function (): void {
    $token = createAdminToken();
    $kategori = Kategori::create(['name' => 'Bibir', 'slug' => 'bibir']);
    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'name' => 'Lip Serum',
        'brand' => 'RiasKu',
        'weight_grams' => 20,
        'base_price' => 25000,
        'retail_price' => 26500,
    ]);

    $file1 = UploadedFile::fake()->image('lip1.png', 400, 400)->size(500); // 500 KB
    $res1 = $this->withToken($token)->postJson("/api/admin/produk/{$produk->id}/foto", [
        'foto' => $file1,
    ])->assertCreated();

    $foto1Id = $res1->json('data.id');
    expect($res1->json('data.is_primary'))->toBeTrue(); // foto pertama otomatis jadi primary

    $file2 = UploadedFile::fake()->image('lip2.jpg', 400, 400)->size(800);
    $res2 = $this->withToken($token)->postJson("/api/admin/produk/{$produk->id}/foto", [
        'foto' => $file2,
    ])->assertCreated();
    $foto2Id = $res2->json('data.id');
    expect($res2->json('data.is_primary'))->toBeFalse();

    // Jadikan foto ke-2 primary
    $this->withToken($token)->patchJson("/api/admin/foto/{$foto2Id}", [
        'is_primary' => true,
    ])->assertOk();

    expect(ProdukFoto::find($foto2Id)->is_primary)->toBeTrue()
        ->and(ProdukFoto::find($foto1Id)->is_primary)->toBeFalse();

    // Hapus foto
    $this->withToken($token)->deleteJson("/api/admin/foto/{$foto2Id}")->assertOk();
    expect(ProdukFoto::find($foto2Id))->toBeNull();
    // Foto 1 otomatis dipromosikan jadi primary
    expect(ProdukFoto::find($foto1Id)->is_primary)->toBeTrue();
});

it('completes the full end-to-end admin product management cycle', function (): void {
    // 1. Admin login
    Admin::create([
        'name' => 'Owner RiasKu',
        'email' => 'owner@riasku.test',
        'password_hash' => bcrypt('AdminPassword123!'),
        'is_active' => true,
    ]);

    $loginRes = $this->postJson('/api/admin/auth/login', [
        'email' => 'owner@riasku.test',
        'password' => 'AdminPassword123!',
    ])->assertOk();

    $token = $loginRes->json('data.token');
    expect($token)->not->toBeEmpty();

    // 2. Tambah Kategori
    $catRes = $this->withToken($token)->postJson('/api/admin/kategori', [
        'name' => 'Complexion Base',
    ])->assertCreated();
    $categoryId = $catRes->json('data.id');

    // 3. Tambah Produk Lengkap (dengan varian & harga pack)
    $prodRes = $this->withToken($token)->postJson('/api/admin/produk', [
        'kategori_id' => $categoryId,
        'sku' => 'RSK-CUSH-E2E',
        'name' => 'Velvet Glow Cushion',
        'brand' => 'RiasKu Beauty',
        'weight_grams' => 60,
        'base_price' => 45000,
        'retail_price' => 46500, // modal + 1.500 (rentang ideal)
        'pack_quantity' => 4,
        'pack_price' => 45800, // antara modal dan eceran
        'is_preorder' => false,
        'short_ingredients' => 'Centella, SPF 35',
        'bpom_number' => 'NA18220300999',
        'description' => 'Cushion ringan dengan hasil glowing alami.',
        'varians' => [
            ['name' => '01 Light Beige', 'stock' => 15],
            ['name' => '02 Natural Sand', 'stock' => 20],
        ],
    ])->assertCreated();

    $productId = $prodRes->json('data.id');
    expect($prodRes->json('warnings'))->toBeEmpty();

    // 4. Unggah Foto Produk
    $imageFile = UploadedFile::fake()->image('cushion-main.png', 500, 500)->size(300);
    $photoRes = $this->withToken($token)->postJson("/api/admin/produk/{$productId}/foto", [
        'foto' => $imageFile,
    ])->assertCreated();
    expect($photoRes->json('data.is_primary'))->toBeTrue();

    // 5. Muat ulang daftar produk (cek pencarian dan data produk lengkap)
    $listRes = $this->withToken($token)->getJson('/api/admin/produk?search=Velvet+Glow')->assertOk();
    $items = $listRes->json('data');
    expect($items)->toHaveCount(1)
        ->and($items[0]['sku'])->toBe('RSK-CUSH-E2E')
        ->and($items[0]['kategori']['name'])->toBe('Complexion Base')
        ->and($items[0]['total_stock'])->toBe(35)
        ->and($items[0]['stock_label'])->toBe('Tersedia')
        ->and($items[0]['fotos'])->toHaveCount(1)
        ->and($items[0]['is_active'])->toBeTrue();

    // 6. Nonaktifkan produk
    $this->withToken($token)->patchJson("/api/admin/produk/{$productId}/status", [
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    // Cek filter status aktif
    $activeOnly = $this->withToken($token)->getJson('/api/admin/produk?is_active=1')->assertOk();
    expect($activeOnly->json('data'))->toHaveCount(0);

    $inactiveOnly = $this->withToken($token)->getJson('/api/admin/produk?is_active=0')->assertOk();
    expect($inactiveOnly->json('data'))->toHaveCount(1);
});
