<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Pesanan extends Model
{
    use HasFactory;

    protected $table = 'pesanan';

    protected $fillable = [
        'pelanggan_id',
        'order_code',
        'customer_name',
        'customer_phone',
        'shipping_address',
        'shipping_city',
        'shipping_district',
        'shipping_postal_code',
        'shipping_courier',
        'shipping_cost',
        'total_product_amount',
        'total_amount',
        'payment_method',
        'payment_channel',
        'payment_status',
        'order_status',
        'notes',
    ];

    protected $casts = [
        'shipping_cost' => 'integer',
        'total_product_amount' => 'integer',
        'total_amount' => 'integer',
    ];

    protected $appends = [
        'total_items_count',
        'formatted_total',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pesanan $pesanan): void {
            if (empty($pesanan->order_code)) {
                $date = now()->format('Ymd');
                $random = strtoupper(Str::random(5));
                $pesanan->order_code = "RSK-{$date}-{$random}";
            }
        });
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PesananItem::class, 'pesanan_id');
    }

    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'pesanan_id');
    }

    public function latestPembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class, 'pesanan_id')->latestOfMany();
    }

    public function getTotalItemsCountAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum('quantity');
        }

        return (int) $this->items()->sum('quantity');
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }
}
