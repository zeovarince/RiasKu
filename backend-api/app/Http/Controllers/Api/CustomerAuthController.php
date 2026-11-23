<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Services\GoogleCustomerService;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $rules = [
            'username' => ['required', 'string', 'min:2', 'max:80', 'unique:pelanggan,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:pelanggan,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];

        if ($request->has('password_confirmation') || $request->filled('password_confirmation')) {
            $rules['password'][] = 'confirmed';
        }

        $data = $request->validate($rules, [
            'username.unique' => 'Username sudah digunakan.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $customer = Pelanggan::create([
            'username' => trim($data['username']),
            'email' => Str::lower(trim($data['email'])),
            'password_hash' => $data['password'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil. Silakan masuk.',
            'data' => ['user' => $this->userData($customer)],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($data['email']));
        $customer = Pelanggan::where('email', $email)->first();

        // Jika email bukan pelanggan, periksa apakah akun terdaftar sebagai Admin
        if (! $customer) {
            $admin = \App\Models\Admin::where('email', $email)->first();
            if ($admin && Hash::check($data['password'], $admin->password_hash)) {
                if (! $admin->is_active) {
                    return response()->json(['message' => 'Akun admin dinonaktifkan.'], 403);
                }

                return response()->json([
                    'data' => [
                        'token' => $admin->createToken('riasku-dashboard')->plainTextToken,
                        'user' => [
                            'id' => $admin->id,
                            'name' => $admin->name,
                            'username' => $admin->name,
                            'email' => $admin->email,
                            'account_type' => 'admin',
                        ],
                    ],
                ]);
            }
        }

        if ($customer && $customer->password_hash === null) {
            return response()->json(['message' => 'Akun ini menggunakan Google. Pilih tombol Masuk dengan Google.'], 422);
        }

        if (! $customer || ! Hash::check($data['password'], $customer->password_hash)) {
            return response()->json(['message' => 'email atau password salah'], 401);
        }

        if (! $customer->is_active) {
            return response()->json(['message' => 'Akun pelanggan dinonaktifkan.'], 403);
        }

        return $this->authenticated($customer);
    }

    public function google(Request $request, GoogleIdentityVerifier $verifier, GoogleCustomerService $customers): JsonResponse
    {
        $data = $request->validate(['credential' => ['required', 'string']]);
        $identity = $verifier->verify($data['credential']);

        if (! $identity) {
            return response()->json(['message' => 'Identitas Google tidak valid atau email belum terverifikasi.'], 401);
        }

        try {
            $customer = $customers->resolve($identity);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        return $this->authenticated($customer);
    }

    /**
     * Menukar kode sekali pakai dari alur redirect OAuth Google menjadi token API.
     */
    public function googleExchange(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        /** @var array{pelanggan_id?: int}|null $payload */
        $payload = Cache::pull('google-login:'.hash('sha256', $data['code']));

        if (! is_array($payload) || empty($payload['pelanggan_id'])) {
            return response()->json(['message' => 'Sesi login Google sudah kedaluwarsa. Silakan ulangi proses masuk dengan Google.'], 422);
        }

        $customer = Pelanggan::find($payload['pelanggan_id']);

        if (! $customer) {
            return response()->json(['message' => 'Akun pelanggan tidak ditemukan.'], 404);
        }

        if (! $customer->is_active) {
            return response()->json(['message' => 'Akun pelanggan dinonaktifkan.'], 403);
        }

        return $this->authenticated($customer);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->userData($request->user())]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    private function authenticated(Pelanggan $customer): JsonResponse
    {
        return response()->json([
            'data' => [
                'token' => $customer->createToken('riasku-web')->plainTextToken,
                'user' => $this->userData($customer),
            ],
        ]);
    }

    private function userData(Pelanggan $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->username,
            'username' => $customer->username,
            'email' => $customer->email,
            'account_type' => 'pelanggan',
        ];
    }
}
