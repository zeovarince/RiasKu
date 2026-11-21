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
        'order_code',
        'pelanggan_id',
        'recipient_name',
        'recipient_phone',
        'customer_name',
        'customer_phone',
        'shipping_address',
        'shipping_city',
        'shipping_district',
        'shipping_postal_code',
        'shipping_courier',
        'shipping_cost',
        'subtotal',
        'total_product_amount',
        'total',
        'total_amount',
        'payment_method',
        'payment_channel',
        'payment_status',
        'status',
        'order_status',
        'shipping_zone_id',
        'destination_province',
        'destination_city',
        'destination_district',
        'destination_postal_code',
        'courier',
        'courier_service',
        'shipping_etd',
        'weight_grams',
        'tracking_number',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'total' => 'integer',
        'total_product_amount' => 'integer',
        'total_amount' => 'integer',
        'shipping_zone_id' => 'integer',
        'weight_grams' => 'integer',
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

        static::saving(function (Pesanan $pesanan): void {
            // Sinkronkan pasangan kolom skema PPL-17/18 dan PPL-19/20 agar konsisten di PostgreSQL & SQLite
            $pesanan->recipient_name = $pesanan->recipient_name ?? $pesanan->customer_name ?? '';
            $pesanan->customer_name = $pesanan->customer_name ?? $pesanan->recipient_name ?? '';

            $pesanan->recipient_phone = $pesanan->recipient_phone ?? $pesanan->customer_phone ?? '';
            $pesanan->customer_phone = $pesanan->customer_phone ?? $pesanan->recipient_phone ?? '';

            $pesanan->subtotal = $pesanan->subtotal ?? $pesanan->total_product_amount ?? 0;
            $pesanan->total_product_amount = $pesanan->total_product_amount ?? $pesanan->subtotal ?? 0;

            $pesanan->shipping_cost = $pesanan->shipping_cost ?? 0;

            $pesanan->total = $pesanan->total ?? $pesanan->total_amount ?? ($pesanan->subtotal + $pesanan->shipping_cost);
            $pesanan->total_amount = $pesanan->total_amount ?? $pesanan->total ?? ($pesanan->total_product_amount + $pesanan->shipping_cost);

            $pesanan->order_status = $pesanan->order_status ?? $pesanan->status ?? 'menunggu_pembayaran';
            $pesanan->status = $pesanan->status ?? $pesanan->order_status ?? 'pending';

            $pesanan->shipping_city = $pesanan->shipping_city ?? $pesanan->destination_city;
            $pesanan->destination_city = $pesanan->destination_city ?? $pesanan->shipping_city;

            $pesanan->shipping_district = $pesanan->shipping_district ?? $pesanan->destination_district;
            $pesanan->destination_district = $pesanan->destination_district ?? $pesanan->shipping_district;

            $pesanan->shipping_postal_code = $pesanan->shipping_postal_code ?? $pesanan->destination_postal_code;
            $pesanan->destination_postal_code = $pesanan->destination_postal_code ?? $pesanan->shipping_postal_code;

            if (empty($pesanan->shipping_courier) && ($pesanan->courier || $pesanan->courier_service)) {
                $pesanan->shipping_courier = trim(($pesanan->courier ?? '') . ' ' . ($pesanan->courier_service ?? ''));
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaPengiriman::class, 'shipping_zone_id');
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
        $amount = $this->total_amount ?? $this->total ?? 0;
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
