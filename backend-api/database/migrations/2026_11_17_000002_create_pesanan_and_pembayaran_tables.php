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
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('order_code', 50)->unique();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 30);
            $table->text('shipping_address');
            $table->string('shipping_city', 100);
            $table->string('shipping_district', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_courier', 100)->default('Kurir Toko RiasKu');
            $table->unsignedInteger('shipping_cost')->default(0);
            $table->unsignedInteger('total_product_amount');
            $table->unsignedInteger('total_amount');
            $table->string('payment_method', 20); // 'cod' atau 'online'
            $table->string('payment_channel', 50); // 'qris', 'bca_va', 'bni_va', 'bri_va', 'mandiri_va', 'gopay', 'shopeepay', 'dana', 'cash'
            $table->string('payment_status', 30)->default('menunggu_pembayaran');
            $table->string('order_status', 30)->default('menunggu_pembayaran');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pelanggan_id', 'order_status']);
            $table->index('order_code');
        });

        Schema::create('pesanan_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->restrictOnDelete();
            $table->foreignId('produk_varian_id')->nullable()->constrained('produk_varian')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('product_sku')->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');
            $table->timestamps();
        });

        Schema::create('pembayaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->string('transaction_id', 100)->unique();
            $table->string('payment_type', 50); // 'qris', 'bank_transfer', 'echannel', 'cstore', 'cod_cash'
            $table->string('bank', 30)->nullable();
            $table->string('va_number', 50)->nullable();
            $table->string('bill_key', 50)->nullable();
            $table->string('biller_code', 50)->nullable();
            $table->text('qr_string')->nullable();
            $table->text('qr_url')->nullable();
            $table->text('deeplink_url')->nullable();
            $table->unsignedInteger('gross_amount');
            $table->string('transaction_status', 50)->default('pending'); // 'pending', 'settlement', 'expire', 'cancel', 'deny'
            $table->json('payload_response')->nullable(); // Disimpan sebagai JSON/JSONB di PostgreSQL
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expiry_time')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('pesanan_item');
        Schema::dropIfExists('pesanan');
    }
};
