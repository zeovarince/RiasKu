<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'min_stock')) {
                $table->unsignedInteger('min_stock')->default(5)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (Schema::hasColumn('produk', 'min_stock')) {
                $table->dropColumn('min_stock');
            }
        });
    }
};
