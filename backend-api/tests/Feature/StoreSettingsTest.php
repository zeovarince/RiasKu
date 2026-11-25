<?php

use App\Models\Admin;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function storeSettingsAdminToken(): string
{
    return Admin::create([
        'name' => 'Admin Pengaturan',
        'email' => 'store-settings-admin@riasku.test',
        'password_hash' => Hash::make('password'),
        'is_active' => true,
    ])->createToken('store-settings-test')->plainTextToken;
}

it('returns seeded safe defaults from the public endpoint', function (): void {
    $this->getJson('/api/store-settings')
        ->assertOk()
        ->assertJsonPath('data.store_name', 'RiasKu')
        ->assertJsonPath('data.whatsapp_number', null)
        ->assertJsonPath('data.is_whatsapp_active', false);
});

it('normalizes an Indonesian WhatsApp number and publishes settings after activation', function (): void {
    $token = storeSettingsAdminToken();

    $this->withToken($token)->putJson('/api/admin/settings/store', [
        'store_name' => 'RiasKu Studio',
        'store_tagline' => 'Kosmetik pilihan',
        'store_logo' => null,
        'store_address' => 'Surabaya, Jawa Timur',
        'operational_hours' => 'Senin–Sabtu, 09.00–17.00 WIB',
        'whatsapp_number' => '0812 3456-7890',
        'whatsapp_default_message' => 'Halo RiasKu, saya ingin bertanya tentang {product_name}.',
        'is_whatsapp_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.whatsapp_number', '6281234567890')
        ->assertJsonPath('data.is_whatsapp_active', true);

    $this->getJson('/api/store-settings')
        ->assertOk()
        ->assertJsonPath('data.store_name', 'RiasKu Studio')
        ->assertJsonPath('data.whatsapp_number', '6281234567890')
        ->assertJsonPath('data.is_whatsapp_active', true);
});

it('hides the number from public settings when WhatsApp is disabled', function (): void {
    $token = storeSettingsAdminToken();

    $this->withToken($token)->putJson('/api/admin/settings/store', [
        'store_name' => 'RiasKu',
        'whatsapp_number' => '081234567890',
        'is_whatsapp_active' => false,
    ])->assertOk();

    $this->getJson('/api/store-settings')
        ->assertOk()
        ->assertJsonPath('data.whatsapp_number', null)
        ->assertJsonPath('data.is_whatsapp_active', false);
});

it('protects admin routes from unauthenticated users and customers', function (): void {
    $this->putJson('/api/admin/settings/store', [])->assertUnauthorized();

    $customer = Pelanggan::create([
        'username' => 'store-settings-customer',
        'email' => 'store-settings-customer@riasku.test',
        'password_hash' => Hash::make('password'),
        'is_active' => true,
    ]);
    $customerToken = $customer->createToken('store-settings-customer')->plainTextToken;
    $this->withToken($customerToken)->getJson('/api/admin/settings/store')->assertForbidden();

});

it('requires a valid number when enabling WhatsApp', function (): void {
    $adminToken = storeSettingsAdminToken();

    $this->withToken($adminToken)->putJson('/api/admin/settings/store', [
        'store_name' => 'RiasKu',
        'whatsapp_number' => 'nomor-tidak-valid',
        'is_whatsapp_active' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['whatsapp_number']);
});
