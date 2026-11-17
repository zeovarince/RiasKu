<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifPengiriman extends Model
{
    use HasFactory;

    protected $table = 'tarif_pengiriman';

    protected $fillable = [
        'zona_pengiriman_id',
        'courier',
        'service',
        'price_per_kg',
        'etd_min_days',
        'etd_max_days',
        'is_active',
    ];

    protected $casts = [
        'price_per_kg' => 'integer',
        'etd_min_days' => 'integer',
        'etd_max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaPengiriman::class, 'zona_pengiriman_id');
    }
}
