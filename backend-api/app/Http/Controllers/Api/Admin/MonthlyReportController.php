<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\SqlSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportController extends Controller
{
    private const TIMEZONE = 'Asia/Jakarta';

    public function summary(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $orders = $this->successfulOrders($period)->get();
        $grossRevenue = (int) $orders->sum(fn (object $order): int => (int) $order->gross_revenue);
        $productsSold = (int) $orders->sum(fn (object $order): int => (int) $order->products_sold);

        $daysInMonth = $period['start_local']->daysInMonth;
        $dailyRevenue = array_fill(1, $daysInMonth, 0);

        foreach ($orders as $order) {
            $day = CarbonImmutable::parse((string) $order->transaction_at, 'UTC')
                ->setTimezone(self::TIMEZONE)
                ->day;
            $dailyRevenue[$day] += (int) $order->gross_revenue;
        }

        $stockExpense = (int) DB::table('stok_masuk')
            ->whereBetween('tanggal_masuk', [$period['start_date'], $period['end_date']])
            ->whereNotNull('unit_cost')
            ->sum(DB::raw('quantity * unit_cost'));

        $soldProducts = $this->soldProducts($period);
        $costByProduct = $this->costBasisByProduct($period, $soldProducts);
        $estimatedCogs = 0;
        foreach ($soldProducts as $soldProduct) {
            $estimatedCogs += (int) $soldProduct->quantity
                * (int) ($costByProduct[(int) $soldProduct->produk_id] ?? $soldProduct->base_price ?? 0);
        }

        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $period['start_local']->setDay($day);
            $days[] = [
                'date' => $date->toDateString(),
                'day' => $day,
                'revenue' => $dailyRevenue[$day],
            ];
        }

        return response()->json([
            'message' => 'Rekap keuangan bulanan berhasil dimuat.',
            'data' => [
                'period' => [
                    'month' => $period['month'],
                    'year' => $period['year'],
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                    'timezone' => self::TIMEZONE,
                ],
                'summary' => [
                    'gross_revenue' => $grossRevenue,
                    'successful_orders' => $orders->count(),
                    'products_sold' => $productsSold,
                    'stock_expense' => $stockExpense,
                    'estimated_cogs' => $estimatedCogs,
                    'gross_profit' => $grossRevenue - $estimatedCogs,
                    'average_order_value' => $orders->isEmpty()
                        ? 0
                        : (int) round($grossRevenue / $orders->count()),
                ],
                'daily_trend' => $days,
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $period = $this->periodFromValidated($validated);
        $rows = $this->transactionRows($period, trim((string) ($validated['search'] ?? '')));

        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json([
            'message' => 'Rincian transaksi bulanan berhasil dimuat.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $period = $this->periodFromValidated($validated);
        $rows = $this->transactionRows($period, trim((string) ($validated['search'] ?? '')));
        $filename = sprintf('riasku-rekap-keuangan-%04d-%02d.csv', $period['year'], $period['month']);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Tanggal', 'Jenis', 'ID Transaksi', 'Keterangan', 'Jumlah Item', 'Pendapatan (Rp)', 'Pengeluaran (Rp)', 'Status'], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['date'],
                    $this->csvSafe($row['type']),
                    $this->csvSafe($row['transaction_id']),
                    $this->csvSafe($row['description']),
                    $row['quantity'],
                    $row['type'] === 'Pendapatan' ? $row['amount'] : '',
                    $row['type'] === 'Pengeluaran' ? ($row['amount'] ?? '') : '',
                    $this->csvSafe($row['status']),
                ], ',', '"', '');
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}
     */
    private function period(Request $request): array
    {
        return $this->periodFromValidated($request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}
     */
    private function periodFromValidated(array $validated): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);
        $nextMonth = $start->addMonth();
        $end = $nextMonth->subDay();

        return [
            'month' => $month,
            'year' => $year,
            'start_local' => $start,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start_utc' => $start->utc()->format('Y-m-d H:i:s'),
            'end_utc' => $nextMonth->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * A settlement timestamp is the transaction date. For legacy/manual payments
     * without a payment row, the order's latest update is used as a fallback.
     */
    private function transactionDateExpression(): string
    {
        return "(COALESCE((SELECT MAX(pay.paid_at) FROM pembayaran AS pay WHERE pay.pesanan_id = p.id AND LOWER(pay.transaction_status) = 'settlement'), p.updated_at, p.created_at))";
    }

    /**
     * Paid orders and completed orders are included. Cancelled orders are always
     * excluded, including a late settlement that still requires a manual refund.
     *
     * @param  array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}  $period
     */
    private function successfulOrders(array $period): Builder
    {
        $transactionDate = $this->transactionDateExpression();
        $grossRevenue = DB::table('pesanan_item AS amount_item')
            ->selectRaw('COALESCE(SUM(COALESCE(amount_item.line_total, amount_item.subtotal, amount_item.unit_price * amount_item.quantity)), 0)')
            ->whereColumn('amount_item.pesanan_id', 'p.id');
        $productsSold = DB::table('pesanan_item AS quantity_item')
            ->selectRaw('COALESCE(SUM(quantity_item.quantity), 0)')
            ->whereColumn('quantity_item.pesanan_id', 'p.id');

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
            ->whereRaw($transactionDate.' >= ? AND '.$transactionDate.' < ?', [$period['start_utc'], $period['end_utc']])
            ->select([
                'p.id',
                'p.order_code',
                'p.customer_name',
                'p.recipient_name',
                'p.order_status',
                'p.status AS legacy_status',
                'p.payment_status',
            ])
            ->selectRaw($transactionDate.' AS transaction_at')
            ->selectSub($grossRevenue, 'gross_revenue')
            ->selectSub($productsSold, 'products_sold')
            ->orderByDesc('transaction_at')
            ->orderByDesc('p.id');
    }

    /**
     * @param  array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}  $period
     * @return Collection<int, object>
     */
    private function soldProducts(array $period): Collection
    {
        $eligibleOrderIds = $this->successfulOrders($period)->reorder()->select('p.id');

        return DB::table('pesanan_item AS item')
            ->join('produk AS product', 'product.id', '=', 'item.produk_id')
            ->whereIn('item.pesanan_id', $eligibleOrderIds)
            ->select('item.produk_id', 'product.base_price')
            ->selectRaw('SUM(item.quantity) AS quantity')
            ->groupBy('item.produk_id', 'product.base_price')
            ->get();
    }

    /**
     * Known restock costs are averaged by received quantity through the report
     * month; products without cost history fall back to their current base_price.
     *
     * @param  array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}  $period
     * @return array<int, int>
     */
    private function costBasisByProduct(array $period, Collection $soldProducts): array
    {
        $productIds = $soldProducts->pluck('produk_id')->all();
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('stok_masuk')
            ->whereIn('produk_id', $productIds)
            ->whereDate('tanggal_masuk', '<=', $period['end_date'])
            ->whereNotNull('unit_cost')
            ->select('produk_id')
            ->selectRaw('SUM(unit_cost * quantity) AS cost_total, SUM(quantity) AS quantity_total')
            ->groupBy('produk_id')
            ->get();

        $costs = [];
        foreach ($rows as $row) {
            if ((int) $row->quantity_total > 0) {
                $costs[(int) $row->produk_id] = (int) round((int) $row->cost_total / (int) $row->quantity_total);
            }
        }

        return $costs;
    }

    /**
     * @param  array{month:int,year:int,start_local:CarbonImmutable,start_date:string,end_date:string,start_utc:string,end_utc:string}  $period
     * @return list<array{date:string,type:string,transaction_id:string,description:string,quantity:int,amount:?int,status:string,_sort_at:string}>
     */
    private function transactionRows(array $period, string $search): array
    {
        $ordersQuery = $this->successfulOrders($period);
        $likeOperator = SqlSearch::operator($ordersQuery);
        if ($search !== '') {
            $like = SqlSearch::pattern($search);
            $ordersQuery->where(function (Builder $query) use ($likeOperator, $like): void {
                $query->where('p.order_code', $likeOperator, $like)
                    ->orWhere('p.customer_name', $likeOperator, $like)
                    ->orWhere('p.recipient_name', $likeOperator, $like)
                    ->orWhereExists(function (Builder $items) use ($likeOperator, $like): void {
                        $items->selectRaw('1')
                            ->from('pesanan_item AS search_item')
                            ->whereColumn('search_item.pesanan_id', 'p.id')
                            ->where(function (Builder $names) use ($likeOperator, $like): void {
                                $names->where('search_item.product_name', $likeOperator, $like)
                                    ->orWhere('search_item.variant_name', $likeOperator, $like);
                            });
                    });
            });
        }
        $orders = $ordersQuery->get();
        $orderIds = $orders->pluck('id')->all();
        $itemsByOrder = [];

        if ($orderIds !== []) {
            $items = DB::table('pesanan_item')
                ->whereIn('pesanan_id', $orderIds)
                ->get(['pesanan_id', 'product_name', 'variant_name', 'quantity']);

            foreach ($items as $item) {
                $label = trim((string) $item->product_name);
                if (trim((string) $item->variant_name) !== '') {
                    $label .= ' ('.trim((string) $item->variant_name).')';
                }
                $itemsByOrder[(int) $item->pesanan_id][] = $label.' × '.(int) $item->quantity;
            }
        }

        $rows = [];
        foreach ($orders as $order) {
            $date = CarbonImmutable::parse((string) $order->transaction_at, 'UTC')
                ->setTimezone(self::TIMEZONE);
            $status = strtolower(trim((string) ($order->order_status ?: $order->legacy_status)));
            $description = implode(', ', array_slice($itemsByOrder[(int) $order->id] ?? [], 0, 3));
            $itemCount = count($itemsByOrder[(int) $order->id] ?? []);
            if ($itemCount > 3) {
                $description .= ' + '.($itemCount - 3).' item lainnya';
            }

            $rows[] = [
                'date' => $date->toIso8601String(),
                'type' => 'Pendapatan',
                'transaction_id' => (string) $order->order_code,
                'description' => $description !== '' ? $description : 'Pesanan pelanggan',
                'quantity' => (int) $order->products_sold,
                'amount' => (int) $order->gross_revenue,
                'status' => $status === 'selesai' ? 'Selesai' : 'Lunas',
                '_sort_at' => $date->utc()->format('Y-m-d H:i:s'),
            ];
        }

        $stockQuery = DB::table('stok_masuk AS stock')
            ->join('produk AS product', 'product.id', '=', 'stock.produk_id')
            ->leftJoin('produk_varian AS variant', 'variant.id', '=', 'stock.produk_varian_id')
            ->whereBetween('stock.tanggal_masuk', [$period['start_date'], $period['end_date']])
            ->select([
                'stock.id',
                'stock.tanggal_masuk',
                'stock.quantity',
                'stock.unit_cost',
                'stock.supplier',
                'stock.notes',
                'product.name AS product_name',
                'variant.name AS variant_name',
            ])
            ->orderByDesc('stock.tanggal_masuk')
            ->orderByDesc('stock.id');

        if ($search !== '') {
            $like = SqlSearch::pattern($search);
            $stockQuery->where(function (Builder $query) use ($likeOperator, $like): void {
                $query->where('product.name', $likeOperator, $like)
                    ->orWhere('variant.name', $likeOperator, $like)
                    ->orWhere('stock.supplier', $likeOperator, $like)
                    ->orWhere('stock.notes', $likeOperator, $like);
            });
        }

        foreach ($stockQuery->get() as $stock) {
            $date = CarbonImmutable::parse((string) $stock->tanggal_masuk, self::TIMEZONE)->startOfDay();
            $description = (string) $stock->product_name;
            if (trim((string) $stock->variant_name) !== '') {
                $description .= ' ('.trim((string) $stock->variant_name).')';
            }
            if (trim((string) $stock->supplier) !== '') {
                $description .= ' · '.trim((string) $stock->supplier);
            }

            $rows[] = [
                'date' => $date->toIso8601String(),
                'type' => 'Pengeluaran',
                'transaction_id' => 'STK-'.$stock->id,
                'description' => $description,
                'quantity' => (int) $stock->quantity,
                'amount' => $stock->unit_cost === null
                    ? null
                    : (int) $stock->unit_cost * (int) $stock->quantity,
                'status' => $stock->unit_cost === null ? 'Modal belum diisi' : 'Tercatat',
                '_sort_at' => $date->utc()->format('Y-m-d H:i:s'),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $dateOrder = strcmp($right['_sort_at'], $left['_sort_at']);

            return $dateOrder !== 0
                ? $dateOrder
                : strcmp($right['transaction_id'], $left['transaction_id']);
        });

        return array_map(static function (array $row): array {
            unset($row['_sort_at']);

            return $row;
        }, $rows);
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
