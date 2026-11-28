<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\JsonResponse;

class KategoriController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Kategori::withCount(['produks' => function ($query) {
            $query->where('is_active', true);
        }])->orderBy('name')->get();

        return response()->json([
            'message' => 'Daftar kategori berhasil dimuat.',
            'data' => $categories,
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }
}
