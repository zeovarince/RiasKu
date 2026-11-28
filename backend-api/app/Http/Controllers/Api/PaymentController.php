<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keranjang;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Services\MidtransService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService,
        protected OrderStatusService $orderStatusService,
    ) {}

    /**
     * POST /api/orders/{id}/payments
     * Membuat instruksi pembayaran baru (atau mengembalikan yang masih aktif).
     */
    public function createIntent(Request $request, int|string $orderId): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
        ]);

        $order = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->where(function ($sub) use ($orderId) {
                if (is_numeric($orderId)) {
                    $sub->where('id', (int) $orderId)->orWhere('order_code', (string) $orderId);
                } else {
                    $sub->where('order_code', (string) $orderId);
                }
            })
            ->with('latestPembayaran')
            ->firstOrFail();

        if ($order->isCodOrder()) {
            return response()->json([
                'message' => 'Pesanan COD tidak memerlukan pembuatan instruksi pembayaran online.',
            ], 422);
        }

        if ($order->resolvedPaymentStatus() === 'dibayar' || in_array($order->resolvedOrderStatus(), ['selesai', 'dibatalkan'], true)) {
            return response()->json([
                'message' => 'Pesanan ini sudah lunas atau tidak lagi aktif untuk pembayaran baru.',
            ], 422);
        }

        $channel = strtolower(trim((string) ($validated['channel'] ?? ($validated['method'] ?? $order->payment_channel ?? 'qris'))));

        // Jika sudah ada pembayaran pending yang valid dengan channel yang sama
        if (
            $order->latestPembayaran
            && $order->latestPembayaran->transaction_status === 'pending'
            && $order->latestPembayaran->expiry_time > now()
            && $this->midtransService->matchesChannel($order->latestPembayaran, $channel)
        ) {
            return response()->json([
                'message' => 'Instruksi pembayaran aktif.',
                'data' => array_merge($order->latestPembayaran->toArray(), [
                    'instructions' => $this->midtransService->instructionsForPayment($order->latestPembayaran, $order, $channel),
                    'midtrans_mode' => $order->latestPembayaran->payload_response['mode'] ?? $this->midtransService->mode(),
                    'can_simulate' => $this->midtransService->canSimulate(),
                ]),
            ]);
        }

        try {
            $chargeResult = $this->midtransService->charge($order, $channel);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        if ($order->payment_channel !== $channel) {
            $order->update(['payment_channel' => $channel]);
        }

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
                'midtrans_mode' => $chargeResult['midtrans_mode'] ?? 'unknown',
                'can_simulate' => $chargeResult['can_simulate'] ?? $this->midtransService->canSimulate(),
            ]),
        ]);
    }

    /**
     * GET /api/payments/{id}
     * Ambil status pembayaran.
     */
    public function status(Request $request, int|string $paymentId): JsonResponse
    {
        $payment = Pembayaran::query()
            ->where(function ($sub) use ($paymentId): void {
                if (is_numeric($paymentId)) {
                    $sub->where('id', (int) $paymentId)->orWhere('transaction_id', (string) $paymentId);
                } else {
                    $sub->where('transaction_id', (string) $paymentId);
                }
            })
            ->with('pesanan')
            ->firstOrFail();

        if (! $payment->pesanan || $payment->pesanan->pelanggan_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke transaksi pembayaran ini.',
            ], 403);
        }

        return response()->json([
            'message' => 'Status pembayaran berhasil dimuat.',
            'data' => [
                'payment' => $payment,
                'order' => $payment->pesanan,
            ],
        ]);
    }

    /**
     * POST /api/payments/{id}/simulate-pay
     * Simulator Pembayaran Sukses (Khusus pengujian lokal / demo).
     */
    public function simulateSuccess(Request $request, int|string $paymentId): JsonResponse
    {
        $isProdMidtrans = (bool) config('services.midtrans.is_production', false);

        abort_if(
            ! $this->midtransService->canSimulate() || $isProdMidtrans || app()->isProduction(),
            403,
            'Simulasi pembayaran hanya tersedia di mode sandbox lokal.'
        );

        $payment = Pembayaran::query()
            ->where(function ($sub) use ($paymentId): void {
                if (is_numeric($paymentId)) {
                    $sub->where('id', (int) $paymentId)->orWhere('transaction_id', (string) $paymentId);
                } else {
                    $sub->where('transaction_id', (string) $paymentId);
                }
            })
            ->with('pesanan')
            ->firstOrFail();

        $order = $payment->pesanan;
        if (! $order || $order->pelanggan_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke transaksi pembayaran ini.',
            ], 403);
        }

        if ($order->payment_method === 'cod' || $payment->payment_type === 'cod_cash') {
            return response()->json([
                'message' => 'Pesanan COD tidak dapat disimulasikan oleh pelanggan. Konfirmasi pembayaran tunai dilakukan oleh Admin.',
            ], 422);
        }

        if ($order->resolvedOrderStatus() !== 'menunggu_pembayaran' || $payment->transaction_status !== 'pending') {
            return response()->json([
                'message' => 'Pembayaran ini tidak lagi berstatus menunggu pembayaran.',
            ], 422);
        }

        $order = $this->applyMidtransSettlement(
            $order,
            $payment,
            'settlement',
            'Payment Simulator',
            'Simulasi pembayaran berhasil diverifikasi.',
        );

        return response()->json([
            'message' => 'Simulasi pembayaran berhasil! Pesanan Anda telah lunas dan sedang diproses.',
            'data' => [
                'payment' => $payment->fresh(),
                'order' => $order,
            ],
        ]);
    }

    /**
     * POST /api/payments/{orderNumber}/sync-status
     * Live Sync: memanggil GET Midtrans /v2/{order_id}/status,
     * lalu update status di PostgreSQL secara real-time.
     * Berguna untuk pengujian localhost tanpa ngrok.
     */
    public function syncStatus(Request $request, string $orderNumber): JsonResponse
    {
        // Cari pesanan berdasarkan order_code atau id
        $order = Pesanan::query()
            ->where(function ($sub) use ($orderNumber): void {
                $sub->where('order_code', (string) $orderNumber);
                if (is_numeric($orderNumber)) {
                    $sub->orWhere('id', (int) $orderNumber);
                }
            })
            ->with('latestPembayaran')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($order->pelanggan_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pesanan ini.',
            ], 403);
        }

        // COD tidak perlu sync ke Midtrans
        if ($order->payment_method === 'cod') {
            return response()->json([
                'message' => 'Pesanan COD tidak memerlukan sinkronisasi Midtrans.',
                'data' => [
                    'payment' => $order->latestPembayaran,
                    'order' => $order,
                    'synced' => false,
                ],
            ]);
        }

        // Jika sudah dibayar, langsung kembalikan status
        if ($order->resolvedPaymentStatus() === 'dibayar') {
            return response()->json([
                'message' => 'Pembayaran sudah lunas.',
                'data' => [
                    'payment' => $order->latestPembayaran,
                    'order' => $order,
                    'synced' => false,
                ],
            ]);
        }

        // Panggil Midtrans GET status
        $midtransResult = $this->midtransService->syncTransactionStatus($order->order_code);

        if (! $midtransResult) {
            // Mode simulasi lokal - kembalikan status saat ini tanpa perubahan
            return response()->json([
                'message' => 'Mode simulasi lokal: sinkronisasi Midtrans tidak tersedia. Gunakan tombol "Simulasikan Pembayaran" untuk pengujian.',
                'data' => [
                    'payment' => $order->latestPembayaran,
                    'order' => $order,
                    'synced' => false,
                    'midtrans_mode' => 'local_simulation',
                ],
            ]);
        }

        // Update status berdasarkan respons Midtrans
        $trxStatus = strtolower((string) ($midtransResult['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($midtransResult['fraud_status'] ?? 'accept'));
        $payment = $order->latestPembayaran;
        $synced = false;

        if ($trxStatus === 'settlement' || ($trxStatus === 'capture' && $fraudStatus === 'accept')) {
            $order = $this->applyMidtransSettlement(
                $order,
                $payment,
                $trxStatus,
                'Midtrans Gateway',
                'Pembayaran terkonfirmasi settlement melalui sinkronisasi Midtrans.',
                $midtransResult,
            );
            $synced = true;

            Log::info("[PaymentController] Sync: Pesanan {$order->order_code} → settlement/dibayar");

        } elseif (in_array($trxStatus, ['expire', 'cancel', 'deny'], true) || ($trxStatus === 'capture' && $fraudStatus === 'deny')) {
            [$order, $cancelled] = $this->applyMidtransCancellation(
                $order,
                $payment,
                $trxStatus === 'capture' ? 'deny' : $trxStatus,
                'Midtrans Gateway',
                "Pembayaran {$trxStatus} melalui sinkronisasi Midtrans.",
                $midtransResult,
            );
            $synced = $cancelled;

            Log::info("[PaymentController] Sync: Pesanan {$order->order_code} → {$trxStatus}");
        }

        return response()->json([
            'message' => $synced
                ? "Status pembayaran berhasil disinkronkan dari Midtrans: {$trxStatus}."
                : "Status Midtrans masih pending ({$trxStatus}).",
            'data' => [
                'payment' => $payment?->fresh(),
                'order' => $order->fresh(),
                'synced' => $synced,
                'midtrans_status' => $trxStatus,
                'midtrans_mode' => $this->midtransService->mode(),
            ],
        ]);
    }

    /**
     * POST /api/payments/midtrans/webhook
     * Webhook notifikasi resmi Midtrans.
     * Verifikasi signature_key wajib jika MIDTRANS_SERVER_KEY terisi.
     */
    public function midtransWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderCode = $payload['order_id'] ?? null;
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $trxStatus = (string) ($payload['transaction_status'] ?? '');

        Log::info('[Midtrans Webhook] Received', [
            'order_id' => $orderCode,
            'transaction_status' => $trxStatus,
            'status_code' => $statusCode,
        ]);

        if (! $orderCode) {
            return response()->json(['message' => 'Invalid order code.'], 400);
        }

        // Fail-closed di produksi bila MIDTRANS_SERVER_KEY belum dikonfigurasi
        if (! $this->midtransService->isLiveMode() && (app()->isProduction() || (bool) config('services.midtrans.is_production', false))) {
            Log::error('[Midtrans Webhook] Server key belum dikonfigurasi di lingkungan produksi.');

            return response()->json(['message' => 'Konfigurasi webhook Midtrans belum siap.'], 503);
        }

        // Verifikasi signature wajib jika Server Key disetel
        if ($this->midtransService->isLiveMode()) {
            if (! $this->midtransService->verifySignature($orderCode, $statusCode, $grossAmount, $signatureKey)) {
                Log::warning('[Midtrans Webhook] Invalid signature', ['order_id' => $orderCode]);

                return response()->json(['message' => 'Invalid signature.'], 403);
            }
        }

        $order = Pesanan::where('order_code', $orderCode)->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->isCodOrder()) {
            return response()->json(['message' => 'Webhook Midtrans tidak berlaku untuk pesanan COD.'], 422);
        }

        $expectedTotal = (int) ($order->total_amount ?? $order->total ?? 0);
        if ($grossAmount !== '' && is_numeric($grossAmount) && (int) round((float) $grossAmount) !== $expectedTotal) {
            Log::warning('[Midtrans Webhook] Gross amount mismatch', [
                'order_id' => $orderCode,
                'expected' => $expectedTotal,
                'received' => $grossAmount,
            ]);

            return response()->json(['message' => 'Nominal transaksi tidak sesuai dengan total pesanan.'], 422);
        }

        $payment = Pembayaran::where('pesanan_id', $order->id)->latest()->first();
        $normalizedTrxStatus = strtolower($trxStatus);
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? 'accept'));

        if ($normalizedTrxStatus === 'settlement' || ($normalizedTrxStatus === 'capture' && $fraudStatus === 'accept')) {
            $this->applyMidtransSettlement(
                $order,
                $payment,
                $normalizedTrxStatus,
                'Midtrans Webhook',
                "Webhook Midtrans: transaksi {$normalizedTrxStatus} lunas.",
                $payload,
            );

            Log::info("[Midtrans Webhook] Pesanan {$orderCode} → LUNAS (settlement)");

        } elseif (in_array($normalizedTrxStatus, ['expire', 'cancel', 'deny'], true) || ($normalizedTrxStatus === 'capture' && $fraudStatus === 'deny')) {
            $effectiveStatus = $normalizedTrxStatus === 'capture' ? 'deny' : $normalizedTrxStatus;
            $this->applyMidtransCancellation(
                $order,
                $payment,
                $effectiveStatus,
                'Midtrans Webhook',
                "Webhook Midtrans: transaksi {$effectiveStatus}, pesanan dibatalkan otomatis.",
                $payload,
            );

            Log::info("[Midtrans Webhook] Pesanan {$orderCode} → {$effectiveStatus}");

        } elseif ($normalizedTrxStatus === 'pending' || ($normalizedTrxStatus === 'capture' && $fraudStatus === 'challenge')) {
            if ($payment && $payment->transaction_status === 'pending') {
                $payment->update([
                    'payload_response' => $payload,
                ]);
            }
        }

        return response()->json(['message' => 'Notification processed successfully.']);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function applyMidtransSettlement(
        Pesanan $order,
        ?Pembayaran $payment,
        string $trxStatus,
        string $actorName,
        string $note,
        ?array $payload = null,
    ): Pesanan {
        try {
            $updatedOrder = $this->orderStatusService->transition($order, 'diproses', [
                'actor_type' => 'midtrans',
                'actor_name' => $actorName,
                'payment_status' => 'dibayar',
                'note' => $note,
                'idempotent' => true,
            ]);

            if ($payment) {
                $paymentUpdates = [
                    'transaction_status' => 'settlement',
                    'paid_at' => $payment->paid_at ?? now(),
                ];
                if ($payload !== null) {
                    $paymentUpdates['payload_response'] = $payload;
                }
                $payment->update($paymentUpdates);
            }

            // Kosongkan keranjang pelanggan setelah pembayaran online benar-benar lunas
            if ($order->pelanggan_id) {
                $cart = Keranjang::where('pelanggan_id', $order->pelanggan_id)->first();
                if ($cart) {
                    $cart->items()->delete();
                }
            }

            return $updatedOrder;
        } catch (ValidationException) {
            return DB::transaction(function () use ($order, $payment, $trxStatus, $actorName, $payload): Pesanan {
                $locked = Pesanan::query()->whereKey($order->id)->lockForUpdate()->first();
                if (! $locked) {
                    return $order;
                }

                $currentStatus = $locked->resolvedOrderStatus();
                Log::warning('[Midtrans] Transisi settlement diabaikan', [
                    'order' => $locked->order_code,
                    'trx' => $trxStatus,
                    'current' => $currentStatus,
                ]);

                if ($payment) {
                    $paymentUpdates = [
                        'transaction_status' => 'settlement',
                        'paid_at' => $payment->paid_at ?? now(),
                    ];
                    if ($payload !== null) {
                        $paymentUpdates['payload_response'] = $payload;
                    }
                    $payment->update($paymentUpdates);
                }

                if ($currentStatus === 'dibatalkan' && $locked->resolvedPaymentStatus() !== 'dibayar') {
                    $locked->update(['payment_status' => 'dibayar']);
                    $locked->statusLogs()->create([
                        'from_status' => 'dibatalkan',
                        'to_status' => 'dibatalkan',
                        'actor_type' => 'midtrans',
                        'actor_name' => $actorName,
                        'tracking_number' => $locked->tracking_number,
                        'note' => 'Pembayaran settlement diterima setelah pesanan dibatalkan — perlu refund manual.',
                        'created_at' => now(),
                    ]);
                } elseif ($currentStatus !== 'dibatalkan' && $locked->resolvedPaymentStatus() !== 'dibayar') {
                    $locked->update(['payment_status' => 'dibayar']);
                }

                return $locked->fresh();
            });
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{0: Pesanan, 1: bool}
     */
    private function applyMidtransCancellation(
        Pesanan $order,
        ?Pembayaran $payment,
        string $trxStatus,
        string $actorName,
        string $note,
        ?array $payload = null,
    ): array {
        try {
            $updatedOrder = $this->orderStatusService->transition($order, 'dibatalkan', [
                'actor_type' => 'midtrans',
                'actor_name' => $actorName,
                'payment_status' => 'kadaluarsa',
                'note' => $note,
                'idempotent' => true,
            ]);

            if ($payment && $payment->transaction_status !== 'settlement') {
                $paymentUpdates = [
                    'transaction_status' => $trxStatus,
                ];
                if ($payload !== null) {
                    $paymentUpdates['payload_response'] = $payload;
                }
                $payment->update($paymentUpdates);
            }

            return [$updatedOrder, true];
        } catch (ValidationException) {
            $fresh = $order->fresh();
            if (! $fresh) {
                return [$order, false];
            }

            Log::warning('[Midtrans] Transisi pembatalan diabaikan karena pesanan sudah diproses/dibayar/selesai', [
                'order' => $fresh->order_code,
                'trx' => $trxStatus,
                'current' => $fresh->resolvedOrderStatus(),
            ]);

            return [$fresh, false];
        }
    }
}
