<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';

    protected $fillable = [
        'pesanan_id',
        'transaction_id',
        'payment_type',
        'bank',
        'va_number',
        'bill_key',
        'biller_code',
        'qr_string',
        'qr_url',
        'deeplink_url',
        'gross_amount',
        'transaction_status',
        'payload_response',
        'paid_at',
        'expiry_time',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'payload_response' => 'array',
        'paid_at' => 'datetime',
        'expiry_time' => 'datetime',
    ];

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }
}
