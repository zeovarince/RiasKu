<?php

use App\Models\Admin;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in admin and returns the authenticated admin profile', function (): void {
    Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $login = $this->postJson('/api/admin/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'strong-password',
    ])->assertOk()->assertJsonPath('data.user.account_type', 'admin');
    $token = $login->json('data.token');

    $this->withToken($token)->getJson('/api/admin/auth/me')
        ->assertOk()->assertJsonPath('data.user.name', 'Admin RiasKu');
    $this->withToken($token)->postJson('/api/admin/auth/logout')->assertOk();
    expect(Admin::firstOrFail()->tokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/admin/auth/me')->assertUnauthorized();
});

it('returns the same admin credential error for missing accounts and wrong passwords', function (): void {
    $this->postJson('/api/admin/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong-password'])
        ->assertUnauthorized()->assertJsonPath('message', 'email atau password salah');

    Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $this->postJson('/api/admin/auth/login', ['email' => 'admin@example.test', 'password' => 'wrong-password'])
        ->assertUnauthorized()->assertJsonPath('message', 'email atau password salah');
});

it('rejects customer tokens from every dashboard protected route', function (): void {
    $customer = Pelanggan::create([
        'username' => 'customer_user',
        'email' => 'customer@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);
    $token = $customer->createToken('customer-test')->plainTextToken;

    $this->withToken($token)->getJson('/api/admin/auth/me')->assertForbidden();
    $this->withToken($token)->getJson('/api/admin/user')->assertForbidden();
});

it('requires admin authentication for protected routes', function (): void {
    $this->getJson('/api/admin/auth/me')->assertUnauthorized();
    $this->getJson('/api/admin/user')->assertUnauthorized();
});
