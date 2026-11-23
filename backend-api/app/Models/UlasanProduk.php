<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UlasanProduk extends Model
{
    use HasFactory;

    protected $table = 'ulasan_produk';

    protected $fillable = [
        'produk_id',
        'pelanggan_id',
        'user_name',
        'rating',
        'comment',
        'status',
    ];

    protected $attributes = [
        'status' => 'approved',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }
}
