<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'produk';

    protected $fillable = [
        'kategori_id',
        'sku',
        'name',
        'brand',
        'weight_grams',
        'base_price',
        'retail_price',
        'pack_quantity',
        'pack_price',
        'is_preorder',
        'preorder_estimate_days',
        'short_ingredients',
        'bpom_number',
        'description',
        'is_active',
    ];

    protected $casts = [
        'weight_grams' => 'integer',
        'base_price' => 'integer',
        'retail_price' => 'integer',
        'pack_quantity' => 'integer',
        'pack_price' => 'integer',
        'is_preorder' => 'boolean',
        'preorder_estimate_days' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'total_stock',
        'stock_label',
        'price_warning',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function varians(): HasMany
    {
        return $this->hasMany(ProdukVarian::class, 'produk_id');
    }

    public function activeVarians(): HasMany
    {
        return $this->hasMany(ProdukVarian::class, 'produk_id')->where('is_active', true);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ProdukFoto::class, 'produk_id')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryFoto(): HasOne
    {
        return $this->hasOne(ProdukFoto::class, 'produk_id')->where('is_primary', true);
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->relationLoaded('varians')) {
            return (int) $this->varians->sum('stock');
        }

        return (int) $this->varians()->sum('stock');
    }

    public function getStockLabelAttribute(): string
    {
        $stock = $this->total_stock;

        if ($stock > 0) {
            return 'Tersedia';
        }

        if ($this->is_preorder) {
            return 'Pre-Order';
        }

        return 'Stok Habis';
    }

    public function getPriceWarningAttribute(): ?string
    {
        if (!$this->base_price || !$this->retail_price) {
            return null;
        }

        $minRecommended = $this->base_price + 1000;
        $maxRecommended = $this->base_price + 2000;

        if ($this->retail_price < $minRecommended || $this->retail_price > $maxRecommended) {
            return 'Harga eceran berada di luar rentang ideal (modal + Rp1.000 sampai Rp2.000). Rekomendasi: Rp' . number_format($minRecommended, 0, ',', '.') . ' - Rp' . number_format($maxRecommended, 0, ',', '.');
        }

        return null;
    }
}
