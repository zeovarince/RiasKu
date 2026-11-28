<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProdukFoto extends Model
{
    use HasFactory;

    protected $table = 'produk_foto';

    protected $fillable = [
        'produk_id',
        'image_path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function getImageUrlAttribute(): string
    {
        if (
            str_starts_with($this->image_path, 'http://')
            || str_starts_with($this->image_path, 'https://')
            || str_starts_with($this->image_path, '/')
        ) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
