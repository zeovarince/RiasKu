<?php

use App\Models\Admin;
use App\Models\Pelanggan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('admin', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password_hash');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('pelanggan', function (Blueprint $table): void {
        $table->id();
        $table->string('username')->unique();
        $table->string('email')->unique();
        $table->string('password_hash')->nullable();
        $table->string('google_id')->nullable()->unique();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('personal_access_tokens', function (Blueprint $table): void {
        $table->id();
        $table->morphs('tokenable');
        $table->text('name');
        $table->string('token', 64)->unique();
        $table->text('abilities')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });
});

it('logs in admin and returns the authenticated admin profile', function (): void {
    Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'strong-password',
    ])->assertOk()->assertJsonPath('data.user.account_type', 'admin');
    $token = $login->json('data.token');

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('data.user.name', 'Admin RiasKu');
    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    expect(Admin::firstOrFail()->tokens()->count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('returns the same admin credential error for missing accounts and wrong passwords', function (): void {
    $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong-password'])
        ->assertUnauthorized()->assertJsonPath('message', 'email atau password salah');

    Admin::create([
        'name' => 'Admin RiasKu',
        'email' => 'admin@example.test',
        'password_hash' => 'strong-password',
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/login', ['email' => 'admin@example.test', 'password' => 'wrong-password'])
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

    $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
    $this->withToken($token)->getJson('/api/user')->assertForbidden();
});

it('requires admin authentication for protected routes', function (): void {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->getJson('/api/user')->assertUnauthorized();
});
