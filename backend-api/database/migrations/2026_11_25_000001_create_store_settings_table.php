<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('store_name', 120)->default('RiasKu');
            $table->string('store_tagline', 255)->default('');
            $table->string('store_logo', 2048)->nullable();
            $table->text('store_address')->default('');
            $table->string('operational_hours', 500)->default('');
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('whatsapp_default_message', 500)->default('Halo RiasKu, saya ingin bertanya.');
            $table->boolean('is_whatsapp_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
