<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\Produk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,30'],
        ]);

        $days = (int) ($validated['days'] ?? 7);
        $nowJakarta = CarbonImmutable::now(self::TIMEZONE);
        $monthStartLocal = $nowJakarta->startOfMonth();
        $nextMonthStartLocal = $monthStartLocal->addMonth();
        $prevMonthStartLocal = $monthStartLocal->subMonth();

        $monthStartUtc = $monthStartLocal->utc()->format('Y-m-d H:i:s');
        $nextMonthStartUtc = $nextMonthStartLocal->utc()->format('Y-m-d H:i:s');
        $prevMonthStartUtc = $prevMonthStartLocal->utc()->format('Y-m-d H:i:s');

        $activeProducts = Produk::query()->where('is_active', true)->count();
        $newProductsThisMonth = Produk::query()->where('created_at', '>=', $monthStartUtc)->count();

        $statusCounts = $this->orderStatusCounts();
        $totalOrdersThisMonth = Pesanan::query()
            ->where('created_at', '>=', $monthStartUtc)
            ->where('created_at', '<', $nextMonthStartUtc)
            ->whereRaw("LOWER(COALESCE(order_status, '')) <> 'dibatalkan'")
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'dibatalkan'")
            ->count();

        $currentMonthOrders = $this->successfulOrdersBetween($monthStartUtc, $nextMonthStartUtc)->get();
        $monthlyRevenue = (int) $currentMonthOrders->sum(fn (object $order): int => (int) $order->gross_revenue);

        $prevMonthOrders = $this->successfulOrdersBetween($prevMonthStartUtc, $monthStartUtc)->get();
        $prevMonthlyRevenue = (int) $prevMonthOrders->sum(fn (object $order): int => (int) $order->gross_revenue);

        $revenueGrowthPct = null;
        if ($prevMonthlyRevenue > 0) {
            $revenueGrowthPct = round((($monthlyRevenue - $prevMonthlyRevenue) / $prevMonthlyRevenue) * 100, 1);
        } elseif ($monthlyRevenue > 0) {
            $revenueGrowthPct = 100.0;
        }

        // Sales chart for last N days in Asia/Jakarta
        $chartStartLocal = $nowJakarta->startOfDay()->subDays($days - 1);
        $chartEndLocal = $nowJakarta->startOfDay()->addDay();
        $chartOrders = $this->successfulOrdersBetween(
            $chartStartLocal->utc()->format('Y-m-d H:i:s'),
            $chartEndLocal->utc()->format('Y-m-d H:i:s'),
        )->get();

        $revenueByDate = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $chartStartLocal->addDays($i);
            $revenueByDate[$d->toDateString()] = 0;
        }

        foreach ($chartOrders as $order) {
            $localDate = CarbonImmutable::parse((string) $order->transaction_at, 'UTC')
                ->setTimezone(self::TIMEZONE)
                ->toDateString();
            if (array_key_exists($localDate, $revenueByDate)) {
                $revenueByDate[$localDate] += (int) $order->gross_revenue;
            }
        }

        $monthLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $chartPoints = [];
        $chartTotalRevenue = 0;
        for ($i = 0; $i < $days; $i++) {
            $d = $chartStartLocal->addDays($i);
            $dateStr = $d->toDateString();
            $rev = $revenueByDate[$dateStr] ?? 0;
            $chartTotalRevenue += $rev;
            $chartPoints[] = [
                'date' => $dateStr,
                'label' => $d->day.' '.($monthLabels[$d->month] ?? $d->format('M')),
                'revenue' => $rev,
            ];
        }

        // Category distribution from successful orders in the last 90 days
        $distStartUtc = $nowJakarta->subDays(90)->utc()->format('Y-m-d H:i:s');
        $eligibleIds = $this->successfulOrdersBetween($distStartUtc, $nextMonthStartUtc)
            ->reorder()
            ->select('p.id');

        $categoryRows = DB::table('pesanan_item AS item')
            ->join('produk AS prod', 'prod.id', '=', 'item.produk_id')
            ->leftJoin('kategori AS kat', 'kat.id', '=', 'prod.kategori_id')
            ->whereIn('item.pesanan_id', $eligibleIds)
            ->selectRaw("COALESCE(kat.name, 'Lainnya') AS category_name")
            ->selectRaw('SUM(item.quantity) AS units')
            ->groupBy('kat.name')
            ->orderByDesc('units')
            ->get();

        $totalUnits = (int) $categoryRows->sum(fn (object $row): int => (int) $row->units);
        $distributionItems = $categoryRows->take(4)->map(function (object $row) use ($totalUnits): array {
            $units = (int) $row->units;

            return [
                'category' => (string) $row->category_name,
                'units' => $units,
                'percentage' => $totalUnits > 0 ? (int) round(($units / $totalUnits) * 100) : 0,
            ];
        })->values()->all();

        // Recent 5 real orders
        $recentOrders = Pesanan::query()
            ->with(['items', 'latestPembayaran'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (Pesanan $order): array {
                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'customer_name' => $order->customer_name ?: $order->recipient_name ?: 'Pelanggan',
                    'items_count' => $order->total_items_count,
                    'total_amount' => (int) ($order->total_amount ?? $order->total ?? 0),
                    'payment_method' => $order->payment_method,
                    'payment_channel' => $order->payment_channel,
                    'order_status' => $order->resolvedOrderStatus(),
                    'payment_status' => $order->resolvedPaymentStatus(),
                    'created_at' => $order->created_at,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'message' => 'Ringkasan dashboard admin berhasil dimuat.',
            'data' => [
                'metrics' => [
                    'active_products' => $activeProducts,
                    'new_products_this_month' => $newProductsThisMonth,
                    'pending_payment_orders' => $statusCounts['menunggu_pembayaran'],
                    'processing_orders' => $statusCounts['diproses'],
                    'total_orders_this_month' => $totalOrdersThisMonth,
                    'monthly_revenue' => $monthlyRevenue,
                    'prev_monthly_revenue' => $prevMonthlyRevenue,
                    'revenue_growth_pct' => $revenueGrowthPct,
                ],
                'sales_chart' => [
                    'days' => $days,
                    'total_revenue' => $chartTotalRevenue,
                    'points' => $chartPoints,
                ],
                'category_distribution' => [
                    'total_units' => $totalUnits,
                    'items' => $distributionItems,
                ],
                'recent_orders' => $recentOrders,
            ],
        ]);
    }

    private function transactionDateExpression(): string
    {
        return "(COALESCE((SELECT MAX(pay.paid_at) FROM pembayaran AS pay WHERE pay.pesanan_id = p.id AND LOWER(pay.transaction_status) = 'settlement'), p.updated_at, p.created_at))";
    }

    private function successfulOrdersBetween(string $startUtc, string $endUtc): Builder
    {
        $transactionDate = $this->transactionDateExpression();
        $grossRevenue = DB::table('pesanan_item AS amount_item')
            ->selectRaw('COALESCE(SUM(COALESCE(amount_item.line_total, amount_item.subtotal, amount_item.unit_price * amount_item.quantity)), 0)')
            ->whereColumn('amount_item.pesanan_id', 'p.id');

        return DB::table('pesanan AS p')
            ->whereRaw("LOWER(COALESCE(p.order_status, '')) <> 'dibatalkan'")
            ->whereRaw("LOWER(COALESCE(p.status, '')) <> 'dibatalkan'")
            ->where(function (Builder $query): void {
                $query->whereIn(DB::raw("LOWER(COALESCE(p.payment_status, ''))"), ['dibayar', 'lunas', 'settlement'])
                    ->orWhereRaw("LOWER(COALESCE(NULLIF(p.order_status, ''), NULLIF(p.status, ''), '')) = 'selesai'")
                    ->orWhereExists(function (Builder $payments): void {
                        $payments->selectRaw('1')
                            ->from('pembayaran AS successful_payment')
                            ->whereColumn('successful_payment.pesanan_id', 'p.id')
                            ->whereRaw("LOWER(successful_payment.transaction_status) = 'settlement'");
                    });
            })
            ->whereRaw($transactionDate.' >= ? AND '.$transactionDate.' < ?', [$startUtc, $endUtc])
            ->select('p.id')
            ->selectRaw($transactionDate.' AS transaction_at')
            ->selectSub($grossRevenue, 'gross_revenue');
    }

    /**
     * @return array<string, int>
     */
    private function orderStatusCounts(): array
    {
        $counts = [
            'menunggu_pembayaran' => 0,
            'diproses' => 0,
            'dikirim' => 0,
            'selesai' => 0,
            'dibatalkan' => 0,
        ];

        $rows = Pesanan::query()
            ->select(['order_status', 'status', 'payment_method', DB::raw('COUNT(*) as total_rows')])
            ->groupBy(['order_status', 'status', 'payment_method'])
            ->get();

        foreach ($rows as $row) {
            $raw = strtolower(trim((string) ($row->order_status ?: $row->status ?: 'menunggu_pembayaran')));
            if ($raw === 'pending') {
                $raw = strtolower((string) $row->payment_method) === 'cod' ? 'diproses' : 'menunggu_pembayaran';
            }
            if (array_key_exists($raw, $counts)) {
                $counts[$raw] += (int) $row->total_rows;
            }
        }

        return $counts;
    }
}
