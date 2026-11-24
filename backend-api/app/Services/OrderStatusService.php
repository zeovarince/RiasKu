<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\PesananStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(protected BestSellerService $bestSellers) {}

    /**
     * Eager load relasi lengkap Produk agar accessor/appends pada Produk tidak memicu N+1 query.
     *
     * @return array<int|string, mixed>
     */
    public static function orderRelations(bool $includeDetail = true): array
    {
        $relations = [
            'items.produk' => function ($query): void {
                $query->select([
                    'id',
                    'kategori_id',
                    'sku',
                    'name',
                    'brand',
                    'weight_grams',
                    'retail_price',
                    'pack_quantity',
                    'pack_price',
                    'is_preorder',
                    'preorder_estimate_days',
                    'short_ingredients',
                    'bpom_number',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                    ->with(['kategori:id,name,slug', 'activeVarians', 'primaryFoto'])
                    ->withSum(['activeVarians as active_stock_sum'], 'stock');
            },
            'latestPembayaran',
        ];

        if ($includeDetail) {
            $relations[] = 'pembayarans';
            $relations['pelanggan'] = function ($query): void {
                $query->select(['id', 'username', 'email']);
            };
            $relations[] = 'statusLogs';
        }

        return $relations;
    }

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'menunggu_pembayaran' => ['diproses', 'dibatalkan'],
        'diproses' => ['dikirim', 'dibatalkan'],
        'dikirim' => ['selesai'],
        'selesai' => [],
        'dibatalkan' => [],
    ];

    /**
     * @return list<string>
     */
    public static function allowedTransitions(?string $currentStatus): array
    {
        $normalized = strtolower(trim((string) $currentStatus));
        if ($normalized === '' || $normalized === 'pending') {
            $normalized = 'menunggu_pembayaran';
        }

        return self::TRANSITIONS[$normalized] ?? [];
    }

    public function recordInitialLog(
        Pesanan $order,
        string $actorType = 'pelanggan',
        ?int $actorId = null,
        ?string $actorName = null,
        ?string $note = null,
    ): PesananStatusLog {
        return PesananStatusLog::create([
            'pesanan_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->resolvedOrderStatus(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName ?? ($order->customer_name ?: $order->recipient_name),
            'tracking_number' => $order->tracking_number,
            'note' => $note ?? 'Pesanan berhasil dibuat.',
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(Pesanan $order, string $targetStatus, array $context = []): Pesanan
    {
        $target = strtolower(trim($targetStatus));

        $updated = DB::transaction(function () use ($order, $target, $context): Pesanan {
            /** @var Pesanan $locked */
            $locked = Pesanan::query()
                ->whereKey($order->id)
                ->with('items.varian')
                ->lockForUpdate()
                ->firstOrFail();
            $currentStatus = $locked->resolvedOrderStatus();
            $trackingNumber = isset($context['tracking_number'])
                ? trim((string) $context['tracking_number'])
                : null;
            $shippingCourier = isset($context['shipping_courier'])
                ? trim((string) $context['shipping_courier'])
                : null;
            $note = isset($context['note']) && trim((string) $context['note']) !== ''
                ? trim((string) $context['note'])
                : null;
            $idempotent = (bool) ($context['idempotent'] ?? false);

            // Izinkan pembaruan nomor resi saat pesanan masih berstatus dikirim
            $isUpdatingResiWhileShipped = $currentStatus === 'dikirim'
                && $target === 'dikirim'
                && ($trackingNumber !== null || $shippingCourier !== null || $note !== null);

            if ($idempotent && $currentStatus === $target && ! $isUpdatingResiWhileShipped) {
                if (! empty($context['payment_status']) && $locked->payment_status !== $context['payment_status']) {
                    $locked->update(['payment_status' => $context['payment_status']]);
                }

                return $locked->fresh(self::orderRelations(true));
            }

            if (! $isUpdatingResiWhileShipped) {
                $allowed = self::allowedTransitions($currentStatus);
                if (! in_array($target, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'status' => ["Status pesanan tidak dapat diubah dari {$currentStatus} menjadi {$target}."],
                    ]);
                }
            }

            $isPaid = $locked->resolvedPaymentStatus() === 'dibayar';
            $isAdminActor = ($context['actor_type'] ?? '') === 'admin';
            $isMidtransActor = ($context['actor_type'] ?? '') === 'midtrans';

            if ($isMidtransActor && $target === 'dibatalkan') {
                if ($currentStatus !== 'menunggu_pembayaran' || $isPaid || $locked->isCodOrder()) {
                    throw ValidationException::withMessages([
                        'status' => ["Webhook Midtrans tidak dapat membatalkan pesanan pada status {$currentStatus}."],
                    ]);
                }
            }

            $requiresManualPaymentVerification = $currentStatus === 'menunggu_pembayaran'
                && $target === 'diproses'
                && ! $isPaid
                && $isAdminActor;

            if ($requiresManualPaymentVerification && $note === null) {
                throw ValidationException::withMessages([
                    'note' => ['Pembayaran belum terkonfirmasi. Isi catatan verifikasi pembayaran manual (mis. bukti transfer) untuk memproses pesanan.'],
                ]);
            }

            $effectiveTracking = $trackingNumber !== null && $trackingNumber !== ''
                ? $trackingNumber
                : $locked->tracking_number;

            if ($target === 'dikirim' && ! $locked->isCodOrder() && empty($effectiveTracking)) {
                throw ValidationException::withMessages([
                    'tracking_number' => ['Nomor resi wajib diisi untuk pesanan ekspedisi non-COD sebelum status diubah ke dikirim.'],
                ]);
            }

            $updates = [
                'order_status' => $target,
                'status' => $target,
            ];

            if ($requiresManualPaymentVerification) {
                $updates['payment_status'] = 'dibayar';
            }

            if ($trackingNumber !== null && $trackingNumber !== '') {
                $updates['tracking_number'] = $trackingNumber;
            }

            if ($shippingCourier !== null && $shippingCourier !== '') {
                $updates['shipping_courier'] = $shippingCourier;
                $updates['courier'] = $shippingCourier;
            }

            if (! empty($context['payment_status'])) {
                $updates['payment_status'] = $context['payment_status'];
            }

            $locked->update($updates);

            if ($target === 'dibatalkan' && $currentStatus !== 'dibatalkan') {
                $this->restoreStockIfNeeded($locked);
            }

            PesananStatusLog::create([
                'pesanan_id' => $locked->id,
                'from_status' => $currentStatus,
                'to_status' => $target,
                'actor_type' => (string) ($context['actor_type'] ?? 'system'),
                'actor_id' => $context['actor_id'] ?? null,
                'actor_name' => $context['actor_name'] ?? null,
                'tracking_number' => $locked->tracking_number,
                'note' => $note ?? $this->defaultNoteFor($currentStatus, $target, $locked->tracking_number),
                'created_at' => now(),
            ]);

            return $locked->fresh(self::orderRelations(true));
        });

        $this->bestSellers->refreshForOrder($updated);

        return $updated;
    }

    /**
     * Konfirmasi pembayaran uang tunai COD Surabaya melalui state machine & row lock.
     *
     * @param  array<string, mixed>  $context
     */
    public function markCodPaid(Pesanan $order, array $context = []): Pesanan
    {
        $updated = DB::transaction(function () use ($order, $context): Pesanan {
            /** @var Pesanan $locked */
            $locked = Pesanan::query()
                ->whereKey($order->id)
                ->with('latestPembayaran')
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isCodOrder()) {
                throw ValidationException::withMessages([
                    'payment_method' => ['Konfirmasi pembayaran tunai hanya berlaku untuk metode COD.'],
                ]);
            }

            $fromStatus = $locked->resolvedOrderStatus();

            if ($fromStatus === 'dibatalkan') {
                throw ValidationException::withMessages([
                    'status' => ['Pesanan yang telah dibatalkan tidak dapat dikonfirmasi lunas.'],
                ]);
            }

            if (! in_array($fromStatus, ['diproses', 'dikirim', 'selesai'], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Pembayaran COD tidak dapat dikonfirmasi pada status {$fromStatus}."],
                ]);
            }

            if ($fromStatus === 'selesai' && $locked->resolvedPaymentStatus() === 'dibayar') {
                return $locked->fresh(self::orderRelations(true));
            }

            $actorType = (string) ($context['actor_type'] ?? 'admin');
            $actorId = $context['actor_id'] ?? null;
            $actorName = $context['actor_name'] ?? 'Admin';

            $locked->update([
                'payment_status' => 'dibayar',
                'order_status' => 'selesai',
                'status' => 'selesai',
            ]);

            $payment = $locked->latestPembayaran;
            if ($payment) {
                $payment->update([
                    'transaction_status' => 'settlement',
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            } else {
                Pembayaran::create([
                    'pesanan_id' => $locked->id,
                    'transaction_id' => 'COD-PAID-'.$locked->order_code,
                    'payment_type' => 'cod_cash',
                    'gross_amount' => $locked->total_amount ?? $locked->total ?? 0,
                    'transaction_status' => 'settlement',
                    'paid_at' => now(),
                    'payload_response' => [
                        'confirmed_by' => $actorName,
                        'note' => 'Uang tunai COD diterima dan dikonfirmasi oleh Admin',
                    ],
                ]);
            }

            if ($fromStatus === 'diproses') {
                PesananStatusLog::create([
                    'pesanan_id' => $locked->id,
                    'from_status' => 'diproses',
                    'to_status' => 'dikirim',
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'tracking_number' => $locked->tracking_number,
                    'note' => 'Pesanan COD diantar oleh kurir toko Surabaya.',
                    'created_at' => now(),
                ]);

                PesananStatusLog::create([
                    'pesanan_id' => $locked->id,
                    'from_status' => 'dikirim',
                    'to_status' => 'selesai',
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'tracking_number' => $locked->tracking_number,
                    'note' => 'Uang tunai COD diterima dan pembayaran dikonfirmasi lunas oleh Admin.',
                    'created_at' => now(),
                ]);
            } elseif ($fromStatus === 'dikirim') {
                PesananStatusLog::create([
                    'pesanan_id' => $locked->id,
                    'from_status' => 'dikirim',
                    'to_status' => 'selesai',
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'actor_name' => $actorName,
                    'tracking_number' => $locked->tracking_number,
                    'note' => 'Uang tunai COD diterima dan pembayaran dikonfirmasi lunas oleh Admin.',
                    'created_at' => now(),
                ]);
            }

            return $locked->fresh(self::orderRelations(true));
        });

        $this->bestSellers->refreshForOrder($updated);

        return $updated;
    }

    public function restoreStockIfNeeded(Pesanan $order): void
    {
        $order->loadMissing('items.varian');

        foreach ($order->items as $item) {
            if ($item->produk_varian_id && ($item->stock_deducted ?? true)) {
                $item->varian?->increment('stock', $item->quantity);
                $item->update(['stock_deducted' => false]);
            }
        }
    }

    public static function publicNoteForLog(PesananStatusLog $log): string
    {
        $rawNote = trim((string) ($log->note ?? ''));
        $lower = strtolower($rawNote);

        // Jangan ekspos catatan internal admin atau catatan refund/verifikasi manual pada endpoint publik
        if (
            $log->actor_type === 'admin'
            || str_contains($lower, 'perlu refund')
            || str_contains($lower, 'verifikasi pembayaran manual')
            || str_contains($lower, 'bukti transfer')
        ) {
            if ($log->from_status === 'dikirim' && $log->to_status === 'dikirim') {
                return $log->tracking_number
                    ? "Nomor resi diperbarui: {$log->tracking_number}."
                    : 'Informasi pengiriman diperbarui.';
            }

            return match ($log->to_status) {
                'menunggu_pembayaran' => 'Pesanan dibuat, menunggu pembayaran.',
                'diproses' => 'Pesanan sedang diproses oleh tim RiasKu.',
                'dikirim' => $log->tracking_number
                    ? "Paket diserahkan ke kurir dengan nomor resi {$log->tracking_number}."
                    : 'Paket sedang dikirim menuju alamat penerima.',
                'selesai' => 'Pesanan telah selesai.',
                'dibatalkan' => 'Pesanan dibatalkan.',
                default => 'Status pesanan diperbarui.',
            };
        }

        return $rawNote !== '' ? $rawNote : 'Status pesanan diperbarui.';
    }

    private function defaultNoteFor(string $fromStatus, string $toStatus, ?string $trackingNumber): string
    {
        if ($fromStatus === 'dikirim' && $toStatus === 'dikirim') {
            return $trackingNumber
                ? "Nomor resi diperbarui: {$trackingNumber}."
                : 'Informasi pengiriman diperbarui.';
        }

        return match ($toStatus) {
            'diproses' => 'Pesanan sedang diproses oleh tim RiasKu.',
            'dikirim' => $trackingNumber
                ? "Paket diserahkan ke kurir dengan nomor resi {$trackingNumber}."
                : 'Paket sedang dikirim menuju alamat penerima.',
            'selesai' => 'Pesanan telah selesai.',
            'dibatalkan' => 'Pesanan dibatalkan.',
            default => "Status berubah dari {$fromStatus} ke {$toStatus}.",
        };
    }
}
