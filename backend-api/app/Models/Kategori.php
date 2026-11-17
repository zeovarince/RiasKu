<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Kategori extends Model
{
    use HasFactory;

    protected $table = 'kategori';

    protected $fillable = [
        'name',
        'slug',
        'icon',
    ];

    protected static function booted(): void
    {
        static::creating(function (Kategori $kategori): void {
            if (empty($kategori->slug)) {
                $kategori->slug = Str::slug($kategori->name);
            }
        });

        static::updating(function (Kategori $kategori): void {
            if ($kategori->isDirty('name') && !$kategori->isDirty('slug')) {
                $kategori->slug = Str::slug($kategori->name);
            }
        });
    }

    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }
}
