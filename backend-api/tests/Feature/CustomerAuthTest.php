<?php

use App\Models\Admin;
use App\Models\Pelanggan;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('registers customers and stores a password hash', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'username' => 'beauty_user',
        'email' => 'beauty@example.test',
        'password' => 'strong-password',
    ]);

    $response->assertCreated()->assertJsonPath('data.user.account_type', 'pelanggan');
    $customer = Pelanggan::firstOrFail();
    expect($customer->password_hash)->not->toBe('strong-password')
        ->and(Hash::check('strong-password', $customer->password_hash))->toBeTrue();
});

it('rejects duplicate customer email and username', function (): void {
    Pelanggan::create([
        'username' => 'beauty_user',
        'email' => 'beauty@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/register', [
        'username' => 'beauty_user',
        'email' => 'beauty@example.test',
        'password' => 'strong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email']);
});

it('logs in, returns the current customer, and revokes only the current token on logout', function (): void {
    Pelanggan::create([
        'username' => 'beauty_user',
        'email' => 'beauty@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'beauty@example.test',
        'password' => 'strong-password',
    ])->assertOk()->assertJsonPath('data.user.account_type', 'pelanggan');
    $token = $login->json('data.token');

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('data.user.email', 'beauty@example.test');
    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    expect(Pelanggan::firstOrFail()->tokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('uses the same message for unknown email and incorrect password', function (): void {
    $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong-pass'])
        ->assertUnauthorized()->assertJsonPath('message', 'email atau password salah');

    Pelanggan::create([
        'username' => 'known_user',
        'email' => 'known@example.test',
        'password_hash' => 'correct-password',
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/login', ['email' => 'known@example.test', 'password' => 'wrong-pass'])
        ->assertUnauthorized()->assertJsonPath('message', 'email atau password salah');
});

it('allows admin to log in via public auth login and returns admin account type', function (): void {
    Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@riasku.com',
        'password_hash' => Hash::make('password123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'admin@riasku.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.account_type', 'admin')
        ->assertJsonPath('data.user.email', 'admin@riasku.com');
});

it('blocks inactive customers and guides passwordless customers to Google', function (): void {
    Pelanggan::create([
        'username' => 'inactive_user',
        'email' => 'inactive@example.test',
        'password_hash' => 'strong-password',
        'is_active' => false,
    ]);
    Pelanggan::create([
        'username' => 'google_user',
        'email' => 'google@example.test',
        'password_hash' => null,
        'google_id' => 'google-subject',
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/login', ['email' => 'inactive@example.test', 'password' => 'strong-password'])
        ->assertForbidden()->assertJsonPath('message', 'Akun pelanggan dinonaktifkan.');
    $this->postJson('/api/auth/login', ['email' => 'google@example.test', 'password' => 'strong-password'])
        ->assertUnprocessable()->assertJsonPath('message', 'Akun ini menggunakan Google. Pilih tombol Masuk dengan Google.');
});

it('creates a new customer from a verified Google identity', function (): void {
    $verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $verifier->shouldReceive('verify')->once()->with('google-token')->andReturn([
        'sub' => 'google-subject',
        'email' => 'new.google@example.test',
        'email_verified' => true,
        'name' => 'New Google User',
    ]);
    $this->app->instance(GoogleIdentityVerifier::class, $verifier);

    $response = $this->postJson('/api/auth/google', ['credential' => 'google-token']);

    $response->assertOk()->assertJsonPath('data.user.email', 'new.google@example.test');
    expect(Pelanggan::where('google_id', 'google-subject')->value('password_hash'))->toBeNull();
});

it('links only verified Google email to an existing customer', function (): void {
    Pelanggan::create([
        'username' => 'existing_user',
        'email' => 'existing@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);
    $verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn([
        'sub' => 'google-subject',
        'email' => 'existing@example.test',
        'email_verified' => true,
    ]);
    $this->app->instance(GoogleIdentityVerifier::class, $verifier);

    $this->postJson('/api/auth/google', ['credential' => 'google-token'])->assertOk();
    expect(Pelanggan::count())->toBe(1)
        ->and(Pelanggan::first()->google_id)->toBe('google-subject');
});

it('blocks an inactive customer from Google login', function (): void {
    Pelanggan::create([
        'username' => 'inactive_google_user',
        'email' => 'inactive.google@example.test',
        'password_hash' => null,
        'is_active' => false,
    ]);
    $verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn([
        'sub' => 'google-subject',
        'email' => 'inactive.google@example.test',
        'email_verified' => true,
    ]);
    $this->app->instance(GoogleIdentityVerifier::class, $verifier);

    $this->postJson('/api/auth/google', ['credential' => 'google-token'])
        ->assertForbidden()->assertJsonPath('message', 'Akun pelanggan dinonaktifkan.');
});

it('rejects an unverified Google identity', function (): void {
    $verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $verifier->shouldReceive('verify')->once()->andReturn([
        'sub' => 'google-subject',
        'email' => 'unverified@example.test',
        'email_verified' => false,
    ]);
    $this->app->instance(GoogleIdentityVerifier::class, $verifier);

    $this->postJson('/api/auth/google', ['credential' => 'google-token'])->assertUnauthorized();
    expect(Pelanggan::count())->toBe(0);
});

it('rejects an admin token from customer protected routes', function (): void {
    $admin = Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);
    $token = $admin->createToken('admin-test')->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
    $this->withToken($token)->getJson('/api/orders')->assertForbidden();
    $this->withToken($token)->postJson('/api/checkout')->assertForbidden();
});

it('requires authentication for customer functions and only permits customer tokens', function (): void {
    // 401 bila tanpa token
    $this->getJson('/api/orders')->assertUnauthorized();
    $this->postJson('/api/checkout')->assertUnauthorized();

    // 403 bila peran salah (token admin)
    $admin = Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin_role@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;
    $this->withToken($adminToken)->getJson('/api/orders')->assertForbidden()->assertJsonPath('message', 'Akses hanya untuk pelanggan.');
    $this->withToken($adminToken)->postJson('/api/checkout')->assertForbidden()->assertJsonPath('message', 'Akses hanya untuk pelanggan.');

    // 200 bila token pelanggan valid
    $this->app['auth']->forgetGuards();
    $pelanggan = Pelanggan::create([
        'username' => 'customer_valid',
        'email' => 'valid@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);
    $customerToken = $pelanggan->createToken('customer-token')->plainTextToken;
    $this->withToken($customerToken)->getJson('/api/orders')->assertOk();
    $this->withToken($customerToken)->postJson('/api/checkout')->assertOk();
});
