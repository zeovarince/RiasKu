<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', Str::lower(trim($data['email'])))->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password_hash)) {
            return response()->json(['message' => 'email atau password salah'], 401);
        }

        if (! $admin->is_active) {
            return response()->json(['message' => 'Akun admin dinonaktifkan.'], 403);
        }

        return response()->json([
            'data' => [
                'token' => $admin->createToken('riasku-dashboard')->plainTextToken,
                'user' => $this->userData($admin),
            ],
        ]);
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

    private function userData(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'account_type' => 'admin',
        ];
    }
}
