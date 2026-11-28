<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin Barcode',
        'email' => 'admin-barcode@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->customer = Pelanggan::create([
        'username' => 'customer_barcode',
        'email' => 'customer-barcode@riasku.com',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $this->catSkincare = Kategori::create([
        'name' => 'Skincare Prep',
        'slug' => 'skincare-prep',
        'icon' => 'skincare',
    ]);

    $this->catLip = Kategori::create([
        'name' => 'Lip Care',
        'slug' => 'lip-care',
        'icon' => 'lip',
    ]);
});

test('barcode lookup endpoint enforces admin authentication', function () {
    $this->getJson('/api/admin/produk/lookup-barcode?barcode=8993137701234')
        ->assertUnauthorized();

    Sanctum::actingAs($this->customer);
    $this->getJson('/api/admin/produk/lookup-barcode?barcode=8993137701234')
        ->assertForbidden();
});

test('admin can store product with unique barcode and auto-generated SKU when SKU is omitted', function () {
    Sanctum::actingAs($this->admin);

    $first = $this->postJson('/api/admin/produk', [
        'kategori_id' => $this->catLip->id,
        'name' => 'Velvet Matte Lip Cream',
        'brand' => 'RiasKu',
        'barcode' => '8993137700011',
        'weight_grams' => 35,
        'base_price' => 45000,
        'retail_price' => 65000,
        'initial_stock' => 10,
    ])->assertCreated();

    $firstSku = $first->json('data.sku');
    expect($first->json('data.barcode'))->toBe('8993137700011')
        ->and($firstSku)->toStartWith('RSK-')
        ->and($firstSku)->toContain('VMLC');

    // Duplicate barcode rejected
    $this->postJson('/api/admin/produk', [
        'kategori_id' => $this->catLip->id,
        'name' => 'Velvet Matte Lip Cream Edition 2',
        'brand' => 'RiasKu',
        'barcode' => '8993137700011',
        'weight_grams' => 35,
        'base_price' => 45000,
        'retail_price' => 65000,
        'initial_stock' => 5,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['barcode']);

    // Same name with different barcode generates next unique SKU suffix
    $second = $this->postJson('/api/admin/produk', [
        'kategori_id' => $this->catLip->id,
        'name' => 'Velvet Matte Lip Cream',
        'brand' => 'RiasKu',
        'barcode' => '8993137700022',
        'weight_grams' => 35,
        'base_price' => 45000,
        'retail_price' => 65000,
        'initial_stock' => 5,
    ])->assertCreated();

    expect($second->json('data.sku'))->not->toBe($firstSku)
        ->and($second->json('data.sku'))->toStartWith('RSK-VMLC-');
});

test('lookup-barcode finds existing local product by barcode or sku without external call', function () {
    Http::fake();

    $product = Produk::create([
        'kategori_id' => $this->catSkincare->id,
        'sku' => 'RSK-KHF-01',
        'barcode' => '8993137708888',
        'name' => 'Kahf Skin Energizing Face Wash',
        'brand' => 'Kahf',
        'weight_grams' => 100,
        'base_price' => 32000,
        'retail_price' => 42000,
        'min_stock' => 5,
        'is_active' => true,
    ]);
    ProdukVarian::create([
        'produk_id' => $product->id,
        'name' => '100ml',
        'stock' => 12,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->admin);

    $byBarcode = $this->getJson('/api/admin/produk/lookup-barcode?barcode=8993137708888')
        ->assertOk();

    expect($byBarcode->json('data.status'))->toBe('existing_local')
        ->and($byBarcode->json('data.product.id'))->toBe($product->id)
        ->and($byBarcode->json('data.product.name'))->toBe('Kahf Skin Energizing Face Wash');

    $bySku = $this->getJson('/api/admin/produk/lookup-barcode?barcode=RSK-KHF-01')
        ->assertOk();

    expect($bySku->json('data.status'))->toBe('existing_local')
        ->and($bySku->json('data.product.id'))->toBe($product->id);

    Http::assertNothingSent();
});

test('lookup-barcode autofills suggestion from Open Beauty Facts when not found locally', function () {
    Http::fake([
        'https://world.openbeautyfacts.org/api/v2/product/8993137709999.json*' => Http::response([
            'code' => '8993137709999',
            'status' => 1,
            'product' => [
                'product_name' => 'Wardah Lightening Micellar Gentle Wash',
                'brands' => 'Wardah',
                'quantity' => '100 ml',
                'ingredients_text' => 'Aqua, Niacinamide, Licorice Extract, Glycerin',
                'categories' => 'Cleansers, Face Wash, Skincare',
                'image_front_url' => 'https://images.openbeautyfacts.org/images/products/test.jpg',
            ],
        ], 200),
    ]);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=8993137709999')
        ->assertOk();

    expect($res->json('data.status'))->toBe('external_found')
        ->and($res->json('data.suggestion.barcode'))->toBe('8993137709999')
        ->and($res->json('data.suggestion.name'))->toBe('Wardah Lightening Micellar Gentle Wash')
        ->and($res->json('data.suggestion.brand'))->toBe('Wardah')
        ->and($res->json('data.suggestion.weight_grams'))->toBe(100)
        ->and($res->json('data.suggestion.short_ingredients'))->toContain('Niacinamide')
        ->and($res->json('data.suggestion.suggested_category_id'))->toBe($this->catSkincare->id)
        ->and($res->json('data.suggestion.suggested_sku'))->toStartWith('RSK-');
});

test('lookup-barcode gracefully falls back to not_found when external api fails or returns status 0', function () {
    Http::fake([
        'https://world.openbeautyfacts.org/api/v2/product/8999999999999.json*' => Http::response([
            'code' => '8999999999999',
            'status' => 0,
        ], 200),
        'https://world.openproductsfacts.org/api/v2/product/8999999999999.json*' => Http::response([
            'code' => '8999999999999',
            'status' => 0,
        ], 200),
        'https://api.upcitemdb.com/prod/trial/lookup*' => Http::response([
            'code' => 'OK',
            'total' => 0,
            'items' => [],
        ], 200),
    ]);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=8999999999999')
        ->assertOk();

    expect($res->json('data.status'))->toBe('not_found')
        ->and($res->json('data.barcode'))->toBe('8999999999999');
});

test('lookup-barcode detects Indonesian BPOM 2D barcode format like (90)NA18241201785', function () {
    Http::fake();
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=(90)NA18241201785')
        ->assertOk();

    expect($res->json('data.status'))->toBe('bpom_detected')
        ->and($res->json('data.bpom_number'))->toBe('NA18241201785')
        ->and($res->json('data.cekbpom_url'))->toBe('https://cekbpom.pom.go.id/');

    Http::assertNothingSent();
});

test('lookup-barcode matches existing local product when scanning its BPOM 2D barcode', function () {
    Http::fake();

    $product = Produk::create([
        'kategori_id' => $this->catSkincare->id,
        'sku' => 'RSK-BPOM-01',
        'bpom_number' => 'NA18241201785',
        'name' => 'Herborist Natural Body Wash',
        'brand' => 'Herborist',
        'weight_grams' => 250,
        'base_price' => 24000,
        'retail_price' => 32000,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=(90)NA18241201785')
        ->assertOk();

    expect($res->json('data.status'))->toBe('existing_local')
        ->and($res->json('data.product.id'))->toBe($product->id)
        ->and($res->json('data.bpom_number'))->toBe('NA18241201785');

    Http::assertNothingSent();
});

test('lookup-barcode falls back to Open Products Facts when Open Beauty Facts has no match', function () {
    Http::fake([
        'https://world.openbeautyfacts.org/api/v2/product/8991234567890.json*' => Http::response([
            'code' => '8991234567890',
            'status' => 0,
        ], 200),
        'https://world.openproductsfacts.org/api/v2/product/8991234567890.json*' => Http::response([
            'code' => '8991234567890',
            'status' => 1,
            'product' => [
                'product_name' => 'Emina Bright Stuff Face Wash',
                'brands' => 'Emina',
                'quantity' => '50 ml',
                'categories' => 'Face Wash, Skincare',
            ],
        ], 200),
    ]);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=8991234567890')
        ->assertOk();

    expect($res->json('data.status'))->toBe('external_found')
        ->and($res->json('data.source'))->toBe('open_products_facts')
        ->and($res->json('data.suggestion.name'))->toBe('Emina Bright Stuff Face Wash')
        ->and($res->json('data.suggestion.brand'))->toBe('Emina')
        ->and($res->json('data.suggestion.weight_grams'))->toBe(50);
});

test('lookup-barcode falls back to UPCitemdb when Open Beauty Facts and Open Products Facts miss', function () {
    Http::fake([
        'https://world.openbeautyfacts.org/api/v2/product/8993137705555.json*' => Http::response(['status' => 0], 200),
        'https://world.openproductsfacts.org/api/v2/product/8993137705555.json*' => Http::response(['status' => 0], 200),
        'https://api.upcitemdb.com/prod/trial/lookup*' => Http::response([
            'code' => 'OK',
            'total' => 1,
            'items' => [
                [
                    'ean' => '8993137705555',
                    'title' => 'Wardah Colorfit Velvet Matte Lip Mousse 4g',
                    'brand' => 'Wardah',
                    'category' => 'Health & Beauty > Personal Care > Cosmetics > Makeup > Lip Makeup',
                    'description' => 'Soft velvet matte lip mousse nyaman dipakai seharian.',
                    'images' => ['https://cdn.example.com/wardah-lip.jpg'],
                ],
            ],
        ], 200),
    ]);

    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/admin/produk/lookup-barcode?barcode=8993137705555')
        ->assertOk();

    expect($res->json('data.status'))->toBe('external_found')
        ->and($res->json('data.source'))->toBe('upcitemdb')
        ->and($res->json('data.suggestion.name'))->toBe('Wardah Colorfit Velvet Matte Lip Mousse 4g')
        ->and($res->json('data.suggestion.brand'))->toBe('Wardah')
        ->and($res->json('data.suggestion.weight_grams'))->toBe(4)
        ->and($res->json('data.suggestion.suggested_category_id'))->toBe($this->catLip->id)
        ->and($res->json('data.suggestion.image_preview_url'))->toBe('https://cdn.example.com/wardah-lip.jpg');
});
