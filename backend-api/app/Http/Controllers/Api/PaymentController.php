<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected MidtransService $midtransService)
    {
    }

    public function createIntent(Request $request, int|string $orderId): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
        ]);

        $order = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->where(function ($sub) use ($orderId) {
                $sub->where('id', $orderId)
                    ->orWhere('order_code', $orderId);
            })
            ->with('latestPembayaran')
            ->firstOrFail();

        $channel = $validated['channel'] ?? ($validated['method'] ?? $order->payment_channel);

        // Jika sudah ada pembayaran pending yang valid dengan channel yang sama
        if ($order->latestPembayaran && $order->latestPembayaran->transaction_status === 'pending' && $order->latestPembayaran->expiry_time > now()) {
            return response()->json([
                'message' => 'Instruksi pembayaran aktif.',
                'data' => $order->latestPembayaran,
            ]);
        }

        $chargeResult = $this->midtransService->charge($order, $channel);

        $payment = Pembayaran::create([
            'pesanan_id' => $order->id,
            'transaction_id' => $chargeResult['transaction_id'],
            'payment_type' => $chargeResult['payment_type'],
            'bank' => $chargeResult['bank'] ?? null,
            'va_number' => $chargeResult['va_number'] ?? null,
            'bill_key' => $chargeResult['bill_key'] ?? null,
            'biller_code' => $chargeResult['biller_code'] ?? null,
            'qr_string' => $chargeResult['qr_string'] ?? null,
            'qr_url' => $chargeResult['qr_url'] ?? null,
            'deeplink_url' => $chargeResult['deeplink_url'] ?? null,
            'gross_amount' => $chargeResult['gross_amount'],
            'transaction_status' => $chargeResult['transaction_status'],
            'payload_response' => $chargeResult['payload_response'],
            'expiry_time' => $chargeResult['expiry_time'] ?? null,
        ]);

        return response()->json([
            'message' => 'Instruksi pembayaran berhasil dibuat.',
            'data' => array_merge($payment->toArray(), [
                'instructions' => $chargeResult['instructions'] ?? [],
            ]),
        ]);
    }

    public function status(Request $request, int|string $paymentId): JsonResponse
    {
        $payment = Pembayaran::query()
            ->where('id', $paymentId)
            ->orWhere('transaction_id', $paymentId)
            ->with('pesanan')
            ->firstOrFail();

        return response()->json([
            'message' => 'Status pembayaran berhasil dimuat.',
            'data' => [
                'payment' => $payment,
                'order' => $payment->pesanan,
            ],
        ]);
    }

    /**
     * Simulator Pembayaran Sukses (Khusus pengujian lokal / demo).
     */
    public function simulateSuccess(Request $request, int|string $paymentId): JsonResponse
    {
        $payment = Pembayaran::query()
            ->where('id', $paymentId)
            ->orWhere('transaction_id', $paymentId)
            ->with('pesanan')
            ->firstOrFail();

        $payment->update([
            'transaction_status' => 'settlement',
            'paid_at' => now(),
        ]);

        $order = $payment->pesanan;
        $order->update([
            'payment_status' => 'dibayar',
            'order_status' => 'diproses',
        ]);

        return response()->json([
            'message' => 'Simulasi pembayaran berhasil! Pesanan Anda telah lunas dan sedang diproses.',
            'data' => [
                'payment' => $payment->fresh(),
                'order' => $order->fresh(),
            ],
        ]);
    }

    /**
     * Webhook notifikasi resmi Midtrans.
     */
    public function midtransNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderCode = $payload['order_id'] ?? null;
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $trxStatus = (string) ($payload['transaction_status'] ?? '');

        if (!$orderCode) {
            return response()->json(['message' => 'Invalid order code.'], 400);
        }

        // Verifikasi signature jika Server Key disetel
        if (env('MIDTRANS_SERVER_KEY') && !$this->midtransService->verifySignature($orderCode, $statusCode, $grossAmount, $signatureKey)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $order = Pesanan::where('order_code', $orderCode)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $payment = Pembayaran::where('pesanan_id', $order->id)->latest()->first();

        if ($trxStatus === 'capture' || $trxStatus === 'settlement') {
            $payment?->update([
                'transaction_status' => 'settlement',
                'paid_at' => now(),
                'payload_response' => $payload,
            ]);
            $order->update([
                'payment_status' => 'dibayar',
                'order_status' => 'diproses',
            ]);
        } elseif ($trxStatus === 'expire' || $trxStatus === 'cancel' || $trxStatus === 'deny') {
            $payment?->update([
                'transaction_status' => $trxStatus,
                'payload_response' => $payload,
            ]);
            $order->update([
                'payment_status' => 'kadaluarsa',
                'order_status' => 'dibatalkan',
            ]);

            // Kembalikan stok item pesanan
            foreach ($order->items as $item) {
                if ($item->produk_varian_id) {
                    $item->varian?->increment('stock', $item->quantity);
                }
            }
        }

        return response()->json(['message' => 'Notification processed successfully.']);
    }
}
