<?php

namespace Database\Seeders;

use App\Models\StoreSettings;
use Illuminate\Database\Seeder;

class StoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        StoreSettings::query()->firstOrCreate(['id' => 1], StoreSettings::defaults());
    }
}
