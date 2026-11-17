<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdukVarianController extends Controller
{
    public function store(Request $request, Produk $produk): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('produk_varian', 'name')->where('produk_id', $produk->id),
            ],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama varian wajib diisi.',
            'name.unique' => 'Nama varian sudah digunakan pada produk ini.',
            'stock.required' => 'Stok varian wajib diisi.',
            'stock.min' => 'Stok varian minimal 0.',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $varian = $produk->varians()->create($validated);

        return response()->json([
            'message' => 'Varian berhasil ditambahkan.',
            'data' => $varian,
        ], 201);
    }

    public function update(Request $request, ProdukVarian $varian): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('produk_varian', 'name')
                    ->where('produk_id', $varian->produk_id)
                    ->ignore($varian->id),
            ],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama varian wajib diisi.',
            'name.unique' => 'Nama varian sudah digunakan pada produk ini.',
            'stock.required' => 'Stok varian wajib diisi.',
            'stock.min' => 'Stok varian minimal 0.',
        ]);

        $varian->update($validated);

        return response()->json([
            'message' => 'Varian berhasil diperbarui.',
            'data' => $varian,
        ]);
    }

    public function updateStatus(Request $request, ProdukVarian $varian): JsonResponse
    {
        $newStatus = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : !$varian->is_active;

        $varian->update(['is_active' => $newStatus]);
        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Varian berhasil {$statusText}.",
            'data' => $varian,
        ]);
    }
}
