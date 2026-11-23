<?php

use App\Models\Admin;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\UlasanProduk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function createProductReviewFixture(): array
{
    $kategori = Kategori::create([
        'name' => 'Complexion Review',
        'slug' => 'complexion-review',
    ]);

    $produk = Produk::create([
        'kategori_id' => $kategori->id,
        'sku' => 'REV-001',
        'name' => 'Review Cushion',
        'brand' => 'RiasKu',
        'weight_grams' => 100,
        'base_price' => 100000,
        'retail_price' => 102000,
        'is_active' => true,
    ]);

    $pelanggan = Pelanggan::create([
        'username' => 'reviewer',
        'email' => 'reviewer@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    return [$produk, $pelanggan];
}

test('PPL-25: public user can list approved reviews and hidden reviews stay private', function () {
    [$produk, $pelanggan] = createProductReviewFixture();

    UlasanProduk::create([
        'produk_id' => $produk->id,
        'pelanggan_id' => $pelanggan->id,
        'user_name' => $pelanggan->username,
        'rating' => 5,
        'comment' => 'Teksturnya ringan dan tahan seharian.',
        'status' => 'approved',
    ]);

    UlasanProduk::create([
        'produk_id' => $produk->id,
        'pelanggan_id' => Pelanggan::create([
            'username' => 'hidden_reviewer',
            'email' => 'hidden@riasku.test',
            'password_hash' => bcrypt('password123'),
            'is_active' => true,
        ])->id,
        'user_name' => 'hidden_reviewer',
        'rating' => 1,
        'comment' => 'Ulasan yang masih ditinjau admin.',
        'status' => 'hidden',
    ]);

    $response = $this->getJson("/api/products/{$produk->id}/reviews");

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'product_id', 'user_name', 'rating', 'comment', 'created_at'],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.product_id'))->toBe($produk->id)
        ->and($response->json('data.0.user_name'))->toBe('reviewer')
        ->and($response->json('data.0.comment'))->toBe('Teksturnya ringan dan tahan seharian.');
});

test('PPL-25: guest cannot create a product review', function () {
    [$produk] = createProductReviewFixture();

    $this->postJson("/api/products/{$produk->id}/reviews", [
        'rating' => 5,
        'comment' => 'Ulasan dari tamu.',
    ])->assertUnauthorized();

    $this->assertDatabaseCount('ulasan_produk', 0);
});

test('PPL-25: authenticated customer can create a review and author name comes from the server', function () {
    [$produk, $pelanggan] = createProductReviewFixture();

    Sanctum::actingAs($pelanggan);

    $response = $this->postJson("/api/products/{$produk->id}/reviews", [
        'user_name' => 'Nama Palsu',
        'rating' => 5,
        'comment' => 'Coverage medium, tidak berat di wajah.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user_name', $pelanggan->username)
        ->assertJsonPath('data.product_id', $produk->id)
        ->assertJsonPath('data.rating', 5);

    $this->assertDatabaseHas('ulasan_produk', [
        'produk_id' => $produk->id,
        'pelanggan_id' => $pelanggan->id,
        'user_name' => $pelanggan->username,
        'rating' => 5,
        'comment' => 'Coverage medium, tidak berat di wajah.',
        'status' => 'approved',
    ]);
});

test('PPL-25: submitting the same product again updates the existing review', function () {
    [$produk, $pelanggan] = createProductReviewFixture();

    Sanctum::actingAs($pelanggan);

    $this->postJson("/api/products/{$produk->id}/reviews", [
        'rating' => 3,
        'comment' => 'Ulasan pertama yang perlu diperbarui.',
    ])->assertCreated();

    $response = $this->postJson("/api/products/{$produk->id}/reviews", [
        'rating' => 5,
        'comment' => 'Ulasan revisi setelah mencoba beberapa hari.',
    ]);

    $response->assertOk()->assertJsonPath('data.rating', 5);

    $this->assertDatabaseCount('ulasan_produk', 1)
        ->assertDatabaseHas('ulasan_produk', [
            'produk_id' => $produk->id,
            'pelanggan_id' => $pelanggan->id,
            'rating' => 5,
            'comment' => 'Ulasan revisi setelah mencoba beberapa hari.',
        ]);
});

test('PPL-25: invalid review payload is rejected with field errors', function (array $payload, string $field) {
    [$produk, $pelanggan] = createProductReviewFixture();

    Sanctum::actingAs($pelanggan);

    $this->postJson("/api/products/{$produk->id}/reviews", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('ulasan_produk', 0);
})->with([
    'rating too low' => [['rating' => 0, 'comment' => 'Ulasan yang cukup panjang.'], 'rating'],
    'rating too high' => [['rating' => 6, 'comment' => 'Ulasan yang cukup panjang.'], 'rating'],
    'rating missing' => [['comment' => 'Ulasan yang cukup panjang.'], 'rating'],
    'comment too short' => [['rating' => 5, 'comment' => 'abc'], 'comment'],
]);

test('PPL-25: product rating and review count use approved reviews instead of hardcoded values', function () {
    [$produk, $pertama] = createProductReviewFixture();

    $kedua = Pelanggan::create([
        'username' => 'reviewer_dua',
        'email' => 'reviewer-dua@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    foreach ([[$pertama, 5], [$kedua, 4]] as [$customer, $rating]) {
        UlasanProduk::create([
            'produk_id' => $produk->id,
            'pelanggan_id' => $customer->id,
            'user_name' => $customer->username,
            'rating' => $rating,
            'comment' => 'Ulasan terverifikasi untuk aggregat rating.',
            'status' => 'approved',
        ]);
    }

    $response = $this->getJson("/api/products/{$produk->id}");

    $response->assertOk()
        ->assertJsonPath('data.rating', '4.5')
        ->assertJsonPath('data.review_count', 2);
});

test('PPL-25: admin can hide a review and it disappears from the public list', function () {
    [$produk, $pelanggan] = createProductReviewFixture();

    $review = UlasanProduk::create([
        'produk_id' => $produk->id,
        'pelanggan_id' => $pelanggan->id,
        'user_name' => $pelanggan->username,
        'rating' => 5,
        'comment' => 'Ulasan akan disembunyikan admin.',
        'status' => 'approved',
    ]);

    $admin = Admin::create([
        'name' => 'Admin Review',
        'email' => 'admin-review@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'hidden'])
        ->assertOk()
        ->assertJsonPath('data.status', 'hidden');

    $this->assertDatabaseHas('ulasan_produk', [
        'id' => $review->id,
        'status' => 'hidden',
    ]);

    app('auth')->forgetGuards();

    $public = $this->getJson("/api/products/{$produk->id}/reviews");

    $public->assertOk();
    expect($public->json('data'))->toHaveCount(0);
});

test('PPL-25: admin can approve a hidden review again', function () {
    [$produk, $pelanggan] = createProductReviewFixture();

    $review = UlasanProduk::create([
        'produk_id' => $produk->id,
        'pelanggan_id' => $pelanggan->id,
        'user_name' => $pelanggan->username,
        'rating' => 4,
        'comment' => 'Ulasan yang disetujui kembali.',
        'status' => 'hidden',
    ]);

    $admin = Admin::create([
        'name' => 'Admin Review',
        'email' => 'admin-review@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    app('auth')->forgetGuards();

    $public = $this->getJson("/api/products/{$produk->id}/reviews");

    $public->assertOk();
    expect($public->json('data'))->toHaveCount(1);
});

test('PPL-25: customer cannot access admin review endpoints', function () {
    [, $pelanggan] = createProductReviewFixture();

    Sanctum::actingAs($pelanggan);

    $this->getJson('/api/admin/reviews')->assertForbidden();
});
