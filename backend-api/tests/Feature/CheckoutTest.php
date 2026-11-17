<?php

use App\Models\Kategori;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use App\Services\ShippingCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCheckoutCustomer(): array
{
    $customer = Pelanggan::create([
        'username' => 'checkout_customer',
        'email' => 'checkout@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    return [$customer, $customer->createToken('checkout-test')->plainTextToken];
}

function createCheckoutVariant(int $stock = 10, int $price = 52000, int $weight = 0): array
{
    $category = Kategori::create([
        'name' => 'Checkout '.uniqid(),
        'slug' => 'checkout-'.uniqid(),
    ]);
    $product = Produk::create([
        'kategori_id' => $category->id,
        'sku' => 'SKU-'.uniqid(),
        'name' => 'Sunscreen',
        'brand' => 'RiasKu',
        'weight_grams' => $weight,
        'base_price' => 50000,
        'retail_price' => $price,
        'is_active' => true,
    ]);
    $variant = ProdukVarian::create([
        'produk_id' => $product->id,
        'name' => '50ml',
        'stock' => $stock,
        'is_active' => true,
    ]);

    return [$product, $variant];
}

function createCheckoutShippingZone(string $city = 'Surabaya', int $pricePerKg = 7000, bool $isActive = true): array
{
    $zone = ZonaPengiriman::create([
        'province' => 'Jawa Timur',
        'city' => $city,
        'is_active' => $isActive,
    ]);
    $rate = TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'price_per_kg' => $pricePerKg,
        'etd_min_days' => 1,
        'etd_max_days' => 2,
        'is_active' => true,
    ]);

    return [$zone, $rate];
}

function checkoutPayload($zone, array $items, string $paymentMethod = 'online'): array
{
    return [
        'name' => 'Pelanggan RiasKu',
        'phone' => '08123456789',
        'address' => 'Jl. Mawar No. 10',
        'payment_method' => $paymentMethod,
        'destination_zone_id' => $zone->id,
        'courier' => 'JNE',
        'service' => 'REG',
        'items' => $items,
    ];
}

it('creates an order using server prices and decrements variant stock', function () {
    [$customer, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant();
    [$zone] = createCheckoutShippingZone();

    $response = $this->withToken($token)->postJson('/api/checkout', array_merge(
        checkoutPayload($zone, [[
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 1,
            'weight_grams' => 999999,
        ]], 'cod'),
        ['shipping_cost' => 1, 'total' => 1],
    ));

    $response->assertCreated()
        ->assertJsonPath('data.pelanggan_id', $customer->id)
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.subtotal', 104000)
        ->assertJsonPath('data.weight_grams', 1000)
        ->assertJsonPath('data.shipping_cost', 7000)
        ->assertJsonPath('data.total', 111000)
        ->assertJsonPath('data.courier_service', 'REG')
        ->assertJsonPath('data.items.0.unit_price', 52000)
        ->assertJsonPath('data.items.0.line_total', 104000);

    expect($variant->fresh()->stock)->toBe(8)
        ->and(Pesanan::count())->toBe(1);
});

it('rejects insufficient stock without creating a partial order', function () {
    [, $token] = createCheckoutCustomer();
    [$firstProduct, $firstVariant] = createCheckoutVariant(5);
    [$secondProduct, $secondVariant] = createCheckoutVariant(1);
    [$zone] = createCheckoutShippingZone();

    $this->withToken($token)->postJson('/api/checkout', checkoutPayload($zone, [
        ['product_id' => $firstProduct->id, 'variant_id' => $firstVariant->id, 'quantity' => 2],
        ['product_id' => $secondProduct->id, 'variant_id' => $secondVariant->id, 'quantity' => 2],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['items.1.quantity']);

    expect($firstVariant->fresh()->stock)->toBe(5)
        ->and($secondVariant->fresh()->stock)->toBe(1)
        ->and(Pesanan::count())->toBe(0);
});

it('calculates total weight across different products and variants', function () {
    [, $token] = createCheckoutCustomer();
    [$firstProduct, $firstVariant] = createCheckoutVariant(10, 52000, 600);
    $secondVariant = ProdukVarian::create([
        'produk_id' => $firstProduct->id,
        'name' => '100ml',
        'stock' => 10,
        'is_active' => true,
    ]);
    [$secondProduct, $secondProductVariant] = createCheckoutVariant(10, 10000, 0);
    [$zone] = createCheckoutShippingZone();
    config(['shipping.default_weight_grams' => 300]);

    $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [
            ['product_id' => $firstProduct->id, 'variant_id' => $firstVariant->id, 'quantity' => 2],
            ['product_id' => $firstProduct->id, 'variant_id' => $secondVariant->id, 'quantity' => 1],
            ['product_id' => $secondProduct->id, 'variant_id' => $secondProductVariant->id, 'quantity' => 3],
        ],
    ])->assertOk()
        ->assertJsonPath('data.weight_grams', 2700)
        ->assertJsonPath('data.options.0.billable_weight_kg', 3)
        ->assertJsonPath('data.options.0.cost', 21000);
});

it('applies each full-kilogram minimum and rounds weight up per kilogram', function () {
    [, $token] = createCheckoutCustomer();
    [$zone] = createCheckoutShippingZone();
    [$heavyProduct, $heavyVariant] = createCheckoutVariant(10, 52000, 1001);
    [$zeroWeightProduct, $zeroWeightVariant] = createCheckoutVariant();
    config(['shipping.default_weight_grams' => 250]);

    $heavyQuote = $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [['product_id' => $heavyProduct->id, 'variant_id' => $heavyVariant->id, 'quantity' => 1]],
    ])->assertOk();
    $heavyQuote->assertJsonPath('data.weight_grams', 1001)
        ->assertJsonPath('data.options.0.billable_weight_kg', 2)
        ->assertJsonPath('data.options.0.cost', 14000);

    $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [['product_id' => $zeroWeightProduct->id, 'variant_id' => $zeroWeightVariant->id, 'quantity' => 1]],
    ])->assertOk()
        ->assertJsonPath('data.weight_grams', 250)
        ->assertJsonPath('data.options.0.billable_weight_kg', 1)
        ->assertJsonPath('data.options.0.cost', 7000);
});

it('rejects inactive zones and missing or inactive shipping services', function () {
    [, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant();
    [$zone, $rate] = createCheckoutShippingZone();
    $payload = checkoutPayload($zone, [
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ]);

    $this->withToken($token)->postJson('/api/checkout', array_merge($payload, ['courier' => 'Kurir Lain']))
        ->assertUnprocessable()->assertJsonValidationErrors(['service']);

    $rate->update(['is_active' => false]);
    $this->withToken($token)->postJson('/api/checkout', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors(['service']);

    $zone->update(['is_active' => false]);
    $this->withToken($token)->postJson('/api/checkout', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors(['destination_zone_id']);
});

it('allows COD only for configured destination cities', function () {
    [, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant();
    [$sidoarjo] = createCheckoutShippingZone('Sidoarjo');
    [$surabaya] = createCheckoutShippingZone('Surabaya');

    $this->withToken($token)->postJson('/api/checkout', checkoutPayload($sidoarjo, [
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ], 'cod'))->assertUnprocessable()->assertJsonValidationErrors(['payment_method']);

    $this->withToken($token)->postJson('/api/checkout', checkoutPayload($surabaya, [
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1],
    ], 'cod'))->assertCreated();
});

it('returns the same server-calculated quote as the saved checkout', function () {
    [, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant(10, 52000, 1250);
    [$zone] = createCheckoutShippingZone('Surabaya', 9000);
    $items = [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2]];
    $quote = $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => $items,
    ])->assertOk()->json('data');

    $order = $this->withToken($token)->postJson('/api/checkout', checkoutPayload($zone, $items))
        ->assertCreated()->json('data');
    $option = collect($quote['options'])->firstWhere('service', 'REG');

    expect($order['weight_grams'])->toBe($quote['weight_grams'])
        ->and($order['billable_weight_kg'])->toBe($option['billable_weight_kg'])
        ->and($order['packaging_weight_grams'])->toBe($quote['packaging_weight_grams'])
        ->and($order['shipping_cost'])->toBe($option['cost'])
        ->and($order['total'])->toBe($order['subtotal'] + $option['cost']);
});

it('returns JNE and J&T services with per-service billable weight and prices', function () {
    [, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant(10, 52000, 250);
    [$zone] = createCheckoutShippingZone('Surabaya', 9000);
    TarifPengiriman::create([
        'zona_pengiriman_id' => $zone->id,
        'courier' => 'J&T',
        'service' => 'EZ',
        'price_per_kg' => 8500,
        'etd_min_days' => 1,
        'etd_max_days' => 2,
        'is_active' => true,
    ]);

    $options = $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]],
    ])->assertOk()->json('data.options');
    $jne = collect($options)->firstWhere('courier', 'JNE');
    $jt = collect($options)->firstWhere('courier', 'J&T');

    expect($jne['billable_weight_kg'])->toBe(1)
        ->and($jne['cost'])->toBe(9000)
        ->and($jt['billable_weight_kg'])->toBe(1)
        ->and($jt['cost'])->toBe(8500);
});

it('adds configured packaging weight once and returns zero cost for free shipping', function () {
    [, $token] = createCheckoutCustomer();
    [$product, $variant] = createCheckoutVariant(10, 52000, 250);
    [$zone] = createCheckoutShippingZone();
    config([
        'shipping.packaging_weight_grams' => 100,
        'shipping.free_shipping_min_subtotal' => 50000,
    ]);

    $this->withToken($token)->postJson('/api/shipping/rates', [
        'destination_zone_id' => $zone->id,
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]],
    ])->assertOk()
        ->assertJsonPath('data.weight_grams', 350)
        ->assertJsonPath('data.packaging_weight_grams', 100)
        ->assertJsonPath('data.options.0.billable_weight_kg', 1)
        ->assertJsonPath('data.options.0.cost', 0);
});

it('provides a volumetric-weight hook using the configured divisor', function () {
    $shipping = app(ShippingCostService::class);
    config(['shipping.volumetric_divisor_cm3_per_kg' => 6000]);

    expect($shipping->beratVolumetrikGrams(30, 20, 10))->toBe(1000)
        ->and($shipping->beratAktualAtauVolumetrik(800, [
            'length' => 30,
            'width' => 20,
            'height' => 10,
        ]))->toBe(1000)
        ->and($shipping->beratAktualAtauVolumetrik(1200, [
            'length' => 30,
            'width' => 20,
            'height' => 10,
        ]))->toBe(1200);
});

it('only returns a customer own orders', function () {
    [$customer, $token] = createCheckoutCustomer();
    [$otherCustomer] = createCheckoutCustomerWithUniqueEmail();
    $order = Pesanan::create([
        'order_code' => 'RSK-TEST-ORDER',
        'pelanggan_id' => $customer->id,
        'recipient_name' => 'Pelanggan RiasKu',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Mawar No. 10, Surabaya',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'status' => 'pending',
        'subtotal' => 0,
        'shipping_cost' => 0,
        'total' => 0,
    ]);
    $otherOrder = Pesanan::create([
        'order_code' => 'RSK-OTHER-ORDER',
        'pelanggan_id' => $otherCustomer->id,
        'recipient_name' => 'Pelanggan Lain',
        'recipient_phone' => '08123456789',
        'shipping_address' => 'Jl. Kenanga No. 1',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'status' => 'pending',
        'subtotal' => 0,
        'shipping_cost' => 0,
        'total' => 0,
    ]);

    $this->withToken($token)->getJson('/api/orders')
        ->assertOk()
        ->assertJsonFragment(['order_code' => $order->order_code])
        ->assertJsonMissing(['order_code' => $otherOrder->order_code]);
    $this->withToken($token)->getJson('/api/orders/'.$otherOrder->order_code)->assertNotFound();
});

function createCheckoutCustomerWithUniqueEmail(): array
{
    $customer = Pelanggan::create([
        'username' => 'another_customer',
        'email' => 'another-checkout@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    return [$customer, $customer->createToken('checkout-test')->plainTextToken];
}
