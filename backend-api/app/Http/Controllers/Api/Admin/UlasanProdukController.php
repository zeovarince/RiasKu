<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UlasanProduk;
use App\Services\BestSellerService;
use App\Support\SqlSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UlasanProdukController extends Controller
{
    public function __construct(protected BestSellerService $bestSellers) {}

    public function index(Request $request): JsonResponse
    {
        $query = UlasanProduk::query()
            ->with(['produk:id,name,sku', 'pelanggan:id,username,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($status = trim((string) $request->query('status'))) {
            if (! in_array($status, ['all', 'Semua'], true)) {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) $request->query('q'))) {
            $operator = SqlSearch::operator($query);
            $pattern = SqlSearch::pattern($search);
            $query->where(function ($sub) use ($operator, $pattern): void {
                $sub->where('user_name', $operator, $pattern)
                    ->orWhere('comment', $operator, $pattern)
                    ->orWhereHas('produk', fn ($product) => $product->where('name', $operator, $pattern));
            });
        }

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

    public function update(Request $request, UlasanProduk $review): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,hidden'],
        ]);

        $review->update([
            'status' => $validated['status'],
        ]);

        $this->bestSellers->refreshForProduct((int) $review->produk_id);

        return response()->json([
            'message' => 'Status ulasan berhasil diperbarui.',
            'data' => [
                'id' => $review->id,
                'status' => $review->status,
            ],
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
            'product_name' => $review->produk?->name,
            'product_sku' => $review->produk?->sku,
            'customer' => $review->user_name,
            'customer_email' => $review->pelanggan?->email,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'status' => $review->status,
            'created_at' => $review->created_at,
        ];
    }
}
