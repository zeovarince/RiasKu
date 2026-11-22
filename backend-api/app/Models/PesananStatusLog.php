<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesananStatusLog extends Model
{
    protected $table = 'pesanan_status_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'pesanan_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'actor_name',
        'tracking_number',
        'note',
        'created_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }
}
