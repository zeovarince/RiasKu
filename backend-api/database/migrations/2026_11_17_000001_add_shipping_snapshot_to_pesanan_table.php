<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table): void {
            $table->foreignId('shipping_zone_id')->nullable()->constrained('zona_pengiriman')->nullOnDelete();
            $table->string('destination_province')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('destination_district')->nullable();
            $table->string('destination_postal_code')->nullable();
            $table->string('courier')->nullable();
            $table->string('courier_service')->nullable();
            $table->string('shipping_etd')->nullable();
            $table->unsignedInteger('weight_grams')->default(0);
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_zone_id');
            $table->dropColumn([
                'destination_province',
                'destination_city',
                'destination_district',
                'destination_postal_code',
                'courier',
                'courier_service',
                'shipping_etd',
                'weight_grams',
                'tracking_number',
                'notes',
            ]);
        });
    }
};
