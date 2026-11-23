<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Produk::query()
            ->where('is_active', true)
            ->with(['kategori', 'activeVarians', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->withCount(['ulasans as review_count' => fn ($review) => $review->where('status', 'approved')])
            ->withAvg(['ulasans as reviews_avg_rating' => fn ($review) => $review->where('status', 'approved')], 'rating');

        if ($search = trim((string) ($request->query('q') ?? $request->query('search')))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->query('category'))) {
            if ($category !== 'all') {
                $query->where(function ($sub) use ($category) {
                    $sub->where('kategori_id', $category)
                        ->orWhereHas('kategori', function ($kQuery) use ($category) {
                            $kQuery->where('slug', $category);
                        });
                });
            }
        }

        $stockFilter = $request->query('stock_label') ?? $request->query('stock_status');
        if ($stockFilter !== null && trim((string) $stockFilter) !== '') {
            $query->filterStockLabel((string) $stockFilter);
        }

        $sort = (string) $request->query('sort', 'recommended');
        switch ($sort) {
            case 'price-asc':
                $query->orderBy('retail_price', 'asc');
                break;
            case 'price-desc':
                $query->orderBy('retail_price', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'rating':
                $query->orderByRaw('reviews_avg_rating IS NULL ASC')
                    ->orderByDesc('reviews_avg_rating');
                break;
            case 'recommended':
            default:
                $query->orderBy('id', 'desc');
                break;
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 12)));

        if ($request->has('page')) {
            $paginated = $query->paginate($perPage);
            collect($paginated->items())->each(fn (Produk $item) => $item->makeHidden(['base_price', 'price_warning']));

            return response()->json([
                'message' => 'Daftar produk berhasil dimuat.',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $limit = $request->has('per_page') ? $perPage : ($request->query('featured') ? 4 : 50);
        $products = $query->limit($limit)->get();
        $products->each(fn (Produk $item) => $item->makeHidden(['base_price', 'price_warning']));

        return response()->json([
            'message' => 'Daftar produk berhasil dimuat.',
            'data' => $products,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Produk::query()
            ->where('is_active', true)
            ->with(['kategori', 'activeVarians', 'fotos', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->withCount(['ulasans as review_count' => fn ($review) => $review->where('status', 'approved')])
            ->withAvg(['ulasans as reviews_avg_rating' => fn ($review) => $review->where('status', 'approved')], 'rating')
            ->find($id);

        if (! $product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        $product->makeHidden(['base_price', 'price_warning']);

        return response()->json([
            'message' => 'Detail produk berhasil dimuat.',
            'data' => $product,
        ]);
    }
}
