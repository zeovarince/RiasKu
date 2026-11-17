<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['username', 'email', 'password_hash', 'google_id', 'is_active'])]
#[Hidden(['password_hash', 'google_id'])]
class Pelanggan extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'pelanggan';

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function keranjang(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Keranjang::class, 'pelanggan_id');
    }
}
