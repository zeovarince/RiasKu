<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesanan extends Model
{
    use HasFactory;

    protected $table = 'pesanan';

    protected $fillable = [
        'order_code',
        'pelanggan_id',
        'recipient_name',
        'recipient_phone',
        'shipping_address',
        'payment_method',
        'payment_status',
        'status',
        'subtotal',
        'shipping_cost',
        'total',
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
        'shipping_zone_id' => 'integer',
        'weight_grams' => 'integer',
    ];

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
}
