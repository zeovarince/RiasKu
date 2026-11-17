<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('produk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori')->restrictOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->string('brand');
            $table->unsignedInteger('weight_grams')->default(0);
            $table->unsignedInteger('base_price'); // modal
            $table->unsignedInteger('retail_price'); // eceran
            $table->unsignedInteger('pack_quantity')->nullable();
            $table->unsignedInteger('pack_price')->nullable(); // harga per pcs saat beli pack
            $table->boolean('is_preorder')->default(false);
            $table->unsignedTinyInteger('preorder_estimate_days')->nullable(); // 1 - 6 hari
            $table->text('short_ingredients')->nullable();
            $table->string('bpom_number')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('produk_varian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['produk_id', 'name']);
        });

        Schema::create('produk_foto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_foto');
        Schema::dropIfExists('produk_varian');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('kategori');
    }
};
