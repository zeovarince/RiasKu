<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesananItem extends Model
{
    use HasFactory;

    protected $table = 'pesanan_item';

    protected $fillable = [
        'pesanan_id',
        'produk_id',
        'produk_varian_id',
        'product_name',
        'variant_name',
        'sku',
        'product_sku',
        'unit_price',
        'quantity',
        'line_total',
        'subtotal',
        'is_preorder',
        'preorder_estimate_days',
        'stock_deducted',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'quantity' => 'integer',
        'line_total' => 'integer',
        'subtotal' => 'integer',
        'is_preorder' => 'boolean',
        'preorder_estimate_days' => 'integer',
        'stock_deducted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (PesananItem $item): void {
            if ($item->isDirty('product_sku') && ! empty($item->product_sku)) {
                $item->sku = $item->product_sku;
            } elseif ($item->isDirty('sku') && ! empty($item->sku)) {
                $item->product_sku = $item->sku;
            } elseif (empty($item->sku) && ! empty($item->product_sku)) {
                $item->sku = $item->product_sku;
            } elseif (empty($item->product_sku) && ! empty($item->sku)) {
                $item->product_sku = $item->sku;
            }

            if ($item->isDirty('line_total') && $item->line_total !== null) {
                $item->subtotal = $item->line_total;
            } elseif ($item->isDirty('subtotal') && $item->subtotal !== null) {
                $item->line_total = $item->subtotal;
            } elseif ($item->line_total === null && $item->subtotal !== null) {
                $item->line_total = $item->subtotal;
            } elseif ($item->subtotal === null && $item->line_total !== null) {
                $item->subtotal = $item->line_total;
            } elseif ($item->line_total === null && $item->subtotal === null) {
                $computed = (int) $item->unit_price * (int) $item->quantity;
                $item->line_total = $computed;
                $item->subtotal = $computed;
            }
        });
    }

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function varian(): BelongsTo
    {
        return $this->belongsTo(ProdukVarian::class, 'produk_varian_id');
    }
}
