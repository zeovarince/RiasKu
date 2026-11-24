<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'best_seller_score')) {
                $table->decimal('best_seller_score', 8, 4)->default(0)->after('min_stock');
            }

            if (! Schema::hasColumn('produk', 'best_seller_units_sold')) {
                $table->unsignedInteger('best_seller_units_sold')->default(0)->after('best_seller_score');
            }

            if (! Schema::hasColumn('produk', 'best_seller_orders_count')) {
                $table->unsignedInteger('best_seller_orders_count')->default(0)->after('best_seller_units_sold');
            }

            if (! Schema::hasColumn('produk', 'best_seller_last_calculated_at')) {
                $table->timestamp('best_seller_last_calculated_at')->nullable()->after('best_seller_orders_count');
            }
        });

        if (! $this->hasIndex('produk', 'produk_best_seller_score_index')) {
            Schema::table('produk', function (Blueprint $table): void {
                $table->index('best_seller_score', 'produk_best_seller_score_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('produk', 'produk_best_seller_score_index')) {
            Schema::table('produk', function (Blueprint $table): void {
                $table->dropIndex('produk_best_seller_score_index');
            });
        }

        Schema::table('produk', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('produk', 'best_seller_score') ? 'best_seller_score' : null,
                Schema::hasColumn('produk', 'best_seller_units_sold') ? 'best_seller_units_sold' : null,
                Schema::hasColumn('produk', 'best_seller_orders_count') ? 'best_seller_orders_count' : null,
                Schema::hasColumn('produk', 'best_seller_last_calculated_at') ? 'best_seller_last_calculated_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
