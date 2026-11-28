<?php

namespace Database\Seeders;

use App\Models\StoreSettings;
use Illuminate\Database\Seeder;

class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = StoreSettings::query()->firstOrCreate(['id' => 1], StoreSettings::defaults());

        $settings->update([
            'whatsapp_number' => '6285156064912',
            'is_whatsapp_active' => true,
        ]);
    }
}
