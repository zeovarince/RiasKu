<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('produk', 'barcode')) {
            Schema::table('produk', function (Blueprint $table): void {
                $table->string('barcode', 64)->nullable()->unique()->after('sku');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('produk', 'barcode')) {
            Schema::table('produk', function (Blueprint $table): void {
                $table->dropUnique(['barcode']);
                $table->dropColumn('barcode');
            });
        }
    }
};
