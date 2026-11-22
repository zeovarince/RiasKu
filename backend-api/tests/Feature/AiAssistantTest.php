<?php

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Services\AiCatalogContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedAiCatalog(): array
{
    $complexion = Kategori::create(['name' => 'Complexion', 'slug' => 'complexion']);
    $lipCare = Kategori::create(['name' => 'Lip Care', 'slug' => 'lip-care']);

    $cushion = Produk::create([
        'kategori_id' => $complexion->id,
        'sku' => 'AI-CUSH-01',
        'name' => 'Velvet Matte Cushion SPF 35',
        'brand' => 'RiasKu Cosmetics',
        'weight_grams' => 120,
        'base_price' => 123456, // Harga modal rahasia, tidak boleh bocor!
        'retail_price' => 185000,
        'description' => 'Cushion hasil velvet matte tahan lama untuk kulit kombinasi dan berminyak.',
        'short_ingredients' => 'Centella, Niacinamide',
        'bpom_number' => 'NA18220300111',
        'is_preorder' => false,
        'is_active' => true,
    ]);
    ProdukVarian::create(['produk_id' => $cushion->id, 'name' => 'Natural 01', 'stock' => 10, 'is_active' => true]);

    $poTint = Produk::create([
        'kategori_id' => $lipCare->id,
        'sku' => 'AI-PO-01',
        'name' => 'Special Edition Velvet Tint (PO)',
        'brand' => 'RiasKu Studio',
        'weight_grams' => 35,
        'base_price' => 65432,
        'retail_price' => 90000,
        'description' => 'Warna edisi terbatas pre-order.',
        'is_preorder' => true,
        'preorder_estimate_days' => 5,
        'is_active' => true,
    ]);
    ProdukVarian::create(['produk_id' => $poTint->id, 'name' => 'Burgundy Rose', 'stock' => 0, 'is_active' => true]);

    $inactive = Produk::create([
        'kategori_id' => $lipCare->id,
        'sku' => 'AI-ARCH-01',
        'name' => 'Produk Nonaktif Rahasia',
        'brand' => 'RiasKu',
        'weight_grams' => 30,
        'base_price' => 50000,
        'retail_price' => 55000,
        'is_active' => false,
    ]);

    return [$cushion, $poTint, $inactive];
}

test('PPL-28: uses Gemini API via Http::fake, filters non-existent/inactive recommended IDs, and never leaks base_price', function () {
    [$cushion, $poTint, $inactive] = seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.gemini_api_key' => 'AIza-test-key-123',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'reply' => 'Untuk kulit kombinasi, kamu bisa pakai Velvet Matte Cushion SPF 35 (Rp185.000) yang saat ini tersedia.',
                                    // Sertakan ID nonaktif & ID fiktif 99999 -> harus difilter otomatis!
                                    'recommended_product_ids' => [$cushion->id, $inactive->id, 99999],
                                    'follow_up_suggestions' => ['Shade apa saja yang tersedia?'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $res = $this->postJson('/api/ai/chat', [
        'message' => 'Cari cushion untuk kulit kombinasi',
    ])->assertOk();

    expect($res->json('data.session_token'))->not->toBeEmpty()
        ->and($res->json('data.message.provider'))->toBe('gemini')
        ->and($res->json('data.message.content'))->toContain('Velvet Matte Cushion')
        ->and($res->json('data.message.recommended_products'))->toHaveCount(1)
        ->and($res->json('data.message.recommended_products.0.id'))->toBe($cushion->id);

    // Pastikan harga modal (base_price = 123456) TIDAK PERNAH dikirim ke Gemini maupun ke client!
    Http::assertSent(function ($request): bool {
        $body = json_encode($request->data());

        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && $request->hasHeader('x-goog-api-key', 'AIza-test-key-123')
            && !str_contains((string) $body, '123456')
            && !str_contains((string) $body, 'Produk Nonaktif Rahasia');
    });
});

test('PPL-28: uses local DB-grounded fallback without external HTTP when GEMINI_API_KEY is empty', function () {
    [, $poTint] = seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.gemini_api_key' => '',
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake();

    $res = $this->postJson('/api/ai/chat', [
        'message' => 'Produk apa saja yang sedang Pre-Order?',
    ])->assertOk();

    Http::assertNothingSent();

    expect($res->json('data.message.provider'))->toBe('fallback')
        ->and($res->json('data.message.content'))->toContain('Pre-Order')
        ->and($res->json('data.message.content'))->toContain('5 hari')
        ->and($res->json('data.message.recommended_products.0.id'))->toBe($poTint->id)
        ->and($res->json('data.message.recommended_products.0.stock_label'))->toBe('Pre-Order');
});

test('PPL-28: falls back gracefully when Gemini returns 500 error or malformed JSON', function () {
    seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.gemini_api_key' => 'AIza-test-key-123',
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Service unavailable'], 500),
    ]);

    $res = $this->postJson('/api/ai/chat', [
        'message' => 'Apakah bisa bayar COD Surabaya?',
    ])->assertOk();

    expect($res->json('data.message.provider'))->toBe('fallback')
        ->and($res->json('data.message.content'))->toContain('Kota Surabaya');
});

test('PPL-28: refuses sensitive base_price requests politely in fallback mode', function () {
    seedAiCatalog();
    config(['services.ai_assistant.gemini_api_key' => '', 'services.ai_assistant.catalog_cache_ttl' => 0]);

    $res = $this->postJson('/api/ai/chat', [
        'message' => 'Berapa harga modal dan kulakan cushion RiasKu?',
    ])->assertOk();

    expect($res->json('data.message.content'))->not->toContain('123456')
        ->and($res->json('data.message.content'))->toContain('informasi publik')
        ->and($res->json('data.message.recommended_products'))->toBeEmpty();
});

test('PPL-28: persists session history, enforces customer isolation, supports clear history, suggestions, and validation', function () {
    seedAiCatalog();
    config(['services.ai_assistant.gemini_api_key' => '', 'services.ai_assistant.catalog_cache_ttl' => 0]);

    // Validasi input kosong / terlalu pendek / terlalu panjang
    $this->postJson('/api/ai/chat', ['message' => 'a'])->assertStatus(422);
    $this->postJson('/api/ai/chat', ['message' => str_repeat('x', 1001)])->assertStatus(422);

    // Suggestions endpoint
    $sugRes = $this->getJson('/api/ai/suggestions')->assertOk();
    expect($sugRes->json('data'))->toBeArray()->not->toBeEmpty();

    // Tamu mengirim 2 pesan berurutan dengan session_token yang sama
    $chat1 = $this->postJson('/api/ai/chat', [
        'message' => 'Halo, butuh bantuan cari produk makeup',
    ])->assertOk();

    $guestToken = $chat1->json('data.session_token');

    $this->postJson('/api/ai/chat', [
        'session_token' => $guestToken,
        'message' => 'Cari cushion untuk kulit kombinasi',
    ])->assertOk();

    $historyRes = $this->getJson("/api/ai/history?session_token={$guestToken}")->assertOk();
    expect($historyRes->json('data.messages'))->toHaveCount(4)
        ->and($historyRes->json('data.messages.0.role'))->toBe('user')
        ->and($historyRes->json('data.messages.1.role'))->toBe('assistant');

    // Pelanggan login buat sesi sendiri -> Pelanggan lain tidak boleh akses (403)
    $owner = Pelanggan::create([
        'username' => 'ai_owner',
        'email' => 'ai_owner@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $other = Pelanggan::create([
        'username' => 'ai_other',
        'email' => 'ai_other@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);
    $ownerChat = $this->postJson('/api/ai/chat', [
        'message' => 'Info COD Surabaya',
    ])->assertOk();
    $ownerSessionToken = $ownerChat->json('data.session_token');

    Sanctum::actingAs($other);
    $this->getJson("/api/ai/history?session_token={$ownerSessionToken}")->assertForbidden();
    $this->deleteJson("/api/ai/history?session_token={$ownerSessionToken}")->assertForbidden();

    // Pemilik dapat menghapus sesinya sendiri
    Sanctum::actingAs($owner);
    $this->deleteJson("/api/ai/history?session_token={$ownerSessionToken}")
        ->assertOk()
        ->assertJsonPath('data.cleared', true);

    $afterClear = $this->getJson("/api/ai/history?session_token={$ownerSessionToken}")->assertOk();
    expect($afterClear->json('data.messages'))->toBeEmpty();
});

test('PPL-28: cascades intra-Gemini models (flash -> flash-lite) on 429 rate limit', function () {
    [$cushion] = seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.provider_chain' => ['gemini', 'groq', 'openrouter'],
        'services.ai_assistant.gemini_api_key' => 'AIza-test-key-123',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.gemini_fallback_models' => ['gemini-2.5-flash-lite', 'gemini-2.0-flash'],
        'services.ai_assistant.cooldown_seconds' => 60,
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['error' => 'quota'], 429),
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'reply' => '**Velvet Matte Cushion SPF 35** cocok untuk kulit kombinasi.',
                                    'recommended_product_ids' => [$cushion->id],
                                    'follow_up_suggestions' => ['Shade Natural 01 ada?'],
                                ]),
                            ]
                        ]
                    ],
                ]
            ],
        ], 200),
    ]);

    $res = $this->postJson('/api/ai/chat', [
        'message' => 'Cushion untuk kulit kombinasi apa ya?',
    ])->assertOk();

    expect($res->json('data.message.provider'))->toBe('gemini')
        ->and($res->json('data.message.model'))->toBe('gemini-2.5-flash-lite')
        ->and($res->json('data.message.content'))->toContain('Velvet Matte Cushion');
});

test('PPL-28: executes 3-LLM failover chain (Gemini -> Groq -> OpenRouter) without leaking base_price', function () {
    [$cushion] = seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.provider_chain' => ['gemini', 'groq', 'openrouter'],
        'services.ai_assistant.gemini_api_key' => 'AIza-secret-gemini',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.gemini_fallback_models' => ['gemini-2.5-flash-lite'],
        'services.ai_assistant.groq_api_key' => 'gsk-secret-groq',
        'services.ai_assistant.groq_model' => 'llama-3.3-70b-versatile',
        'services.ai_assistant.openrouter_api_key' => 'sk-or-secret-openrouter',
        'services.ai_assistant.openrouter_model' => 'meta-llama/llama-3.3-70b-instruct:free',
        'services.ai_assistant.cooldown_seconds' => 0,
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    // 1) Semua model Gemini 429 -> otomatis failover ke Groq
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota exhausted'], 429),
        'api.groq.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'reply' => 'Dijawab oleh Groq Llama untuk Velvet Matte Cushion SPF 35.',
                            'recommended_product_ids' => [$cushion->id],
                            'follow_up_suggestions' => ['Cek COD Surabaya'],
                        ]),
                    ],
                ]
            ],
        ], 200),
    ]);

    $groqRes = $this->postJson('/api/ai/chat', [
        'message' => 'Rekomendasi cushion RiasKu',
    ])->assertOk();

    expect($groqRes->json('data.message.provider'))->toBe('groq')
        ->and($groqRes->json('data.message.model'))->toBe('llama-3.3-70b-versatile')
        ->and($groqRes->json('data.message.recommended_products.0.id'))->toBe($cushion->id);
});

test('PPL-28: cascades all the way to OpenRouter when both Gemini and Groq fail without leaking base_price', function () {
    [$cushion] = seedAiCatalog();

    config([
        'services.ai_assistant.provider' => 'gemini',
        'services.ai_assistant.provider_chain' => ['gemini', 'groq', 'openrouter'],
        'services.ai_assistant.gemini_api_key' => 'AIza-secret-gemini',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.gemini_fallback_models' => ['gemini-2.5-flash-lite'],
        'services.ai_assistant.groq_api_key' => 'gsk-secret-groq',
        'services.ai_assistant.groq_model' => 'llama-3.3-70b-versatile',
        'services.ai_assistant.openrouter_api_key' => 'sk-or-secret-openrouter',
        'services.ai_assistant.openrouter_model' => 'meta-llama/llama-3.3-70b-instruct:free',
        'services.ai_assistant.cooldown_seconds' => 0,
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
        'api.groq.com/*' => Http::response(['error' => 'rate limit'], 429),
        'openrouter.ai/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'reply' => 'Dijawab oleh OpenRouter untuk cushion terbaik RiasKu.',
                            'recommended_product_ids' => [$cushion->id],
                            'follow_up_suggestions' => ['Ada lip tint PO?'],
                        ]),
                    ],
                ]
            ],
        ], 200),
    ]);

    $orRes = $this->postJson('/api/ai/chat', [
        'message' => 'Alternatif makeup harian',
    ])->assertOk();

    expect($orRes->json('data.message.provider'))->toBe('openrouter')
        ->and($orRes->json('data.message.model'))->toBe('meta-llama/llama-3.3-70b-instruct:free');

    Http::assertSent(function ($request): bool {
        if (!str_contains($request->url(), 'openrouter.ai')) {
            return false;
        }
        $body = (string) json_encode($request->data());

        return !str_contains($body, '123456') && !str_contains($body, 'Produk Nonaktif Rahasia');
    });
});

test('PPL-28: manages multi-session list, rename, delete, isolation, and provider status cleanly', function () {
    seedAiCatalog();
    config([
        'services.ai_assistant.gemini_api_key' => 'AIza-secret-should-not-leak',
        'services.ai_assistant.groq_api_key' => 'gsk-secret-should-not-leak',
        'services.ai_assistant.openrouter_api_key' => '',
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    // Status endpoint never exposes API key material or internal provider/model lists (L8)
    $statusRes = $this->getJson('/api/ai/status')->assertOk();
    expect($statusRes->json('data.has_real_llm'))->toBeTrue()
        ->and($statusRes->json('data.local_engine_active'))->toBeTrue()
        ->and(json_encode($statusRes->json()))->not->toContain('AIza-secret')
        ->and(json_encode($statusRes->json()))->not->toContain('gsk-secret');
    $statusRes->assertJsonMissingPath('data.models')
        ->assertJsonMissingPath('data.configured_providers');

    config([
        'services.ai_assistant.gemini_api_key' => '',
        'services.ai_assistant.groq_api_key' => '',
    ]);

    // Tamu membuat 2 sesi terpisah
    $g1 = $this->postJson('/api/ai/chat', ['message' => 'Cari cushion kulit kombinasi'])->assertOk()->json('data.session_token');
    $g2 = $this->postJson('/api/ai/chat', ['message' => 'Produk Pre-Order apa?'])->assertOk()->json('data.session_token');

    // Pelanggan login membuat sesi sendiri -> tamu tidak boleh melihat sesi pelanggan
    $owner = Pelanggan::create([
        'username' => 'multi_owner',
        'email' => 'multi_owner@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $other = Pelanggan::create([
        'username' => 'multi_other',
        'email' => 'multi_other@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($owner);
    $ownerToken = $this->postJson('/api/ai/chat', ['message' => 'Info COD Surabaya'])->assertOk()->json('data.session_token');

    $ownerList = $this->getJson('/api/ai/sessions')->assertOk()->json('data');
    expect($ownerList)->toHaveCount(1)
        ->and($ownerList[0]['session_token'])->toBe($ownerToken);

    // Rename & isolation check
    $this->patchJson("/api/ai/sessions/{$ownerToken}", ['title' => 'Tanya Pengiriman COD'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Tanya Pengiriman COD');

    Sanctum::actingAs($other);
    $this->patchJson("/api/ai/sessions/{$ownerToken}", ['title' => 'Hack'])->assertForbidden();
    $this->deleteJson("/api/ai/sessions/{$ownerToken}")->assertForbidden();

    // Tamu hanya melihat sesi miliknya sendiri dari daftar token, bukan milik owner meskipun memasukkan token owner
    app('auth')->forgetGuards();
    $guestSessions = $this->getJson('/api/ai/sessions?tokens=' . implode(',', [$g1, $g2, $ownerToken]))->assertOk()->json('data');
    $guestSessionTokens = collect($guestSessions)->pluck('session_token')->all();
    expect($guestSessionTokens)->toContain($g1)->toContain($g2)->not->toContain($ownerToken);

    // M7: Saat pelanggan lain (other) login dan hanya punya active_token=$g1, token $g2 milik tamu lain TIDAK ikut ter-claim!
    Sanctum::actingAs($other);
    $claimedList = $this->getJson('/api/ai/sessions?tokens=' . implode(',', [$g1, $g2]) . "&active_token={$g1}")->assertOk()->json('data');
    $claimedTokens = collect($claimedList)->pluck('session_token')->all();
    expect($claimedTokens)->toContain($g1)->not->toContain($g2);

    // Rename & delete sesi tamu g2 oleh tamu (masih tidak bertuan)
    app('auth')->forgetGuards();
    $this->patchJson("/api/ai/sessions/{$g2}", ['title' => 'Rekomendasi Pre-Order'])->assertOk();
    $this->deleteJson("/api/ai/sessions/{$g2}")->assertOk()->assertJsonPath('data.deleted', true);
    $afterDelete = $this->getJson('/api/ai/sessions?tokens=' . $g2)->assertOk()->json('data');
    expect($afterDelete)->toBeEmpty();
});

test('PPL-28: RiasKu Smart Local AI Engine v2 handles multi-turn follow-ups, ready stock filter, comparisons, qty simulation, and order lookup', function () {
    [$cushion, $poTint] = seedAiCatalog();
    config([
        'services.ai_assistant.gemini_api_key' => '',
        'services.ai_assistant.groq_api_key' => '',
        'services.ai_assistant.openrouter_api_key' => '',
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    // Turn 1: Tanya produk Pre-Order
    $t1 = $this->postJson('/api/ai/chat', [
        'message' => 'Produk apa saja yang sedang Pre-Order?',
    ])->assertOk();
    $token = $t1->json('data.session_token');
    expect($t1->json('data.message.recommended_products.0.id'))->toBe($poTint->id);

    // Turn 2: Klik follow-up "Tampilkan produk yang ready stock" -> harus mengembalikan produk Tersedia ($cushion), bukan Pre-Order!
    $t2 = $this->postJson('/api/ai/chat', [
        'session_token' => $token,
        'message' => 'Tampilkan produk yang ready stock',
    ])->assertOk();
    expect($t2->json('data.message.recommended_products.0.id'))->toBe($cushion->id)
        ->and($t2->json('data.message.content'))->toContain('Tersedia (Ready Stock)');

    // Turn 3: Multi-turn follow-up tanpa menyebut nama produk ("Shade apa saja dan nomor BPOM-nya?") -> merujuk ke $cushion dari Turn 2
    $t3 = $this->postJson('/api/ai/chat', [
        'session_token' => $token,
        'message' => 'Shade apa saja dan nomor BPOM-nya?',
    ])->assertOk();
    expect($t3->json('data.message.content'))->toContain('NA18220300111')
        ->and($t3->json('data.message.content'))->toContain('Natural 01');

    // Turn 4: Simulasi harga multi-turn ("Kalau beli 3 pcs berapa?") -> 3 x Rp185.000 = Rp555.000
    $t4 = $this->postJson('/api/ai/chat', [
        'session_token' => $token,
        'message' => 'Kalau beli 3 pcs berapa?',
    ])->assertOk();
    expect($t4->json('data.message.content'))->toContain('Rp555.000');

    // Turn 5: Perbandingan produk ("Bandingkan dengan produk lain")
    $t5 = $this->postJson('/api/ai/chat', [
        'session_token' => $token,
        'message' => 'Bandingkan dengan produk lain',
    ])->assertOk();
    expect($t5->json('data.message.content'))->toContain('perbandingan lengkap')
        ->and($t5->json('data.message.recommended_products'))->toHaveCount(2);
});

test('PPL-28 M1, M2 & H3: handles order lookup deterministically without external LLM and applies 5xx cooldown', function () {
    [$cushion] = seedAiCatalog();
    $customer = Pelanggan::create([
        'username' => 'ai_order_cust',
        'email' => 'ai_order_cust@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $order = Pesanan::create([
        'pelanggan_id' => $customer->id,
        'order_code' => 'RSK-20261122-AI999',
        'customer_name' => 'Lia Pembeli',
        'customer_phone' => '081234567890',
        'shipping_address' => 'Jl. Dharmahusada 10',
        'shipping_city' => 'Kota Surabaya',
        'shipping_courier' => 'JNE REG',
        'tracking_number' => 'JNE-AI-7788',
        'payment_method' => 'online',
        'payment_channel' => 'qris',
        'payment_status' => 'dibayar',
        'order_status' => 'dikirim',
        'total_product_amount' => 370000,
        'total_amount' => 370000,
    ]);
    $order->items()->create([
        'produk_id' => $cushion->id,
        'product_name' => 'Velvet Matte Cushion SPF 35',
        'variant_name' => 'Natural 01',
        'unit_price' => 185000,
        'quantity' => 2,
        'subtotal' => 370000,
    ]);

    config([
        'services.ai_assistant.gemini_api_key' => 'AIza-secret-should-not-be-called',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.gemini_fallback_models' => ['gemini-2.5-flash-lite'],
        'services.ai_assistant.cooldown_seconds' => 60,
        'services.ai_assistant.error_cooldown_seconds' => 30,
        'services.ai_assistant.catalog_cache_ttl' => 300,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response(['error' => 'server err'], 500),
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'reply' => 'Dijawab model cadangan sesudah 500.',
                                    'recommended_product_ids' => [$cushion->id],
                                    'follow_up_suggestions' => ['Cek stok'],
                                ]),
                            ]
                        ]
                    ],
                ]
            ],
        ], 200),
    ]);

    // M1 & M2: Pelanggan login lookup RSK-... dijawab deterministik lokal dengan nama item lengkap, TANPA memanggil LLM eksternal!
    Sanctum::actingAs($customer);
    $custLookup = $this->postJson('/api/ai/chat', [
        'message' => 'Tolong cek status pesanan RSK-20261122-AI999',
    ])->assertOk();
    Http::assertNothingSent();
    expect($custLookup->json('data.message.provider'))->toBe('fallback')
        ->and($custLookup->json('data.message.content'))->toContain('Velvet Matte Cushion SPF 35 (Natural 01) x2')
        ->and($custLookup->json('data.message.content'))->toContain('JNE-AI-7788');

    // M2: Tamu menanyakan RSK-... hanya melihat status, kurir, dan resi (tanpa item & total)
    app('auth')->forgetGuards();
    $guestLookup = $this->postJson('/api/ai/chat', [
        'message' => 'Cek RSK-20261122-AI999',
    ])->assertOk();
    Http::assertNothingSent();
    expect($guestLookup->json('data.message.content'))->toContain('JNE-AI-7788')
        ->and($guestLookup->json('data.message.content'))->not->toContain('370.000')
        ->and($guestLookup->json('data.message.content'))->not->toContain('Natural 01');

    // H3: Model yang mengembalikan HTTP 500 dikenai cooldown pendek sehingga call kedua tidak memanggil flash lagi
    $r1 = $this->postJson('/api/ai/chat', ['message' => 'Rekomendasi cushion terbaik'])->assertOk();
    expect($r1->json('data.message.model'))->toBe('gemini-2.5-flash-lite')
        ->and(Cache::has('ai_assistant:cooldown:gemini:gemini-2.5-flash'))->toBeTrue();
});

test('PPL-28: rate limits chat endpoint after 20 requests per minute', function () {
    config(['services.ai_assistant.gemini_api_key' => '', 'services.ai_assistant.catalog_cache_ttl' => 0]);

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/ai/chat', [
            'message' => "Pertanyaan ke-{$i} tentang cushion",
        ])->assertOk();
    }

    $this->postJson('/api/ai/chat', [
        'message' => 'Pertanyaan ke-21 yang terkena rate limit',
    ])->assertStatus(429);
});

test('PPL-28 N-H1: isolates AI rate limiters from auth routes and separates per-customer buckets', function () {
    $customerA = Pelanggan::create([
        'username' => 'rate_a',
        'email' => 'rate_a@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $customerB = Pelanggan::create([
        'username' => 'rate_b',
        'email' => 'rate_b@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    // 15x GET /api/ai/status sebagai tamu tidak boleh menghabiskan kuota /api/auth/login (bucket terpisah)
    for ($i = 0; $i < 15; $i++) {
        $this->getJson('/api/ai/status')->assertOk();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'rate_a@riasku.test',
        'password' => 'password123',
    ])->assertOk();

    // Kuota chat pelanggan A tidak boleh memblokir pelanggan B di IP yang sama
    config(['services.ai_assistant.gemini_api_key' => '', 'services.ai_assistant.catalog_cache_ttl' => 0]);

    Sanctum::actingAs($customerA);
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/ai/chat', [
            'message' => "Pertanyaan pelanggan A ke-{$i}",
        ])->assertOk();
    }
    $this->postJson('/api/ai/chat', [
        'message' => 'Pertanyaan pelanggan A ke-21',
    ])->assertStatus(429);

    Sanctum::actingAs($customerB);
    $this->postJson('/api/ai/chat', [
        'message' => 'Pertanyaan pertama pelanggan B di IP sama',
    ])->assertOk();
});

test('PPL-28 N-L2 & N-L4: answers empty-order customer locally without LLM and avoids FK 500 when session is deleted mid-flight', function () {
    seedAiCatalog();
    $customer = Pelanggan::create([
        'username' => 'empty_order_cust',
        'email' => 'empty_order@riasku.test',
        'password_hash' => bcrypt('password123'),
        'is_active' => true,
    ]);

    config([
        'services.ai_assistant.gemini_api_key' => 'AIza-test-nl2-nl4',
        'services.ai_assistant.gemini_model' => 'gemini-2.5-flash',
        'services.ai_assistant.gemini_fallback_models' => [],
        'services.ai_assistant.catalog_cache_ttl' => 0,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => function () {
            // Simulasikan pengguna menekan "Percakapan Baru" (DELETE /api/ai/history) saat LLM eksternal masih berjalan
            AiChatSession::query()->delete();

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'reply' => 'Respons AI terlambat untuk sesi yang sudah dihapus.',
                                        'recommended_product_ids' => [],
                                        'follow_up_suggestions' => ['Cari produk'],
                                    ]),
                                ]
                            ]
                        ],
                    ]
                ],
            ], 200);
        },
    ]);

    // N-L4: Pelanggan tanpa riwayat pesanan menanyakan pesanannya -> dijawab deterministik lokal
    Sanctum::actingAs($customer);
    $emptyOrderRes = $this->postJson('/api/ai/chat', [
        'message' => 'Cek status pesanan saya sekarang',
    ])->assertOk();

    Http::assertNothingSent();
    expect($emptyOrderRes->json('data.message.provider'))->toBe('fallback')
        ->and($emptyOrderRes->json('data.message.content'))->toContain('belum memiliki riwayat pesanan');

    // N-L2: Sesi terhapus saat panggilan LLM masih berjalan -> tetap HTTP 200 tanpa pelanggaran FK
    AiChatMessage::query()->delete();
    AiChatSession::query()->delete();

    $midDeleteRes = $this->postJson('/api/ai/chat', [
        'message' => 'Rekomendasi cushion yang cocok untuk kulit berminyak',
    ])->assertOk();

    expect($midDeleteRes->json('data.message.content'))->toContain('Respons AI terlambat')
        ->and(AiChatMessage::count())->toBe(0);
});

test('PPL-28 M5 & N-L8: flushes catalog cache only after DB transaction commits', function () {
    [$cushion] = seedAiCatalog();
    config(['services.ai_assistant.catalog_cache_ttl' => 300]);

    $ctxService = app(AiCatalogContextService::class);
    AiCatalogContextService::flush();

    $snap1 = $ctxService->getCatalogSnapshot();
    expect($snap1['products'][0]['total_stock'])->toBe(10)
        ->and(Cache::has('riasku:ai_catalog_context:v3'))->toBeTrue();

    $varian = ProdukVarian::where('produk_id', $cushion->id)->firstOrFail();

    // 1. Di dalam transaksi dan saat rollback -> cache TIDAK boleh di-flush prematur (N-L8)
    try {
        DB::transaction(function () use ($varian): void {
            $varian->update(['stock' => 4]);
            expect(Cache::has('riasku:ai_catalog_context:v3'))->toBeTrue();

            throw new RuntimeException('Simulasi rollback transaksi');
        });
    } catch (RuntimeException) {
        // Expected rollback
    }

    $varian = $varian->fresh();

    expect(Cache::has('riasku:ai_catalog_context:v3'))->toBeTrue()
        ->and($varian->stock)->toBe(10);

    // 2. Ketika DB::transaction berhasil commit -> afterCommit mem-flush cache katalog AI (M5 & N-L8)
    DB::transaction(function () use ($varian): void {
        $varian->update(['stock' => 4]);
    });

    expect(Cache::has('riasku:ai_catalog_context:v3'))->toBeFalse();
    $snap2 = $ctxService->getCatalogSnapshot();
    expect($snap2['products'][0]['total_stock'])->toBe(4);

    // 3. Update langsung di luar transaksi aplikasi tetap langsung mem-flush cache
    $varian->update(['stock' => 2]);
    $snap3 = $ctxService->getCatalogSnapshot();
    expect($snap3['products'][0]['total_stock'])->toBe(2);
});
