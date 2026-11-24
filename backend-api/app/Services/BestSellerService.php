<?php

namespace App\Services;

use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BestSellerService
{
    /**
     * Penjualan yang lebih tua dari jendela ini tidak lagi memengaruhi skor.
     * Tujuannya agar produk lama tidak terus mendominasi etalase.
     */
    public const WINDOW_DAYS = 90;

    /**
     * Bobot metrik pembentuk skor (total 1.0 / 100 poin).
     *
     * @var array<string, float>
     */
    public const WEIGHTS = [
        'orders_count' => 0.60,
        'units_sold' => 0.25,
        'rating' => 0.15,
    ];

    /**
     * Hitung ulang skor best seller untuk seluruh produk atau produk tertentu.
     *
     * Normalisasi selalu memakai nilai maksimum seluruh katalog agar skor
     * antarproduk tetap sebanding meskipun hanya sebagian produk yang dihitung ulang.
     *
     * @param  array<int, int>|null  $productIds
     * @return array{updated:int, calculated_at:string}
     */
    public function refresh(?array $productIds = null): array
    {
        $calculatedAt = now();

        $sales = $this->salesByProduct();
        $reviews = $this->reviewsByProduct();

        $maxUnits = max(0, (int) $sales->max('units_sold'));
        $maxOrders = max(0, (int) $sales->max('orders_count'));
        $salesByProduct = $sales->keyBy('produk_id');

        $ids = $productIds === null
            ? DB::table('produk')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : array_values(array_unique(array_map('intval', $productIds)));

        if ($ids === []) {
            return [
                'updated' => 0,
                'calculated_at' => $calculatedAt->toIso8601String(),
            ];
        }

        $updated = 0;

        foreach ($ids as $productId) {
            $sale = $salesByProduct->get($productId);
            $review = $reviews->get($productId);

            $unitsSold = (int) ($sale->units_sold ?? 0);
            $ordersCount = (int) ($sale->orders_count ?? 0);
            $averageRating = (float) ($review->average_rating ?? 0);

            $score = $unitsSold > 0
                ? $this->calculateScore($unitsSold, $ordersCount, $averageRating, $maxUnits, $maxOrders)
                : 0.0;

            DB::table('produk')->where('id', $productId)->update([
                'best_seller_score' => $score,
                'best_seller_units_sold' => $unitsSold,
                'best_seller_orders_count' => $ordersCount,
                'best_seller_last_calculated_at' => $calculatedAt,
            ]);

            $updated++;
        }

        return [
            'updated' => $updated,
            'calculated_at' => $calculatedAt->toIso8601String(),
        ];
    }

    /**
     * Hitung ulang seluruh katalog setelah status pesanan berubah.
     *
     * Skor memakai nilai maksimum unit dan pesanan seluruh katalog sebagai
     * normalisasi. Perubahan pada satu pesanan dapat menggeser maksimum itu,
     * sehingga skor produk lain juga harus dihitung ulang.
     */
    public function refreshForOrder(Pesanan $order): void
    {
        $this->refresh();
    }

    public function refreshForProduct(int $productId): void
    {
        $this->refresh([$productId]);
    }

    /**
     * Ambil produk best seller aktif lengkap dengan peringkat dan sinyal restock PPL-23.
     *
     * @return Collection<int, Produk>
     */
    public function topSellers(int $limit = 8, bool $includeZeroScore = false): Collection
    {
        $products = Produk::query()
            ->where('is_active', true)
            ->when(! $includeZeroScore, fn ($query) => $query->where('best_seller_score', '>', 0))
            ->with(['kategori', 'activeVarians', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->withCount(['ulasans as review_count' => fn ($review) => $review->where('status', 'approved')])
            ->withAvg(['ulasans as reviews_avg_rating' => fn ($review) => $review->where('status', 'approved')], 'rating')
            ->orderByDesc('best_seller_orders_count')
            ->orderByDesc('best_seller_score')
            ->orderByDesc('best_seller_units_sold')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $products->each(function (Produk $product, int $index): void {
            $product->setAttribute('best_seller_rank', $index + 1);
        });

        return $products;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $calculatedAt = Produk::query()->max('best_seller_last_calculated_at');

        return [
            'window_days' => self::WINDOW_DAYS,
            'weights' => self::WEIGHTS,
            'calculated_at' => $calculatedAt,
        ];
    }

    private function calculateScore(
        int $unitsSold,
        int $ordersCount,
        float $averageRating,
        int $maxUnits,
        int $maxOrders,
    ): float {
        $unitsNormalized = $maxUnits > 0 ? min(1, $unitsSold / $maxUnits) : 0.0;
        $ordersNormalized = $maxOrders > 0 ? min(1, $ordersCount / $maxOrders) : 0.0;
        $ratingNormalized = $averageRating > 0 ? min(1, $averageRating / 5) : 0.0;

        $score = ($ordersNormalized * self::WEIGHTS['orders_count'])
            + ($unitsNormalized * self::WEIGHTS['units_sold'])
            + ($ratingNormalized * self::WEIGHTS['rating']);

        return round($score * 100, 4);
    }

    /**
     * Penjualan dihitung dari pesanan yang lunas (dibayar/settlement) atau
     * berstatus selesai, tidak dibatalkan, dan berada dalam jendela 90 hari.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function salesByProduct(): \Illuminate\Support\Collection
    {
        $cutoff = now()->subDays(self::WINDOW_DAYS);

        return DB::table('pesanan_item as item')
            ->join('pesanan as p', 'p.id', '=', 'item.pesanan_id')
            ->whereNotNull('item.produk_id')
            ->whereRaw("LOWER(COALESCE(p.order_status, '')) <> 'dibatalkan'")
            ->whereRaw("LOWER(COALESCE(p.status, '')) <> 'dibatalkan'")
            ->where(function ($query): void {
                $query->whereIn(DB::raw("LOWER(COALESCE(p.payment_status, ''))"), ['dibayar', 'lunas', 'settlement'])
                    ->orWhereRaw("LOWER(COALESCE(NULLIF(p.order_status, ''), NULLIF(p.status, ''), '')) = 'selesai'")
                    ->orWhereExists(function ($payments): void {
                        $payments->selectRaw('1')
                            ->from('pembayaran as pay')
                            ->whereColumn('pay.pesanan_id', 'p.id')
                            ->whereRaw("LOWER(pay.transaction_status) = 'settlement'");
                    });
            })
            ->whereRaw('COALESCE(p.updated_at, p.created_at) >= ?', [$cutoff])
            ->select('item.produk_id')
            ->selectRaw('SUM(item.quantity) as units_sold')
            ->selectRaw('COUNT(DISTINCT p.id) as orders_count')
            ->groupBy('item.produk_id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function reviewsByProduct(): \Illuminate\Support\Collection
    {
        return DB::table('ulasan_produk')
            ->where('status', 'approved')
            ->select('produk_id')
            ->selectRaw('AVG(rating) as average_rating')
            ->groupBy('produk_id')
            ->get()
            ->keyBy('produk_id');
    }
}
