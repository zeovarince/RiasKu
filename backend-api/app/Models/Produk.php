<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        'barcode',
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
        'min_stock',
        'best_seller_score',
        'best_seller_units_sold',
        'best_seller_orders_count',
        'best_seller_last_calculated_at',
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
        'min_stock' => 'integer',
        'best_seller_score' => 'float',
        'best_seller_units_sold' => 'integer',
        'best_seller_orders_count' => 'integer',
        'best_seller_last_calculated_at' => 'datetime',
    ];

    protected $appends = [
        'total_stock',
        'stock_label',
        'needs_restock',
        'restock_status',
        'price_warning',
        'image_url',
        'art',
        'type',
        'tag',
        'rating',
        'review_count',
        'price',
        'variants',
        'is_best_seller',
        'best_seller_rank',
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

    public function stokMasuks(): HasMany
    {
        return $this->hasMany(StokMasuk::class, 'produk_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ProdukFoto::class, 'produk_id')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryFoto(): HasOne
    {
        return $this->hasOne(ProdukFoto::class, 'produk_id')->where('is_primary', true);
    }

    public function ulasans(): HasMany
    {
        return $this->hasMany(UlasanProduk::class, 'produk_id');
    }

    public function getTotalStockAttribute(): int
    {
        if (array_key_exists('active_stock_sum', $this->attributes) && $this->attributes['active_stock_sum'] !== null) {
            return (int) $this->attributes['active_stock_sum'];
        }

        if ($this->relationLoaded('activeVarians')) {
            return (int) $this->activeVarians->sum('stock');
        }

        if ($this->relationLoaded('varians')) {
            return (int) $this->varians->where('is_active', true)->sum('stock');
        }

        return (int) $this->activeVarians()->sum('stock');
    }

    public function scopeNeedsRestock(Builder $query): Builder
    {
        $produkTable = (new Produk)->getTable();
        $varianTable = (new ProdukVarian)->getTable();

        return $query->whereRaw(
            '(SELECT COALESCE(SUM(v.stock), 0) FROM '.$varianTable.' AS v '.
            'WHERE v.produk_id = '.$produkTable.'.id AND v.is_active = ?) <= '.$produkTable.'.min_stock',
            [true]
        );
    }

    public function scopeBestSeller(Builder $query): Builder
    {
        return $query->where('best_seller_score', '>', 0);
    }

    public function getIsBestSellerAttribute(): bool
    {
        return (float) ($this->attributes['best_seller_score'] ?? 0) > 0;
    }

    public function getBestSellerRankAttribute(): ?int
    {
        if (! array_key_exists('best_seller_rank', $this->attributes)) {
            return null;
        }

        return $this->attributes['best_seller_rank'] === null
            ? null
            : (int) $this->attributes['best_seller_rank'];
    }

    public function getNeedsRestockAttribute(): bool
    {
        return $this->total_stock <= (int) ($this->min_stock ?? 0);
    }

    public function getRestockStatusAttribute(): string
    {
        if ($this->total_stock === 0) {
            return 'Stok Habis';
        }

        if ($this->needs_restock) {
            return 'Stok Menipis';
        }

        return 'Stok Aman';
    }

    public function scopeFilterStockLabel(Builder $query, ?string $label): Builder
    {
        $normalized = strtolower(trim((string) $label));

        if ($normalized === '' || $normalized === 'all' || $normalized === 'semua') {
            return $query;
        }

        if (in_array($normalized, ['tersedia', 'ready', 'ready_stock', 'ready stock'], true)) {
            return $query->whereHas('activeVarians', function (Builder $sub): void {
                $sub->where('stock', '>', 0);
            });
        }

        if (in_array($normalized, ['pre-order', 'preorder', 'po', 'pre order'], true)) {
            return $query
                ->where('is_preorder', true)
                ->whereDoesntHave('activeVarians', function (Builder $sub): void {
                    $sub->where('stock', '>', 0);
                });
        }

        if (in_array($normalized, ['stok habis', 'stok_habis', 'habis', 'out_of_stock', 'out of stock'], true)) {
            return $query
                ->where('is_preorder', false)
                ->whereDoesntHave('activeVarians', function (Builder $sub): void {
                    $sub->where('stock', '>', 0);
                });
        }

        return $query->whereRaw('1 = 0');
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
        if (! $this->base_price || ! $this->retail_price) {
            return null;
        }

        $minRecommended = $this->base_price + 1000;
        $maxRecommended = $this->base_price + 2000;

        if ($this->retail_price < $minRecommended || $this->retail_price > $maxRecommended) {
            return 'Harga eceran berada di luar rentang ideal (modal + Rp1.000 sampai Rp2.000). Rekomendasi: Rp'.number_format($minRecommended, 0, ',', '.').' - Rp'.number_format($maxRecommended, 0, ',', '.');
        }

        return null;
    }

    public function getPriceAttribute(): int
    {
        return (int) ($this->retail_price ?? 0);
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('primaryFoto') && $this->primaryFoto) {
            return $this->primaryFoto->image_url;
        }

        if ($this->relationLoaded('fotos') && $this->fotos->isNotEmpty()) {
            return $this->fotos->first()->image_url;
        }

        return $this->primaryFoto?->image_url;
    }

    public function getArtAttribute(): string
    {
        $slug = $this->kategori?->slug ?? '';
        $sku = (string) $this->sku;
        $name = strtolower($this->name);

        if (str_contains($name, 'cushion') || str_contains($sku, 'CUSH')) {
            return 'cushion';
        }
        if (str_contains($name, 'lip') || str_contains($sku, 'LIP')) {
            return 'lip';
        }
        if (str_contains($name, 'powder') || str_contains($sku, 'POWD')) {
            return 'powder';
        }
        if (str_contains($name, 'pengantin') || str_contains($name, 'bridal') || str_contains($sku, 'BRV')) {
            return 'bridal';
        }
        if (str_contains($name, 'wisuda')) {
            return 'crown';
        }
        if ($slug === 'lip-care') {
            return 'lip';
        }
        if ($slug === 'complexion') {
            return 'cushion';
        }
        if ($slug === 'eye-makeup') {
            return 'eye';
        }
        if ($slug === 'rias-pengantin') {
            return 'bridal';
        }
        if ($slug === 'alat-rias') {
            return 'brush';
        }

        return 'cushion';
    }

    public function getTypeAttribute(): string
    {
        $slug = $this->kategori?->slug ?? '';
        $sku = (string) $this->sku;
        if ($slug === 'rias-pengantin' || $slug === 'rias-wisuda' || str_contains($sku, 'BRV')) {
            return 'service';
        }

        return 'product';
    }

    public function getTagAttribute(): ?string
    {
        $sku = (string) $this->sku;

        if (str_contains($sku, 'BRV')) {
            return 'COD SURABAYA';
        }
        if ($this->total_stock <= 3 && $this->total_stock > 0) {
            return 'SISA '.$this->total_stock.' PCS';
        }
        if ($this->total_stock > 0) {
            return 'READY STOCK';
        }

        return null;
    }

    public function getRatingAttribute(): ?string
    {
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            $average = $this->attributes['reviews_avg_rating'];

            return $average === null ? null : number_format((float) $average, 1, '.', '');
        }

        $average = $this->ulasans()->approved()->avg('rating');

        return $average === null ? null : number_format((float) $average, 1, '.', '');
    }

    public function getReviewCountAttribute(): int
    {
        if (array_key_exists('review_count', $this->attributes)) {
            return (int) $this->attributes['review_count'];
        }

        return (int) $this->ulasans()->approved()->count();
    }

    public function getVariantsAttribute(): EloquentCollection
    {
        if ($this->relationLoaded('activeVarians')) {
            return $this->annotateVariants($this->activeVarians);
        }

        if ($this->relationLoaded('varians')) {
            return $this->annotateVariants($this->varians);
        }

        return $this->annotateVariants($this->activeVarians()->get());
    }

    private function annotateVariants(EloquentCollection $variants): EloquentCollection
    {
        $isPreorder = (bool) $this->is_preorder;
        $estimateDays = $this->preorder_estimate_days !== null ? (int) $this->preorder_estimate_days : null;

        $variants->each(function (ProdukVarian $variant) use ($isPreorder, $estimateDays): void {
            $variant->resolvedProductIsPreorder = $isPreorder;
            $variant->resolvedPreorderEstimateDays = $estimateDays;
        });

        return $variants;
    }
}
