<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pesanan::query()
            ->with(['items.produk', 'latestPembayaran'])
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('order_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status = trim((string) $request->query('status'))) {
            if ($status !== 'Semua' && $status !== 'all') {
                $query->where('order_status', strtolower($status));
            }
        }

        if ($paymentMethod = trim((string) $request->query('payment_method'))) {
            if ($paymentMethod !== 'all') {
                $query->where('payment_method', strtolower($paymentMethod));
            }
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
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $order = Pesanan::query()
            ->where(function ($sub) use ($id) {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->with(['items.produk.primaryFoto', 'pembayarans', 'pelanggan'])
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
        ]);

        $order = Pesanan::query()
            ->where(function ($sub) use ($id) {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->firstOrFail();

        $order->update([
            'order_status' => $validated['status'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => "Status pesanan {$order->order_code} berhasil diperbarui menjadi {$order->order_status}.",
            'data' => $order->fresh(['items', 'latestPembayaran']),
        ]);
    }

    /**
     * PPL-20: Aksi konfirmasi admin saat uang tunai COD diterima dari kurir / pelanggan.
     * Tombol "Uang Tunai Diterima" di OrderDetailView.vue.
     */
    public function confirmCodPayment(Request $request, int|string $id): JsonResponse
    {
        $order = Pesanan::query()
            ->where(function ($sub) use ($id) {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->firstOrFail();

        if ($order->payment_method !== 'cod' && $order->payment_channel !== 'cash') {
            return response()->json([
                'message' => 'Konfirmasi pembayaran tunai hanya berlaku untuk metode COD.',
            ], 422);
        }

        $order->update([
            'payment_status' => 'dibayar',
            'order_status' => 'selesai',
            'status' => 'selesai',
        ]);

        $payment = $order->latestPembayaran;
        if ($payment) {
            $payment->update([
                'transaction_status' => 'settlement',
                'paid_at' => now(),
            ]);
        } else {
            Pembayaran::create([
                'pesanan_id' => $order->id,
                'transaction_id' => 'COD-PAID-' . $order->order_code,
                'payment_type' => 'cod_cash',
                'gross_amount' => $order->total_amount,
                'transaction_status' => 'settlement',
                'paid_at' => now(),
                'payload_response' => [
                    'confirmed_by' => $request->user()->name ?? 'Admin',
                    'note' => 'Uang tunai COD diterima dan dikonfirmasi oleh Admin',
                ],
            ]);
        }

        return response()->json([
            'message' => "Pembayaran COD untuk pesanan {$order->order_code} berhasil dikonfirmasi lunas.",
            'data' => $order->fresh(['items', 'latestPembayaran']),
        ]);
    }
}
