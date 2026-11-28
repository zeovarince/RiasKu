<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StoreSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = StoreSettings::current();

        return response()->json([
            'message' => 'Pengaturan toko berhasil dimuat.',
            'data' => $this->present($settings, publicView: true),
        ]);
    }

    public function adminShow(): JsonResponse
    {
        return response()->json([
            'message' => 'Pengaturan toko berhasil dimuat.',
            'data' => $this->present(StoreSettings::current()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_tagline' => ['nullable', 'string', 'max:255'],
            'store_logo' => ['nullable', 'url', 'max:2048'],
            'store_address' => ['nullable', 'string', 'max:2000'],
            'operational_hours' => ['nullable', 'string', 'max:500'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_default_message' => ['nullable', 'string', 'max:500'],
            'is_whatsapp_active' => ['required', 'boolean'],
        ]);

        $number = $this->normalizeWhatsAppNumber($data['whatsapp_number'] ?? null);

        if (($data['whatsapp_number'] ?? null) !== null && trim($data['whatsapp_number']) !== '' && $number === null) {
            throw ValidationException::withMessages([
                'whatsapp_number' => 'Nomor WhatsApp harus nomor Indonesia yang valid, misalnya 081234567890 atau 6281234567890.',
            ]);
        }

        if ((bool) $data['is_whatsapp_active'] && $number === null) {
            throw ValidationException::withMessages([
                'whatsapp_number' => 'Isi nomor WhatsApp yang valid sebelum mengaktifkan tombol WhatsApp.',
            ]);
        }

        $settings = StoreSettings::current();
        $message = trim((string) ($data['whatsapp_default_message'] ?? ''));

        $settings->fill([
            ...$data,
            'store_tagline' => trim((string) ($data['store_tagline'] ?? '')),
            'store_address' => trim((string) ($data['store_address'] ?? '')),
            'operational_hours' => trim((string) ($data['operational_hours'] ?? '')),
            'whatsapp_number' => $number,
            'whatsapp_default_message' => $message !== '' ? $message : StoreSettings::defaults()['whatsapp_default_message'],
        ]);
        $settings->save();

        return response()->json([
            'message' => 'Pengaturan toko berhasil disimpan.',
            'data' => $this->present($settings),
        ]);
    }

    private function normalizeWhatsAppNumber(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $compact = preg_replace('/[\s().-]+/', '', $value);

        if (! is_string($compact) || ! preg_match('/^\+?(?:62\d{8,12}|0\d{9,12}|8\d{9,12})$/', $compact)) {
            return null;
        }

        $digits = ltrim($compact, '+');

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^628\d{7,12}$/', $digits) ? $digits : null;
    }

    private function present(StoreSettings $settings, bool $publicView = false): array
    {
        return [
            'store_name' => $settings->store_name,
            'store_tagline' => (string) ($settings->store_tagline ?? ''),
            'store_logo' => $settings->store_logo,
            'store_address' => (string) ($settings->store_address ?? ''),
            'operational_hours' => (string) ($settings->operational_hours ?? ''),
            'whatsapp_number' => $publicView && ! $settings->is_whatsapp_active ? null : $settings->whatsapp_number,
            'whatsapp_default_message' => $settings->whatsapp_default_message,
            'is_whatsapp_active' => $settings->is_whatsapp_active,
            'updated_at' => $settings->updated_at,
        ];
    }
}
