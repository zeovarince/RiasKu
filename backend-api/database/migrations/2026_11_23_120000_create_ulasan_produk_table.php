<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ulasan_produk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('user_name', 100);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('status', 20)->default('approved');
            $table->timestamps();

            $table->unique(['produk_id', 'pelanggan_id']);
            $table->index(['produk_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ulasan_produk');
    }
};
