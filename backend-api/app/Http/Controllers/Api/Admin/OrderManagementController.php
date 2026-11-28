<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Services\OrderStatusService;
use App\Support\SqlSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderManagementController extends Controller
{
    public function __construct(protected OrderStatusService $orderStatusService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Pesanan::query()
            ->with(OrderStatusService::orderRelations(false))
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search'))) {
            $operator = SqlSearch::operator($query);
            $pattern = SqlSearch::pattern($search);
            $query->where(function (Builder $sub) use ($operator, $pattern): void {
                $sub->where('order_code', $operator, $pattern)
                    ->orWhere('customer_name', $operator, $pattern)
                    ->orWhere('recipient_name', $operator, $pattern)
                    ->orWhere('customer_phone', $operator, $pattern)
                    ->orWhere('recipient_phone', $operator, $pattern)
                    ->orWhere('tracking_number', $operator, $pattern);
            });
        }

        if ($status = trim((string) $request->query('status'))) {
            if ($status !== 'Semua' && $status !== 'all') {
                $normalizedStatus = strtolower($status);
                $query->where(function (Builder $sub) use ($normalizedStatus): void {
                    $sub->where('order_status', $normalizedStatus)
                        ->orWhere(function (Builder $legacy) use ($normalizedStatus): void {
                            $legacy->whereNull('order_status');
                            if ($normalizedStatus === 'menunggu_pembayaran') {
                                $legacy->where('status', 'pending')
                                    ->where(function (Builder $pm): void {
                                        $pm->whereNull('payment_method')->orWhere('payment_method', '!=', 'cod');
                                    });
                            } elseif ($normalizedStatus === 'diproses') {
                                $legacy->where(function (Builder $dip): void {
                                    $dip->where('status', 'diproses')
                                        ->orWhere(function (Builder $codPending): void {
                                            $codPending->where('status', 'pending')->where('payment_method', 'cod');
                                        });
                                });
                            } else {
                                $legacy->where('status', $normalizedStatus);
                            }
                        });
                });
            }
        }

        if ($paymentMethod = trim((string) $request->query('payment_method'))) {
            if ($paymentMethod !== 'all') {
                $query->where('payment_method', strtolower($paymentMethod));
            }
        }

        if ($request->filled('is_preorder') && $request->query('is_preorder') !== 'all') {
            $isPreorder = filter_var($request->query('is_preorder'), FILTER_VALIDATE_BOOLEAN);
            $query->where('has_preorder_items', $isPreorder);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Daftar pesanan berhasil dimuat.',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => $this->buildStatusSummary(),
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $order = $this->findOrderByIdOrCode($id)
            ->with(OrderStatusService::orderRelations(true))
            ->firstOrFail();

        return response()->json([
            'message' => 'Detail pesanan berhasil dimuat.',
            'data' => $order,
        ]);
    }

    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:menunggu_pembayaran,diproses,dikirim,selesai,dibatalkan'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'shipping_courier' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Status pesanan wajib dipilih.',
            'status.in' => 'Status pesanan tidak valid.',
            'tracking_number.max' => 'Nomor resi maksimal 100 karakter.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $order = $this->findOrderByIdOrCode($id)->firstOrFail();
        $admin = $request->user();

        $updated = $this->orderStatusService->transition($order, $validated['status'], [
            'actor_type' => 'admin',
            'actor_id' => $admin?->id,
            'actor_name' => $admin?->name ?? 'Admin',
            'tracking_number' => $validated['tracking_number'] ?? null,
            'shipping_courier' => $validated['shipping_courier'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'message' => "Status pesanan {$updated->order_code} berhasil diperbarui menjadi {$updated->order_status}.",
            'data' => $updated,
        ]);
    }

    /**
     * PPL-20: Aksi konfirmasi admin saat uang tunai COD diterima dari kurir / pelanggan.
     * Tombol "Uang Tunai Diterima" di OrderDetailView.vue.
     */
    public function confirmCodPayment(Request $request, int|string $id): JsonResponse
    {
        $order = $this->findOrderByIdOrCode($id)->firstOrFail();
        $admin = $request->user();

        $updated = $this->orderStatusService->markCodPaid($order, [
            'actor_type' => 'admin',
            'actor_id' => $admin?->id,
            'actor_name' => $admin?->name ?? 'Admin',
        ]);

        return response()->json([
            'message' => "Pembayaran COD untuk pesanan {$updated->order_code} berhasil dikonfirmasi lunas.",
            'data' => $updated,
        ]);
    }

    private function findOrderByIdOrCode(int|string $id): Builder
    {
        return Pesanan::query()->where(function (Builder $sub) use ($id): void {
            if (is_numeric($id)) {
                $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
            } else {
                $sub->where('order_code', (string) $id);
            }
        });
    }

    /**
     * @return array<string, int>
     */
    private function buildStatusSummary(): array
    {
        $summary = [
            'all' => 0,
            'menunggu_pembayaran' => 0,
            'diproses' => 0,
            'dikirim' => 0,
            'selesai' => 0,
            'dibatalkan' => 0,
            'action_required_count' => 0,
        ];

        $rows = Pesanan::query()
            ->select(['order_status', 'status', 'payment_method', DB::raw('COUNT(*) as total_rows')])
            ->groupBy(['order_status', 'status', 'payment_method'])
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->total_rows;
            $raw = strtolower(trim((string) ($row->order_status ?: $row->status ?: 'menunggu_pembayaran')));
            if ($raw === 'pending') {
                $raw = strtolower((string) $row->payment_method) === 'cod' ? 'diproses' : 'menunggu_pembayaran';
            }

            $summary['all'] += $count;
            if (array_key_exists($raw, $summary)) {
                $summary[$raw] += $count;
            }
        }

        $summary['action_required_count'] = $summary['menunggu_pembayaran'] + $summary['diproses'];

        return $summary;
    }
}
