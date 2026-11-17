<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pesanan;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MidtransService
{
    protected string $serverKey;
    protected string $clientKey;
    protected bool $isProduction;
    protected bool $isMock;

    public function __construct()
    {
        $this->serverKey = (string) env('MIDTRANS_SERVER_KEY', '');
        $this->clientKey = (string) env('MIDTRANS_CLIENT_KEY', '');
        $this->isProduction = (bool) env('MIDTRANS_IS_PRODUCTION', false);
        $this->isMock = (bool) env('MIDTRANS_MOCK', empty($this->serverKey));
    }

    public function getBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function charge(Pesanan $pesanan, string $channel): array
    {
        if ($channel === 'cash' || $pesanan->payment_method === 'cod') {
            return $this->generateCodCharge($pesanan);
        }

        if ($this->isMock || empty($this->serverKey)) {
            return $this->generateMockCharge($pesanan, $channel);
        }

        try {
            return $this->chargeRealMidtrans($pesanan, $channel);
        } catch (Exception $e) {
            // Fallback graceful ke simulator jika koneksi Midtrans Sandbox sedang gangguan/offline
            return $this->generateMockCharge($pesanan, $channel, 'Fallback dari Midtrans Sandbox: ' . $e->getMessage());
        }
    }

    private function generateCodCharge(Pesanan $pesanan): array
    {
        $transactionId = 'COD-' . $pesanan->order_code;
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
            'gross_amount' => $pesanan->total_amount,
            'transaction_status' => 'pending',
            'expiry_time' => now()->addDays(3),
            'instructions' => [
                'Siapkan uang pas sebesar Rp ' . number_format($pesanan->total_amount, 0, ',', '.') . ' saat pesanan sampai.',
                'Kurir RiasKu akan mengantarkan pesanan ke alamat tujuan Anda di Kota Surabaya.',
                'Lakukan pengecekan fisik barang bersama kurir sebelum melakukan pembayaran tunai.',
            ],
            'payload_response' => [
                'mode' => 'cod',
                'description' => 'Cash On Delivery Surabaya',
            ],
        ];
    }

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
                'note' => $note ?? 'Simulasi Sandbox RiasKu Dual-Mode',
            ],
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
            default:
                $result['payment_type'] = 'qris';
                $result['bank'] = null;
                $result['va_number'] = null;
                $result['bill_key'] = null;
                $result['biller_code'] = null;
                $qrString = "00020101021226680016ID.CO.RIASKU.EWALLET01{$amount}5303360" . strtoupper(Str::random(6));
                $result['qr_string'] = $qrString;
                $result['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
                $result['deeplink_url'] = "https://simulator.sandbox.midtrans.com/pay?amount={$amount}&order_id={$pesanan->order_code}";
                $result['instructions'] = [
                    "Buka aplikasi " . ucfirst($channel) . " di ponsel Anda.",
                    "Klik bayar atau scan QR Code di atas.",
                    "Periksa total pembayaran Rp " . number_format($amount, 0, ',', '.') . ".",
                    "Konfirmasi pembayaran dengan PIN e-wallet Anda.",
                ];
                break;
        }

        return $result;
    }

    private function chargeRealMidtrans(Pesanan $pesanan, string $channel): array
    {
        $auth = base64_encode($this->serverKey . ':');
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $auth,
        ];

        $payload = [
            'transaction_details' => [
                'order_id' => $pesanan->order_code,
                'gross_amount' => $pesanan->total_amount,
            ],
            'customer_details' => [
                'first_name' => $pesanan->customer_name,
                'phone' => $pesanan->customer_phone,
            ],
        ];

        if ($channel === 'qris') {
            $payload['payment_type'] = 'qris';
            $payload['qris'] = ['acquirer' => 'gopay'];
        } elseif (in_array($channel, ['bca_va', 'bni_va', 'bri_va', 'permata_va'])) {
            $bank = explode('_', $channel)[0];
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = ['bank' => $bank];
        } elseif ($channel === 'mandiri_va') {
            $payload['payment_type'] = 'echannel';
            $payload['echannel'] = [
                'bill_info1' => 'Pembayaran RiasKu',
                'bill_info2' => $pesanan->order_code,
            ];
        } elseif ($channel === 'gopay') {
            $payload['payment_type'] = 'gopay';
        } elseif ($channel === 'shopeepay') {
            $payload['payment_type'] = 'shopeepay';
        } else {
            $payload['payment_type'] = 'qris';
        }

        $response = Http::withHeaders($headers)->timeout(15)->post($this->getBaseUrl() . '/charge', $payload);

        if (!$response->successful()) {
            throw new Exception('Midtrans charge failed: ' . $response->body());
        }

        $res = $response->json();

        return [
            'transaction_id' => $res['transaction_id'] ?? ('MID-' . Str::upper(Str::random(10))),
            'payment_type' => $res['payment_type'] ?? $payload['payment_type'],
            'bank' => $res['va_numbers'][0]['bank'] ?? null,
            'va_number' => $res['va_numbers'][0]['va_number'] ?? ($res['permata_va_number'] ?? null),
            'bill_key' => $res['bill_key'] ?? null,
            'biller_code' => $res['biller_code'] ?? null,
            'qr_string' => $res['qr_string'] ?? null,
            'qr_url' => $res['actions'][0]['url'] ?? null,
            'deeplink_url' => $res['actions'][1]['url'] ?? ($res['actions'][0]['url'] ?? null),
            'gross_amount' => (int) ($res['gross_amount'] ?? $pesanan->total_amount),
            'transaction_status' => $res['transaction_status'] ?? 'pending',
            'expiry_time' => isset($res['expiry_time']) ? now()->parse($res['expiry_time']) : now()->addHours(24),
            'instructions' => [
                'Lakukan pembayaran sesuai petunjuk metode yang dipilih.',
                'Periksa nomor transaksi dan nominal yang tertera.',
            ],
            'payload_response' => $res,
        ];
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $computed = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($computed, $signatureKey);
    }
}
