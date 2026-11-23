<?php

namespace App\Services;

use App\Models\Pelanggan;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GoogleCustomerService
{
    /**
     * Temukan atau buat pelanggan dari klaim identitas Google yang sudah diverifikasi.
     *
     * @param  array<string, mixed>  $identity
     *
     * @throws HttpException
     */
    public function resolve(array $identity): Pelanggan
    {
        if (! filter_var($identity['email'] ?? null, FILTER_VALIDATE_EMAIL) || empty($identity['sub']) || ($identity['email_verified'] ?? false) !== true) {
            throw new HttpException(401, 'Identitas Google tidak valid atau email belum terverifikasi.');
        }

        $email = Str::lower($identity['email']);
        $customer = Pelanggan::where('google_id', $identity['sub'])->first();

        if ($customer) {
            $this->ensureActive($customer);

            return $customer;
        }

        $customer = Pelanggan::where('email', $email)->first();

        if ($customer && $customer->google_id && $customer->google_id !== $identity['sub']) {
            throw new HttpException(409, 'Email sudah terhubung ke akun Google lain.');
        }

        if ($customer) {
            $this->ensureActive($customer);
            $customer->forceFill(['google_id' => $identity['sub']])->save();
        } else {
            $base = Str::slug($identity['name'] ?? Str::before($email, '@'), '_');
            $base = Str::limit($base !== '' ? $base : 'pelanggan', 70, '');
            $username = $base;
            $suffix = 1;

            while (Pelanggan::where('username', $username)->exists()) {
                $username = Str::limit($base, 70, '').$suffix++;
            }

            $customer = Pelanggan::create([
                'username' => $username,
                'email' => $email,
                'google_id' => $identity['sub'],
                'password_hash' => null,
                'is_active' => true,
            ]);
        }

        return $customer;
    }

    /**
     * @throws HttpException
     */
    private function ensureActive(Pelanggan $customer): void
    {
        if (! $customer->is_active) {
            throw new HttpException(403, 'Akun pelanggan dinonaktifkan.');
        }
    }
}
