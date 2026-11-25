<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'store_name',
    'store_tagline',
    'store_logo',
    'store_address',
    'operational_hours',
    'whatsapp_number',
    'whatsapp_default_message',
    'is_whatsapp_active',
])]
class StoreSettings extends Model
{
    protected $table = 'store_settings';

    protected function casts(): array
    {
        return [
            'is_whatsapp_active' => 'boolean',
        ];
    }

    public static function defaults(): array
    {
        return [
            'store_name' => 'RiasKu',
            'store_tagline' => '',
            'store_logo' => null,
            'store_address' => '',
            'operational_hours' => '',
            'whatsapp_number' => null,
            'whatsapp_default_message' => 'Halo RiasKu, saya ingin bertanya.',
            'is_whatsapp_active' => false,
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], static::defaults());
    }
}
