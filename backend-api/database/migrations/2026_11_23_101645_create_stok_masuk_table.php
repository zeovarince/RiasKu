<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_masuk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('produk_varian_id')->constrained('produk_varian')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost')->nullable();
            $table->string('supplier', 150)->nullable();
            $table->text('notes')->nullable();
            $table->date('tanggal_masuk');
            $table->foreignId('recorded_by')->constrained('admin')->restrictOnDelete();
            $table->timestamps();

            $table->index(['produk_id', 'tanggal_masuk']);
            $table->index('tanggal_masuk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_masuk');
    }
};
