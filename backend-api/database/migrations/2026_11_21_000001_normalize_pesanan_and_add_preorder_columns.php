<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('pesanan', 'payment_status')) {
                $table->string('payment_status', 40)->default('menunggu_pembayaran')->change();
            }

            if (Schema::hasColumn('pesanan', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->change();
            }

            if (Schema::hasColumn('pesanan', 'recipient_phone')) {
                $table->string('recipient_phone', 30)->nullable()->change();
            }

            if (Schema::hasColumn('pesanan', 'subtotal')) {
                $table->unsignedBigInteger('subtotal')->default(0)->change();
            }

            if (Schema::hasColumn('pesanan', 'total')) {
                $table->unsignedBigInteger('total')->default(0)->change();
            }

            if (! Schema::hasColumn('pesanan', 'has_preorder_items')) {
                $table->boolean('has_preorder_items')->default(false);
            }

            if (! Schema::hasColumn('pesanan', 'preorder_estimate_days')) {
                $table->unsignedTinyInteger('preorder_estimate_days')->nullable();
            }
        });

        Schema::table('pesanan_item', function (Blueprint $table): void {
            if (Schema::hasColumn('pesanan_item', 'line_total')) {
                $table->unsignedBigInteger('line_total')->default(0)->change();
            }

            if (! Schema::hasColumn('pesanan_item', 'is_preorder')) {
                $table->boolean('is_preorder')->default(false);
            }

            if (! Schema::hasColumn('pesanan_item', 'preorder_estimate_days')) {
                $table->unsignedTinyInteger('preorder_estimate_days')->nullable();
            }

            if (! Schema::hasColumn('pesanan_item', 'stock_deducted')) {
                $table->boolean('stock_deducted')->default(true);
            }
        });
    }

    /**
     * Rollback hanya menghapus kolom Pre-Order baru; perubahan panjang payment_status
     * dan nullability kolom lama sengaja dipertahankan demi keamanan data historis.
     */
    public function down(): void
    {
        Schema::table('pesanan_item', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('pesanan_item', 'is_preorder') ? 'is_preorder' : null,
                Schema::hasColumn('pesanan_item', 'preorder_estimate_days') ? 'preorder_estimate_days' : null,
                Schema::hasColumn('pesanan_item', 'stock_deducted') ? 'stock_deducted' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('pesanan', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('pesanan', 'has_preorder_items') ? 'has_preorder_items' : null,
                Schema::hasColumn('pesanan', 'preorder_estimate_days') ? 'preorder_estimate_days' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
