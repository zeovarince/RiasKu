<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zona_pengiriman', function (Blueprint $table): void {
            $table->id();
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city', 'district', 'postal_code'], 'zona_city_district_postal_unique');
        });

        Schema::create('tarif_pengiriman', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zona_pengiriman_id')->constrained('zona_pengiriman')->cascadeOnDelete();
            $table->string('courier');
            $table->string('service');
            $table->unsignedBigInteger('price_per_kg');
            $table->unsignedSmallInteger('etd_min_days');
            $table->unsignedSmallInteger('etd_max_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['zona_pengiriman_id', 'courier', 'service'], 'tarif_zone_courier_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_pengiriman');
        Schema::dropIfExists('zona_pengiriman');
    }
};
