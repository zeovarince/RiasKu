<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\UlasanProduk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UlasanProdukController extends Controller
{
    public function index(Request $request, int $product): JsonResponse
    {
        $produk = Produk::query()
            ->whereKey($product)
            ->where('is_active', true)
            ->first();

        if (! $produk) {
            return response()->json([
                'message' => 'Produk tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        $query = UlasanProduk::query()
            ->where('produk_id', $produk->id)
            ->approved()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'message' => 'Daftar ulasan berhasil dimuat.',
                'data' => $paginated->getCollection()->map(fn (UlasanProduk $review): array => $this->transform($review))->values()->all(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $reviews = $query
            ->limit(50)
            ->get()
            ->map(fn (UlasanProduk $review): array => $this->transform($review))
            ->values()
            ->all();

        return response()->json([
            'message' => 'Daftar ulasan berhasil dimuat.',
            'data' => $reviews,
        ]);
    }

    public function store(Request $request, int $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $produk = Produk::query()
            ->whereKey($product)
            ->where('is_active', true)
            ->first();

        if (! $produk) {
            return response()->json([
                'message' => 'Produk tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        $customer = $request->user();

        $review = UlasanProduk::query()->updateOrCreate(
            [
                'produk_id' => $produk->id,
                'pelanggan_id' => $customer->id,
            ],
            [
                'user_name' => $customer->username,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'status' => 'approved',
            ],
        );

        return response()->json([
            'message' => $review->wasRecentlyCreated
                ? 'Ulasan berhasil ditambahkan.'
                : 'Ulasan berhasil diperbarui.',
            'data' => $this->transform($review),
        ], $review->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $review = UlasanProduk::query()
            ->where('produk_id', $product)
            ->where('pelanggan_id', $request->user()->id)
            ->first();

        if (! $review) {
            return response()->json([
                'message' => 'Ulasan tidak ditemukan.',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(UlasanProduk $review): array
    {
        return [
            'id' => $review->id,
            'product_id' => $review->produk_id,
            'user_name' => $review->user_name,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at,
        ];
    }
}
