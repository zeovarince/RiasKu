<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pesanan;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MidtransService
{
    protected string $serverKey;
    protected string $clientKey;
    protected string $merchantId;
    protected bool $isProduction;
    protected bool $allowSandboxSimulator;
    protected bool $isMock;
    protected string $baseUrl;

    public function __construct()
    {
        $config = config('services.midtrans', []);

        $this->serverKey = trim((string) ($config['server_key'] ?? ''));
        $this->clientKey = trim((string) ($config['client_key'] ?? ''));
        $this->merchantId = trim((string) ($config['merchant_id'] ?? ''));
        $this->isProduction = (bool) ($config['is_production'] ?? false);
        $this->allowSandboxSimulator = (bool) ($config['allow_sandbox_simulator'] ?? false);
        $defaultBaseUrl = $this->isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $this->baseUrl = trim((string) ($config['base_url'] ?: $defaultBaseUrl));

        // Jika server key kosong, otomatis pakai mode simulasi lokal
        $this->isMock = $this->serverKey === '';
    }

    public function canSimulate(): bool
    {
        $isProduction = filter_var(config('services.midtrans.is_production', $this->isProduction), FILTER_VALIDATE_BOOLEAN);
        if ($isProduction || app()->isProduction()) {
            return false;
        }

        $serverKey = trim((string) config('services.midtrans.server_key', $this->serverKey));
        $allowSandbox = filter_var(config('services.midtrans.allow_sandbox_simulator', $this->allowSandboxSimulator), FILTER_VALIDATE_BOOLEAN);

        return $serverKey === '' || $allowSandbox;
    }

    public function mode(): string
    {
        if ($this->isMock) {
            return 'local_simulation';
        }

        return $this->isProduction ? 'live_production' : 'live_sandbox';
    }

    /**
     * Base URL endpoint Midtrans Core API v2.
     */
    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/v2';
    }

    /**
     * Authorization header untuk Midtrans Core API.
     */
    private function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->serverKey . ':');
    }

    // ─────────────────────────────────────────────────────────────────
    // CHARGE (Buat Transaksi Baru)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Charge: Membuat transaksi pembayaran.
     * - COD → generateCodCharge (tidak memanggil Midtrans)
     * - Server key kosong → generateMockCharge (simulasi lokal)
     * - Server key terisi → chargeRealMidtrans (Midtrans Sandbox/Prod)
     */
    public function charge(Pesanan $pesanan, string $channel): array
    {
        // PPL-20: COD Surabaya tidak memanggil Midtrans sama sekali
        if ($channel === 'cash' || $pesanan->payment_method === 'cod') {
            return $this->generateCodCharge($pesanan);
        }

        // Jika server key kosong → simulasi lokal (fail-closed di production)
        if ($this->isMock) {
            if ($this->isProduction || app()->isProduction()) {
                Log::error('[MidtransService] MIDTRANS_SERVER_KEY belum dikonfigurasi di lingkungan produksi.');
                throw new \RuntimeException('Konfigurasi payment gateway Midtrans belum tersedia.');
            }
            Log::info('[MidtransService] Mode simulasi lokal aktif (MIDTRANS_SERVER_KEY kosong).');
            return $this->generateMockCharge($pesanan, $channel);
        }

        // Panggil Midtrans Sandbox/Production asli
        try {
            Log::info("[MidtransService] Charge real Midtrans: order={$pesanan->order_code}, channel={$channel}");
            return $this->chargeRealMidtrans($pesanan, $channel);
        } catch (Exception $e) {
            if ($this->isProduction || app()->isProduction()) {
                Log::error("[MidtransService] Production charge gagal tanpa fallback mock: {$e->getMessage()}");
                throw new \RuntimeException('Gagal membuat transaksi pembayaran ke Midtrans. Silakan coba lagi beberapa saat.', 0, $e);
            }
            Log::warning("[MidtransService] Fallback ke simulasi lokal (non-production): {$e->getMessage()}");
            return $this->generateMockCharge($pesanan, $channel, 'Fallback dari Midtrans Sandbox: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // CHARGE REAL MIDTRANS (Core API POST /v2/charge)
    // ─────────────────────────────────────────────────────────────────

    private function chargeRealMidtrans(Pesanan $pesanan, string $channel): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $pesanan->order_code,
                'gross_amount' => (int) $pesanan->total_amount,
            ],
            'customer_details' => [
                'first_name' => $pesanan->customer_name,
                'phone' => $pesanan->customer_phone,
                'email' => $pesanan->pelanggan?->email ?? 'customer@riasku.com',
            ],
        ];

        // Map channel ke payload Midtrans Core API
        switch ($channel) {
            case 'qris':
                $payload['payment_type'] = 'qris';
                $payload['qris'] = ['acquirer' => 'gopay'];
                break;

            case 'bca_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bca'];
                break;

            case 'bni_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bni'];
                break;

            case 'bri_va':
                $payload['payment_type'] = 'bank_transfer';
                $payload['bank_transfer'] = ['bank' => 'bri'];
                break;

            case 'mandiri_va':
                $payload['payment_type'] = 'echannel';
                $payload['echannel'] = [
                    'bill_info1' => 'Pembayaran RiasKu',
                    'bill_info2' => $pesanan->order_code,
                ];
                break;

            case 'gopay':
                $frontendTrackUrl = rtrim((string) config('services.frontend_url', 'http://localhost:5173'), '/')
                    . '/orders/track?code=' . urlencode($pesanan->order_code);
                $payload['payment_type'] = 'gopay';
                $payload['gopay'] = [
                    'enable_callback' => true,
                    'callback_url' => $frontendTrackUrl,
                ];
                break;

            case 'shopeepay':
                $frontendTrackUrl = rtrim((string) config('services.frontend_url', 'http://localhost:5173'), '/')
                    . '/orders/track?code=' . urlencode($pesanan->order_code);
                $payload['payment_type'] = 'shopeepay';
                $payload['shopeepay'] = [
                    'callback_url' => $frontendTrackUrl,
                ];
                break;

            default:
                // Default ke QRIS untuk channel tidak dikenal
                $payload['payment_type'] = 'qris';
                $payload['qris'] = ['acquirer' => 'gopay'];
                break;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authHeader(),
        ])->connectTimeout(5)->timeout(12)->post($this->getBaseUrl() . '/charge', $payload);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error("[MidtransService] Charge gagal: HTTP {$response->status()}, body: {$body}");
            throw new Exception("Midtrans charge failed (HTTP {$response->status()}): {$body}");
        }

        $res = $response->json();
        if (! is_array($res)) {
            throw new Exception('Midtrans charge returned invalid JSON body.');
        }

        $midtransStatusCode = isset($res['status_code']) ? (int) $res['status_code'] : 200;
        if ($midtransStatusCode >= 400) {
            $statusMessage = (string) ($res['status_message'] ?? 'Transaksi ditolak oleh Midtrans.');
            Log::error("[MidtransService] Charge ditolak Midtrans (status_code={$midtransStatusCode}): {$statusMessage}");
            throw new Exception("Midtrans charge rejected ({$midtransStatusCode}): {$statusMessage}");
        }

        Log::info('[MidtransService] Charge berhasil', [
            'transaction_id' => $res['transaction_id'] ?? '-',
            'payment_type' => $res['payment_type'] ?? '-',
            'status' => $res['transaction_status'] ?? '-',
        ]);

        return $this->parseChargeResponse($res, $pesanan, $channel);
    }

    /**
     * Parse response dari Midtrans Core API /v2/charge.
     */
    private function parseChargeResponse(array $res, Pesanan $pesanan, string $channel): array
    {
        $paymentType = $res['payment_type'] ?? $channel;

        // ---- Ambil QR Code URL dari actions ----
        $qrUrl = null;
        $deeplinkUrl = null;
        if (!empty($res['actions'])) {
            foreach ($res['actions'] as $action) {
                if (($action['name'] ?? '') === 'generate-qr-code') {
                    $qrUrl = $action['url'] ?? null;
                }
                if (($action['name'] ?? '') === 'deeplink-redirect') {
                    $deeplinkUrl = $action['url'] ?? null;
                }
            }
            // Fallback jika tidak ada nama spesifik
            if (!$qrUrl && !$deeplinkUrl) {
                $qrUrl = $res['actions'][0]['url'] ?? null;
                $deeplinkUrl = $res['actions'][1]['url'] ?? ($res['actions'][0]['url'] ?? null);
            }
        }

        // ---- VA Number ----
        $bank = null;
        $vaNumber = null;
        if (!empty($res['va_numbers'])) {
            $bank = $res['va_numbers'][0]['bank'] ?? null;
            $vaNumber = $res['va_numbers'][0]['va_number'] ?? null;
        }
        // Permata VA
        if (!$vaNumber && !empty($res['permata_va_number'])) {
            $bank = 'permata';
            $vaNumber = $res['permata_va_number'];
        }

        // ---- Bill Key / Biller Code (Mandiri echannel) ----
        $billKey = $res['bill_key'] ?? null;
        $billerCode = $res['biller_code'] ?? null;

        // ---- QR String ----
        $qrString = $res['qr_string'] ?? null;

        // ---- Expiry Time ----
        $expiryTime = null;
        if (!empty($res['expiry_time'])) {
            try {
                $expiryTime = \Carbon\Carbon::parse($res['expiry_time']);
            } catch (\Throwable) {
                $expiryTime = now()->addHours(24);
            }
        } else {
            $expiryTime = now()->addHours(24);
        }

        // ---- Instructions ----
        $instructions = $this->generateInstructions($channel, $pesanan, [
            'va_number' => $vaNumber,
            'bill_key' => $billKey,
            'biller_code' => $billerCode,
        ]);

        return [
            'transaction_id' => $res['transaction_id'] ?? ('MID-' . Str::upper(Str::random(10))),
            'payment_type' => $paymentType,
            'bank' => $bank,
            'va_number' => $vaNumber,
            'bill_key' => $billKey,
            'biller_code' => $billerCode,
            'qr_string' => $qrString,
            'qr_url' => $qrUrl,
            'deeplink_url' => $deeplinkUrl,
            'gross_amount' => (int) ($res['gross_amount'] ?? $pesanan->total_amount),
            'transaction_status' => $res['transaction_status'] ?? 'pending',
            'expiry_time' => $expiryTime,
            'instructions' => $instructions,
            'payload_response' => $res,
            'midtrans_mode' => $this->mode(),
            'can_simulate' => $this->canSimulate(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // SYNC STATUS (GET /v2/{order_id}/status)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Memanggil Midtrans GET /v2/{order_id}/status untuk sinkronisasi
     * status pembayaran secara real-time (pengganti webhook untuk localhost).
     */
    public function syncTransactionStatus(string $orderId): ?array
    {
        if ($this->isMock) {
            return null; // Tidak bisa sync ke Midtrans jika mode simulasi
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $this->authHeader(),
            ])->connectTimeout(4)->timeout(8)->get($this->getBaseUrl() . '/' . $orderId . '/status');

            if (!$response->successful()) {
                Log::warning("[MidtransService] Sync status gagal untuk {$orderId}: HTTP {$response->status()}");
                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            Log::warning("[MidtransService] Sync status exception: {$e->getMessage()}");
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // WEBHOOK SIGNATURE VERIFICATION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Verifikasi signature_key dari Midtrans notification webhook.
     * signature_key = SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $computed = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($computed, $signatureKey);
    }

    /**
     * Apakah server key dikonfigurasi (mode Midtrans asli)?
     */
    public function isLiveMode(): bool
    {
        return !$this->isMock;
    }

    /**
     * Dapatkan client key untuk frontend (aman diekspos).
     */
    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    // ─────────────────────────────────────────────────────────────────
    // COD CHARGE (PPL-20 - tidak memanggil Midtrans)
    // ─────────────────────────────────────────────────────────────────

    private function generateCodCharge(Pesanan $pesanan): array
    {
        $transactionId = 'COD-' . $pesanan->order_code;
        $amount = $pesanan->total_amount;

        return [
            'transaction_id' => $transactionId,
            'payment_type' => 'cod_cash',
            'bank' => null,
            'va_number' => null,
            'bill_key' => null,
            'biller_code' => null,
            'qr_string' => null,
            'qr_url' => null,
            'deeplink_url' => null,
            'gross_amount' => $amount,
            'transaction_status' => 'pending',
            'expiry_time' => now()->addDays(3),
            'instructions' => [
                'Siapkan uang pas sebesar Rp ' . number_format($amount, 0, ',', '.') . ' saat pesanan sampai.',
                'Kurir RiasKu akan mengantarkan pesanan ke alamat tujuan Anda di Kota Surabaya.',
                'Lakukan pengecekan fisik barang bersama kurir sebelum melakukan pembayaran tunai.',
            ],
            'payload_response' => [
                'mode' => 'cod',
                'description' => 'Cash On Delivery Surabaya',
            ],
            'midtrans_mode' => 'cod',
            'can_simulate' => false,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // MOCK CHARGE (Simulasi lokal jika server key kosong)
    // ─────────────────────────────────────────────────────────────────

    private function generateMockCharge(Pesanan $pesanan, string $channel, ?string $note = null): array
    {
        $transactionId = 'MOCK-TRX-' . Str::upper(Str::random(10));
        $expiry = now()->addHours(24);
        $amount = $pesanan->total_amount;

        $result = [
            'transaction_id' => $transactionId,
            'gross_amount' => $amount,
            'transaction_status' => 'pending',
            'expiry_time' => $expiry,
            'payload_response' => [
                'mode' => 'local_simulation',
                'channel' => $channel,
                'note' => $note ?? 'Simulasi Sandbox RiasKu Dual-Mode (isi MIDTRANS_SERVER_KEY untuk mode Midtrans asli)',
            ],
            'midtrans_mode' => 'local_simulation',
            'can_simulate' => $this->canSimulate(),
        ];

        switch ($channel) {
            case 'qris':
                $qrString = "00020101021226680016ID.CO.RIASKU.WWW01189360099990000000000208{$pesanan->id}520459995303360540{$amount}5802ID5914RIASKU SURABAYA6008SURABAYA62070703A016304" . strtoupper(Str::random(4));
                $result['payment_type'] = 'qris';
                $result['bank'] = null;
                $result['va_number'] = null;
                $result['bill_key'] = null;
                $result['biller_code'] = null;
                $result['qr_string'] = $qrString;
                $result['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
                $result['deeplink_url'] = null;
                $result['instructions'] = [
                    'Buka aplikasi e-wallet (GoPay, OVO, Dana, ShopeePay) atau mobile banking pilihan Anda.',
                    'Pilih menu Scan QR / Bayar dengan QRIS.',
                    'Arahkan kamera ke QR Code yang tampil di layar.',
                    'Periksa nama penerima: "RiasKu Cosmetics Surabaya" dan nominal Rp ' . number_format($amount, 0, ',', '.') . '.',
                    'Masukkan PIN Anda untuk menyelesaikan pembayaran.',
                ];
                break;

            case 'bca_va':
            case 'bni_va':
            case 'bri_va':
            case 'permata_va':
                $bank = explode('_', $channel)[0];
                $vaPrefix = match ($bank) {
                    'bca' => '12890',
                    'bni' => '8808',
                    'bri' => '10234',
                    default => '9880',
                };
                $vaNumber = $vaPrefix . str_pad((string) $pesanan->id, 8, '0', STR_PAD_LEFT);

                $result['payment_type'] = 'bank_transfer';
                $result['bank'] = $bank;
                $result['va_number'] = $vaNumber;
                $result['bill_key'] = null;
                $result['biller_code'] = null;
                $result['qr_string'] = null;
                $result['qr_url'] = null;
                $result['deeplink_url'] = null;
                $result['instructions'] = [
                    "Buka aplikasi Mobile Banking " . strtoupper($bank) . " atau ATM terdekat.",
                    "Pilih menu Transfer > Virtual Account.",
                    "Masukkan nomor Virtual Account: {$vaNumber}.",
                    "Pastikan rincian tagihan sesuai: RiasKu - {$pesanan->order_code} senilai Rp " . number_format($amount, 0, ',', '.') . ".",
                    "Konfirmasi dan selesaikan pembayaran sebelum batas waktu.",
                ];
                break;

            case 'mandiri_va':
                $billKey = str_pad((string) $pesanan->id, 8, '0', STR_PAD_LEFT);
                $billerCode = '70012';

                $result['payment_type'] = 'echannel';
                $result['bank'] = 'mandiri';
                $result['va_number'] = $billKey;
                $result['bill_key'] = $billKey;
                $result['biller_code'] = $billerCode;
                $result['qr_string'] = null;
                $result['qr_url'] = null;
                $result['deeplink_url'] = null;
                $result['instructions'] = [
                    'Buka aplikasi Livin\' by Mandiri atau ATM Mandiri.',
                    'Pilih menu Bayar / Pembayaran > Multi Payment.',
                    "Masukkan Kode Perusahaan: {$billerCode} (RiasKu).",
                    "Masukkan Nomor Pelanggan / Bill Key: {$billKey}.",
                    "Periksa nominal tagihan Rp " . number_format($amount, 0, ',', '.') . " dan selesaikan pembayaran.",
                ];
                break;

            case 'gopay':
            case 'shopeepay':
            case 'dana':
                $result['payment_type'] = $channel;
                $result['bank'] = null;
                $result['va_number'] = null;
                $result['bill_key'] = null;
                $result['biller_code'] = null;
                $qrString = "00020101021226680016ID.CO.RIASKU.EWALLET01{$amount}5303360" . strtoupper(Str::random(6));
                $result['qr_string'] = $qrString;
                $result['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
                $result['deeplink_url'] = "https://simulator.sandbox.midtrans.com/";
                $result['instructions'] = [
                    "Buka aplikasi " . ucfirst($channel) . " di ponsel Anda.",
                    "Klik bayar atau scan QR Code di atas.",
                    "Periksa total pembayaran Rp " . number_format($amount, 0, ',', '.') . ".",
                    "Konfirmasi pembayaran dengan PIN e-wallet Anda.",
                ];
                break;

            default:
                $result['payment_type'] = 'qris';
                $result['bank'] = null;
                $result['va_number'] = null;
                $result['bill_key'] = null;
                $result['biller_code'] = null;
                $qrString = "00020101021226680016ID.CO.RIASKU.EWALLET01{$amount}5303360" . strtoupper(Str::random(6));
                $result['qr_string'] = $qrString;
                $result['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
                $result['deeplink_url'] = null;
                $result['instructions'] = [
                    'Buka aplikasi e-wallet atau mobile banking pilihan Anda.',
                    'Pilih menu Scan QR / Bayar dengan QRIS.',
                    'Periksa total pembayaran Rp ' . number_format($amount, 0, ',', '.') . '.',
                    'Konfirmasi pembayaran dengan PIN Anda.',
                ];
                break;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER: Generate Instructions per Channel
    // ─────────────────────────────────────────────────────────────────

    public function matchesChannel(Pembayaran $payment, string $channel): bool
    {
        $normalized = strtolower(trim($channel));
        $paymentType = strtolower((string) $payment->payment_type);
        $bank = strtolower((string) ($payment->bank ?? ''));
        $payloadChannel = strtolower((string) ($payment->payload_response['channel'] ?? ''));

        return match ($normalized) {
            'qris' => $paymentType === 'qris' && ($payloadChannel === '' || $payloadChannel === 'qris'),
            'bca_va' => $paymentType === 'bank_transfer' && $bank === 'bca',
            'bni_va' => $paymentType === 'bank_transfer' && $bank === 'bni',
            'bri_va' => $paymentType === 'bank_transfer' && $bank === 'bri',
            'permata_va' => $paymentType === 'bank_transfer' && $bank === 'permata',
            'mandiri_va' => $paymentType === 'echannel',
            'gopay', 'shopeepay', 'dana' => $paymentType === $normalized || $payloadChannel === $normalized,
            default => $paymentType === $normalized,
        };
    }

    public function instructionsForPayment(Pembayaran $payment, Pesanan $pesanan, ?string $channel = null): array
    {
        $resolvedChannel = $channel ?: $this->resolveChannelFromPayment($payment);

        return $this->generateInstructions($resolvedChannel, $pesanan, [
            'va_number' => $payment->va_number,
            'bill_key' => $payment->bill_key,
            'biller_code' => $payment->biller_code,
        ]);
    }

    private function resolveChannelFromPayment(Pembayaran $payment): string
    {
        if (! empty($payment->payload_response['channel'])) {
            return strtolower((string) $payment->payload_response['channel']);
        }

        if ($payment->payment_type === 'bank_transfer' && ! empty($payment->bank)) {
            return strtolower((string) $payment->bank) . '_va';
        }

        if ($payment->payment_type === 'echannel') {
            return 'mandiri_va';
        }

        return strtolower((string) ($payment->payment_type ?: 'qris'));
    }

    private function generateInstructions(string $channel, Pesanan $pesanan, array $data): array
    {
        $amount = $pesanan->total_amount;
        $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');

        return match ($channel) {
            'qris' => [
                'Buka aplikasi e-wallet (GoPay, OVO, Dana, ShopeePay) atau mobile banking Anda.',
                'Pilih menu Scan QR / Bayar dengan QRIS.',
                'Arahkan kamera ke QR Code yang tampil di layar.',
                "Periksa nama penerima dan nominal {$formattedAmount}.",
                'Masukkan PIN Anda untuk menyelesaikan pembayaran.',
            ],
            'bca_va' => [
                'Buka aplikasi BCA Mobile atau kunjungi ATM BCA terdekat.',
                'Pilih menu Transfer > BCA Virtual Account.',
                "Masukkan nomor Virtual Account: {$data['va_number']}.",
                "Pastikan rincian tagihan sesuai: RiasKu - {$pesanan->order_code} senilai {$formattedAmount}.",
                'Konfirmasi dan selesaikan pembayaran.',
            ],
            'bni_va' => [
                'Buka aplikasi BNI Mobile Banking atau kunjungi ATM BNI.',
                'Pilih menu Transfer > Virtual Account Billing.',
                "Masukkan nomor Virtual Account: {$data['va_number']}.",
                "Pastikan rincian tagihan sesuai senilai {$formattedAmount}.",
                'Konfirmasi dan selesaikan pembayaran.',
            ],
            'bri_va' => [
                'Buka aplikasi BRImo atau kunjungi ATM BRI.',
                'Pilih menu Pembayaran > BRIVA.',
                "Masukkan nomor Virtual Account: {$data['va_number']}.",
                "Pastikan rincian tagihan sesuai senilai {$formattedAmount}.",
                'Konfirmasi dan selesaikan pembayaran.',
            ],
            'mandiri_va' => [
                "Buka aplikasi Livin' by Mandiri atau ATM Mandiri.",
                'Pilih menu Bayar / Pembayaran > Multi Payment.',
                "Masukkan Kode Perusahaan: {$data['biller_code']} (RiasKu).",
                "Masukkan Nomor Pelanggan / Bill Key: {$data['bill_key']}.",
                "Periksa nominal tagihan {$formattedAmount} dan selesaikan pembayaran.",
            ],
            'gopay' => [
                'Buka aplikasi GoPay / Gojek di ponsel Anda.',
                "Scan QR Code atau klik tombol 'Bayar via GoPay'.",
                "Periksa total pembayaran {$formattedAmount}.",
                'Konfirmasi pembayaran dengan PIN GoPay Anda.',
            ],
            'shopeepay' => [
                'Buka aplikasi Shopee di ponsel Anda.',
                "Scan QR Code atau klik tombol 'Bayar via ShopeePay'.",
                "Periksa total pembayaran {$formattedAmount}.",
                'Konfirmasi pembayaran dengan PIN ShopeePay Anda.',
            ],
            default => [
                'Lakukan pembayaran sesuai petunjuk metode yang dipilih.',
                "Periksa nomor transaksi dan nominal {$formattedAmount}.",
            ],
        };
    }
}
