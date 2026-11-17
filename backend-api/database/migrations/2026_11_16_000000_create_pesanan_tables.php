<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan', function (Blueprint $table): void {
            $table->id();
            $table->string('order_code')->unique();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone', 30);
            $table->text('shipping_address');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20);
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('total');
            $table->timestamps();

            $table->index(['pelanggan_id', 'created_at']);
        });

        Schema::create('pesanan_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->foreignId('produk_varian_id')->nullable()->constrained('produk_varian')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_item');
        Schema::dropIfExists('pesanan');
    }
};
