<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keranjang', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('pelanggan_id');
        });

        Schema::create('keranjang_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('keranjang_id')->constrained('keranjang')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('produk_varian_id')->nullable()->constrained('produk_varian')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['keranjang_id', 'produk_id', 'produk_varian_id'], 'keranjang_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keranjang_item');
        Schema::dropIfExists('keranjang');
    }
};
