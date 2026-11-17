<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KategoriController extends Controller
{
    public function index(): JsonResponse
    {
        $kategoris = Kategori::withCount('produks')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Daftar kategori berhasil diambil.',
            'data' => $kategoris,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:kategori,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:kategori,slug'],
            'icon' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori sudah digunakan.',
            'slug.unique' => 'Slug kategori sudah digunakan.',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $kategori = Kategori::create($validated);
        $kategori->loadCount('produks');

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => $kategori,
        ], 201);
    }

    public function show(Kategori $kategori): JsonResponse
    {
        $kategori->loadCount('produks');

        return response()->json([
            'message' => 'Detail kategori berhasil diambil.',
            'data' => $kategori,
        ]);
    }

    public function update(Request $request, Kategori $kategori): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('kategori', 'name')->ignore($kategori->id)],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('kategori', 'slug')->ignore($kategori->id)],
            'icon' => ['nullable', 'string', 'max:50'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Nama kategori sudah digunakan.',
            'slug.unique' => 'Slug kategori sudah digunakan.',
        ]);

        if (empty($validated['slug']) && $validated['name'] !== $kategori->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $kategori->update($validated);
        $kategori->loadCount('produks');

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $kategori,
        ]);
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        $productCount = $kategori->produks()->count();

        if ($productCount > 0) {
            return response()->json([
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki produk terkait.',
                'errors' => [
                    'kategori' => [
                        "Kategori ini masih memiliki {$productCount} produk terkait. Hapus atau pindahkan produk terlebih dahulu.",
                    ],
                ],
            ], 422);
        }

        $kategori->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }
}
