<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\StokMasuk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokMasukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'produk_id' => ['nullable', 'exists:produk,id'],
            'produk_varian_id' => ['nullable', 'exists:produk_varian,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = StokMasuk::with(['produk', 'varian', 'recordedBy']);

        if (! empty($validated['produk_id'])) {
            $query->where('produk_id', $validated['produk_id']);
        }

        if (! empty($validated['produk_varian_id'])) {
            $query->where('produk_varian_id', $validated['produk_varian_id']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('tanggal_masuk', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('tanggal_masuk', '<=', $validated['date_to']);
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $paginator = $query->orderByDesc('tanggal_masuk')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'message' => 'Riwayat barang masuk berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'produk_id' => ['required', 'exists:produk,id'],
            'produk_varian_id' => ['required', 'exists:produk_varian,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tanggal_masuk' => ['nullable', 'date_format:Y-m-d'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'produk_id.required' => 'Produk wajib dipilih.',
            'produk_id.exists' => 'Produk yang dipilih tidak valid.',
            'produk_varian_id.required' => 'Varian produk wajib dipilih.',
            'produk_varian_id.exists' => 'Varian produk yang dipilih tidak valid.',
            'quantity.required' => 'Jumlah barang masuk wajib diisi.',
            'quantity.integer' => 'Jumlah barang masuk harus berupa angka.',
            'quantity.min' => 'Jumlah barang masuk minimal 1 pcs.',
            'unit_cost.min' => 'Harga modal satuan tidak boleh negatif.',
            'tanggal_masuk.date_format' => 'Tanggal masuk harus berformat YYYY-MM-DD.',
            'date.date_format' => 'Tanggal masuk harus berformat YYYY-MM-DD.',
        ]);

        $tanggalMasuk = $validated['tanggal_masuk'] ?? $validated['date'] ?? now()->toDateString();

        $stokMasuk = DB::transaction(function () use ($validated, $tanggalMasuk, $request): StokMasuk {
            $varian = ProdukVarian::query()
                ->where('id', $validated['produk_varian_id'])
                ->where('produk_id', $validated['produk_id'])
                ->lockForUpdate()
                ->first();

            if ($varian === null) {
                throw ValidationException::withMessages([
                    'produk_varian_id' => ['Varian yang dipilih tidak tersedia pada produk tersebut.'],
                ]);
            }

            $stokMasuk = StokMasuk::create([
                'produk_id' => $validated['produk_id'],
                'produk_varian_id' => $varian->id,
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'] ?? null,
                'supplier' => $validated['supplier'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tanggal_masuk' => $tanggalMasuk,
                'recorded_by' => $request->user()->id,
            ]);

            $varian->increment('stock', $validated['quantity']);

            if (($validated['unit_cost'] ?? null) !== null) {
                Produk::where('id', $validated['produk_id'])->update([
                    'base_price' => $validated['unit_cost'],
                ]);
            }

            return $stokMasuk;
        }, 3);

        $stokMasuk->load(['produk', 'varian', 'recordedBy']);

        $produk = $stokMasuk->produk
            ->load(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->loadSum(['activeVarians as active_stock_sum'], 'stock');

        $warnings = [];
        if ($produk->needs_restock) {
            $warnings[] = 'Stok '.$produk->name.' tersisa '.$produk->total_stock.' pcs (batas minimum '.
                $produk->min_stock.' pcs). Segera lakukan restock.';
        }

        return response()->json([
            'message' => 'Barang masuk berhasil dicatat.',
            'data' => $stokMasuk,
            'product' => [
                'id' => $produk->id,
                'name' => $produk->name,
                'total_stock' => $produk->total_stock,
                'min_stock' => (int) $produk->min_stock,
                'needs_restock' => (bool) $produk->needs_restock,
                'restock_status' => $produk->restock_status,
            ],
            'warnings' => $warnings,
        ], 201);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $paginator = Produk::with(['kategori', 'varians'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->needsRestock()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Daftar produk yang perlu restock berhasil diambil.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
