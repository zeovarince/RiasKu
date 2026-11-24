<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Services\BestSellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BestSellerController extends Controller
{
    public function __construct(protected BestSellerService $bestSellers) {}

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', $request->query('per_page', 10))));
        $includeZero = $request->boolean('include_zero');
        $products = $this->bestSellers->topSellers($limit, $includeZero);

        $rows = $products->map(function (Produk $product): array {
            return [
                'rank' => $product->best_seller_rank,
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'category' => $product->kategori?->name,
                'retail_price' => (int) $product->retail_price,
                'best_seller_score' => round((float) $product->best_seller_score, 2),
                'units_sold' => (int) $product->best_seller_units_sold,
                'orders_count' => (int) $product->best_seller_orders_count,
                'rating' => $product->rating,
                'review_count' => $product->review_count,
                'total_stock' => $product->total_stock,
                'min_stock' => (int) $product->min_stock,
                'needs_restock' => $product->needs_restock,
                'restock_status' => $product->restock_status,
                'best_seller_last_calculated_at' => $product->best_seller_last_calculated_at,
            ];
        })->values()->all();

        $scores = array_column($rows, 'best_seller_score');

        return response()->json([
            'message' => 'Daftar produk best seller berhasil dimuat.',
            'data' => $rows,
            'summary' => [
                'products' => count($rows),
                'total_units_sold' => array_sum(array_column($rows, 'units_sold')),
                'average_score' => $scores === [] ? 0 : round(array_sum($scores) / count($scores), 2),
                'needs_restock' => count(array_filter($rows, fn (array $row): bool => $row['needs_restock'])),
            ],
            'meta' => array_merge([
                'limit' => $limit,
                'include_zero' => $includeZero,
            ], $this->bestSellers->metadata()),
        ]);
    }
}
