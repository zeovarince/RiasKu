<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaPengiriman extends Model
{
    use HasFactory;

    protected $table = 'zona_pengiriman';

    protected $fillable = [
        'province',
        'city',
        'district',
        'postal_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tarif(): HasMany
    {
        return $this->hasMany(TarifPengiriman::class, 'zona_pengiriman_id');
    }
}
