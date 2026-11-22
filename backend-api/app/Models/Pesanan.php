<?php

namespace App\Models;

use App\Services\OrderStatusService;
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
        'has_preorder_items',
        'preorder_estimate_days',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'shipping_cost' => 'integer',
        'total' => 'integer',
        'total_product_amount' => 'integer',
        'total_amount' => 'integer',
        'shipping_zone_id' => 'integer',
        'weight_grams' => 'integer',
        'has_preorder_items' => 'boolean',
        'preorder_estimate_days' => 'integer',
    ];

    protected $appends = [
        'total_items_count',
        'formatted_total',
        'allowed_next_statuses',
        'resolved_payment_status',
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
            $pesanan->syncSchemaAliases();
        });
    }

    public function syncSchemaAliases(): void
    {
        $this->syncPair('customer_name', 'recipient_name');
        $this->syncPair('customer_phone', 'recipient_phone');
        $this->syncPair('shipping_city', 'destination_city');
        $this->syncPair('shipping_district', 'destination_district');
        $this->syncPair('shipping_postal_code', 'destination_postal_code');

        if ($this->isDirty('shipping_courier') && ! empty($this->shipping_courier)) {
            $this->courier = $this->shipping_courier;
        } elseif ($this->isDirty('courier') && ! empty($this->courier)) {
            $this->shipping_courier = trim($this->courier.' '.($this->courier_service ?? ''));
        } elseif (empty($this->courier) && ! empty($this->shipping_courier)) {
            $this->courier = $this->shipping_courier;
        } elseif (empty($this->shipping_courier) && ! empty($this->courier)) {
            $this->shipping_courier = trim($this->courier.' '.($this->courier_service ?? ''));
        }

        if ($this->isDirty('total_product_amount') && $this->total_product_amount !== null) {
            $this->subtotal = $this->total_product_amount;
        } elseif ($this->isDirty('subtotal') && $this->subtotal !== null) {
            $this->total_product_amount = $this->subtotal;
        } elseif ($this->subtotal === null && $this->total_product_amount !== null) {
            $this->subtotal = $this->total_product_amount;
        } elseif ($this->total_product_amount === null && $this->subtotal !== null) {
            $this->total_product_amount = $this->subtotal;
        }

        if ($this->isDirty('total_amount') && $this->total_amount !== null) {
            $this->total = $this->total_amount;
        } elseif ($this->isDirty('total') && $this->total !== null) {
            $this->total_amount = $this->total;
        } elseif ($this->total === null && $this->total_amount !== null) {
            $this->total = $this->total_amount;
        } elseif ($this->total_amount === null && $this->total !== null) {
            $this->total_amount = $this->total;
        }

        if ($this->isDirty('order_status') && ! empty($this->order_status)) {
            $this->status = $this->order_status;
        } elseif ($this->isDirty('status') && ! empty($this->status) && $this->status !== 'pending') {
            $this->order_status = $this->status;
        } elseif (! empty($this->order_status) && (empty($this->status) || $this->status === 'pending')) {
            $this->status = $this->order_status;
        } elseif (empty($this->order_status) && ! empty($this->status)) {
            $this->order_status = $this->status === 'pending'
                ? ($this->isCodOrder() ? 'diproses' : 'menunggu_pembayaran')
                : $this->status;
        }

        if ($this->payment_status === 'pending' && ! $this->isCodOrder()) {
            $this->payment_status = 'menunggu_pembayaran';
        }
    }

    private function syncPair(string $primary, string $legacy): void
    {
        if ($this->isDirty($primary) && ! empty($this->{$primary})) {
            $this->{$legacy} = $this->{$primary};
        } elseif ($this->isDirty($legacy) && ! empty($this->{$legacy})) {
            $this->{$primary} = $this->{$legacy};
        } elseif (empty($this->{$legacy}) && ! empty($this->{$primary})) {
            $this->{$legacy} = $this->{$primary};
        } elseif (empty($this->{$primary}) && ! empty($this->{$legacy})) {
            $this->{$primary} = $this->{$legacy};
        }
    }

    public function resolvedPaymentStatus(): string
    {
        $raw = strtolower(trim((string) ($this->payment_status ?? '')));

        return match ($raw) {
            'lunas', 'settlement', 'dibayar' => 'dibayar',
            'unpaid', 'menunggu_pembayaran_cod' => 'menunggu_pembayaran_cod',
            'pending', 'menunggu', '' => $this->isCodOrder() ? 'menunggu_pembayaran_cod' : 'menunggu_pembayaran',
            default => $raw,
        };
    }

    public function getResolvedPaymentStatusAttribute(): string
    {
        return $this->resolvedPaymentStatus();
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

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PesananStatusLog::class, 'pesanan_id')->orderBy('id');
    }

    public function resolvedOrderStatus(): string
    {
        $raw = strtolower(trim((string) ($this->order_status ?: $this->status ?: 'menunggu_pembayaran')));

        if ($raw === 'pending') {
            return $this->isCodOrder() ? 'diproses' : 'menunggu_pembayaran';
        }

        return $raw;
    }

    public function isCodOrder(): bool
    {
        return strtolower((string) $this->payment_method) === 'cod'
            || strtolower((string) $this->payment_channel) === 'cash';
    }

    public static function maskPhone(?string $phone): string
    {
        $cleaned = trim((string) $phone);
        $length = strlen($cleaned);

        if ($cleaned === '') {
            return '-';
        }

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        if ($length <= 7) {
            return substr($cleaned, 0, 2).'****'.substr($cleaned, -2);
        }

        return substr($cleaned, 0, 4).'****'.substr($cleaned, -3);
    }

    /**
     * @return list<string>
     */
    public function getAllowedNextStatusesAttribute(): array
    {
        return OrderStatusService::allowedTransitions($this->resolvedOrderStatus());
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

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
