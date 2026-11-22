<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdukVarian extends Model
{
    use HasFactory;

    protected $table = 'produk_varian';

    protected $fillable = [
        'produk_id',
        'name',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'stock_label',
        'is_preorder_item',
        'preorder_estimate_days',
    ];

    public ?bool $resolvedProductIsPreorder = null;

    public ?int $resolvedPreorderEstimateDays = null;

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function resolveStockLabel(bool $productIsPreorder): string
    {
        if (! $this->is_active) {
            return 'Nonaktif';
        }

        if ($this->stock > 0) {
            return 'Tersedia';
        }

        if ($productIsPreorder) {
            return 'Pre-Order';
        }

        return 'Stok Habis';
    }

    public function getStockLabelAttribute(): string
    {
        $isPreorder = $this->resolveProductIsPreorderFlag();

        return $this->resolveStockLabel($isPreorder);
    }

    public function getIsPreorderItemAttribute(): bool
    {
        if (! $this->is_active || $this->stock > 0) {
            return false;
        }

        return $this->resolveProductIsPreorderFlag();
    }

    public function getPreorderEstimateDaysAttribute(): ?int
    {
        if (! $this->is_preorder_item) {
            return null;
        }

        if ($this->resolvedPreorderEstimateDays !== null) {
            return $this->resolvedPreorderEstimateDays;
        }

        return $this->produk?->preorder_estimate_days;
    }

    private function resolveProductIsPreorderFlag(): bool
    {
        if ($this->resolvedProductIsPreorder !== null) {
            return $this->resolvedProductIsPreorder;
        }

        if ($this->stock > 0 || ! $this->is_active) {
            return false;
        }

        return (bool) $this->produk?->is_preorder;
    }
}
