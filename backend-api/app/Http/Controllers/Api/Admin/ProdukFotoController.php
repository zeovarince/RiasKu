<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\ProdukFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProdukFotoController extends Controller
{
    public function store(Request $request, Produk $produk): JsonResponse
    {
        $request->validate([
            'foto' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format gambar yang diperbolehkan: JPEG, JPG, PNG, WEBP.',
            'foto.max' => 'Ukuran gambar maksimal 2 MB.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar yang diperbolehkan: JPEG, JPG, PNG, WEBP.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ]);

        $file = $request->file('foto') ?? $request->file('image');

        if (!$file) {
            return response()->json([
                'message' => 'Berkas gambar wajib diunggah.',
                'errors' => ['foto' => ['Berkas gambar wajib dipilih.']],
            ], 422);
        }

        $path = $file->store('products', 'public');

        $hasExistingPhotos = $produk->fotos()->exists();
        $isPrimary = $request->has('is_primary')
            ? filter_var($request->input('is_primary'), FILTER_VALIDATE_BOOLEAN)
            : !$hasExistingPhotos;

        if ($isPrimary) {
            $produk->fotos()->update(['is_primary' => false]);
        }

        $sortOrder = $request->input('sort_order', (int) ($produk->fotos()->max('sort_order') ?? 0) + 1);

        $foto = $produk->fotos()->create([
            'image_path' => $path,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'message' => 'Foto produk berhasil diunggah.',
            'data' => $foto,
        ], 201);
    }

    public function update(Request $request, ProdukFoto $foto): JsonResponse
    {
        $validated = $request->validate([
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['is_primary']) && filter_var($validated['is_primary'], FILTER_VALIDATE_BOOLEAN)) {
            $foto->produk->fotos()->where('id', '!=', $foto->id)->update(['is_primary' => false]);
            $foto->is_primary = true;
        }

        if (isset($validated['sort_order'])) {
            $foto->sort_order = (int) $validated['sort_order'];
        }

        $foto->save();

        return response()->json([
            'message' => 'Foto produk berhasil diperbarui.',
            'data' => $foto,
        ]);
    }

    public function destroy(ProdukFoto $foto): JsonResponse
    {
        $produk = $foto->produk;
        $wasPrimary = $foto->is_primary;

        if (Storage::disk('public')->exists($foto->image_path)) {
            Storage::disk('public')->delete($foto->image_path);
        }

        $foto->delete();

        // Bila foto utama dihapus, jadikan foto pertama tersisa sebagai utama
        if ($wasPrimary && $produk) {
            $nextPrimary = $produk->fotos()->orderBy('sort_order')->orderBy('id')->first();
            if ($nextPrimary) {
                $nextPrimary->update(['is_primary' => true]);
            }
        }

        return response()->json([
            'message' => 'Foto produk berhasil dihapus.',
        ]);
    }
}
