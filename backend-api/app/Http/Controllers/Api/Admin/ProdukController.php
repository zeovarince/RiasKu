<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Support\SqlSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Produk::with(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock');

        if ($request->filled('q') || $request->filled('search')) {
            $keyword = (string) ($request->input('q') ?? $request->input('search'));
            $operator = SqlSearch::operator($query);
            $pattern = SqlSearch::pattern($keyword);
            $query->where(function ($q) use ($operator, $pattern): void {
                $q->where('name', $operator, $pattern)
                    ->orWhere('brand', $operator, $pattern)
                    ->orWhere('sku', $operator, $pattern);
            });
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->input('kategori_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('stock_label')) {
            $query->filterStockLabel((string) $request->input('stock_label'));
        }

        if ($request->boolean('restock') || $request->boolean('needs_restock')) {
            $query->needsRestock();
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest('id')->paginate($perPage);

        return response()->json([
            'message' => 'Daftar produk berhasil diambil.',
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
            'kategori_id' => ['required', 'exists:kategori,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:produk,sku'],
            'weight_grams' => ['required', 'integer', 'min:1'],
            'base_price' => ['required', 'integer', 'min:1'],
            'retail_price' => ['required', 'integer', 'min:1'],
            'pack_quantity' => ['nullable', 'integer', 'min:2'],
            'pack_price' => ['nullable', 'integer', 'min:1'],
            'is_preorder' => ['nullable', 'boolean'],
            'preorder_estimate_days' => ['nullable', 'integer'],
            'short_ingredients' => ['nullable', 'string'],
            'bpom_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'varians' => ['nullable', 'array'],
            'varians.*.name' => ['required_with:varians', 'string', 'max:100'],
            'varians.*.stock' => ['required_with:varians', 'integer', 'min:0'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
        ], [
            'kategori_id.required' => 'Kategori produk wajib dipilih.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak valid.',
            'name.required' => 'Nama produk wajib diisi.',
            'brand.required' => 'Merek produk wajib diisi.',
            'weight_grams.required' => 'Berat produk (gram) wajib diisi.',
            'weight_grams.min' => 'Berat produk minimal 1 gram.',
            'base_price.required' => 'Harga modal produk wajib diisi.',
            'base_price.min' => 'Harga modal minimal Rp1.',
            'retail_price.required' => 'Harga eceran produk wajib diisi.',
            'retail_price.min' => 'Harga eceran minimal Rp1.',
            'sku.unique' => 'SKU produk sudah digunakan.',
        ]);

        $basePrice = (int) $validated['base_price'];
        $retailPrice = (int) $validated['retail_price'];
        $isPreorder = filter_var($validated['is_preorder'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $estimateDays = $validated['preorder_estimate_days'] ?? null;

        // Aturan Pre-Order
        if ($isPreorder) {
            if (! $estimateDays || $estimateDays < 1 || $estimateDays > 6) {
                throw ValidationException::withMessages([
                    'preorder_estimate_days' => ['Estimasi Pre-Order wajib diisi antara 1 sampai 6 hari bila Pre-Order aktif.'],
                ]);
            }
        } else {
            $validated['preorder_estimate_days'] = null;
        }

        // Aturan Harga Pack: wajib di antara modal dan eceran
        if (! empty($validated['pack_price'])) {
            $packPrice = (int) $validated['pack_price'];
            if ($packPrice <= $basePrice || $packPrice >= $retailPrice) {
                throw ValidationException::withMessages([
                    'pack_price' => [
                        'Harga pack per pcs harus berada di antara harga modal (Rp'.number_format($basePrice, 0, ',', '.').
                        ') dan harga eceran (Rp'.number_format($retailPrice, 0, ',', '.').').',
                    ],
                ]);
            }
        }

        // Cek duplikasi nama varian
        if (! empty($validated['varians'])) {
            $names = array_map(fn ($v) => strtolower(trim($v['name'])), $validated['varians']);
            if (count($names) !== count(array_unique($names))) {
                throw ValidationException::withMessages([
                    'varians' => ['Nama varian harus unik dalam satu produk.'],
                ]);
            }
        }

        $produk = DB::transaction(function () use ($validated, $request, $isPreorder): Produk {
            $validated['is_preorder'] = $isPreorder;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $variansData = $validated['varians'] ?? null;
            unset($validated['varians'], $validated['initial_stock']);

            $produk = Produk::create($validated);

            if (! empty($variansData)) {
                foreach ($variansData as $item) {
                    $produk->varians()->create([
                        'name' => trim($item['name']),
                        'stock' => (int) ($item['stock'] ?? 0),
                        'is_active' => true,
                    ]);
                }
            } else {
                // Produk tanpa varian otomatis memiliki varian "Standar"
                $initialStock = (int) ($request->input('initial_stock') ?? 0);
                $produk->varians()->create([
                    'name' => 'Standar',
                    'stock' => $initialStock,
                    'is_active' => true,
                ]);
            }

            return $produk;
        });

        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->loadSum(['activeVarians as active_stock_sum'], 'stock');

        $warnings = [];
        if ($produk->price_warning) {
            $warnings[] = $produk->price_warning;
        }

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $produk,
            'warnings' => $warnings,
        ], 201);
    }

    public function show(Produk $produk): JsonResponse
    {
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->loadSum(['activeVarians as active_stock_sum'], 'stock');

        return response()->json([
            'message' => 'Detail produk berhasil diambil.',
            'data' => $produk,
        ]);
    }

    public function update(Request $request, Produk $produk): JsonResponse
    {
        $validated = $request->validate([
            'kategori_id' => ['required', 'exists:kategori,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('produk', 'sku')->ignore($produk->id)],
            'weight_grams' => ['required', 'integer', 'min:1'],
            'base_price' => ['required', 'integer', 'min:1'],
            'retail_price' => ['required', 'integer', 'min:1'],
            'pack_quantity' => ['nullable', 'integer', 'min:2'],
            'pack_price' => ['nullable', 'integer', 'min:1'],
            'is_preorder' => ['nullable', 'boolean'],
            'preorder_estimate_days' => ['nullable', 'integer'],
            'short_ingredients' => ['nullable', 'string'],
            'bpom_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'varians' => ['nullable', 'array', 'min:1'],
            'varians.*.id' => ['nullable', 'integer', 'distinct'],
            'varians.*.name' => ['required_with:varians', 'string', 'max:100'],
            'varians.*.stock' => ['required_with:varians', 'integer', 'min:0'],
            'varians.*.original_stock' => ['nullable', 'integer', 'min:0'],
            'varians.*.is_active' => ['nullable', 'boolean'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
            'original_initial_stock' => ['nullable', 'integer', 'min:0'],
        ], [
            'kategori_id.required' => 'Kategori produk wajib dipilih.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak valid.',
            'name.required' => 'Nama produk wajib diisi.',
            'brand.required' => 'Merek produk wajib diisi.',
            'weight_grams.required' => 'Berat produk (gram) wajib diisi.',
            'base_price.required' => 'Harga modal produk wajib diisi.',
            'retail_price.required' => 'Harga eceran produk wajib diisi.',
            'sku.unique' => 'SKU produk sudah digunakan.',
        ]);

        $basePrice = (int) $validated['base_price'];
        $retailPrice = (int) $validated['retail_price'];
        $isPreorder = filter_var($validated['is_preorder'] ?? $produk->is_preorder, FILTER_VALIDATE_BOOLEAN);
        $estimateDays = $validated['preorder_estimate_days'] ?? null;

        // Aturan Pre-Order
        if ($isPreorder) {
            if (! $estimateDays || $estimateDays < 1 || $estimateDays > 6) {
                throw ValidationException::withMessages([
                    'preorder_estimate_days' => ['Estimasi Pre-Order wajib diisi antara 1 sampai 6 hari bila Pre-Order aktif.'],
                ]);
            }
        } else {
            $validated['preorder_estimate_days'] = null;
        }

        // Aturan Harga Pack: wajib di antara modal dan eceran
        if (! empty($validated['pack_price'])) {
            $packPrice = (int) $validated['pack_price'];
            if ($packPrice <= $basePrice || $packPrice >= $retailPrice) {
                throw ValidationException::withMessages([
                    'pack_price' => [
                        'Harga pack per pcs harus berada di antara harga modal (Rp'.number_format($basePrice, 0, ',', '.').
                        ') dan harga eceran (Rp'.number_format($retailPrice, 0, ',', '.').').',
                    ],
                ]);
            }
        }

        // Cek duplikasi nama varian
        if (! empty($validated['varians'])) {
            $names = array_map(fn ($v) => strtolower(trim($v['name'])), $validated['varians']);
            if (count($names) !== count(array_unique($names))) {
                throw ValidationException::withMessages([
                    'varians' => ['Nama varian harus unik dalam satu produk.'],
                ]);
            }
        }

        $variansData = $validated['varians'] ?? null;
        $hasVarians = $request->has('varians') && is_array($variansData) && count($variansData) > 0;
        $hasInitialStock = ! $hasVarians && $request->has('initial_stock') && $validated['initial_stock'] !== null;
        $initialStock = $hasInitialStock ? (int) $validated['initial_stock'] : null;
        $originalInitialStock = array_key_exists('original_initial_stock', $validated) && $validated['original_initial_stock'] !== null
            ? (int) $validated['original_initial_stock']
            : null;

        unset($validated['varians'], $validated['initial_stock'], $validated['original_initial_stock']);
        $validated['is_preorder'] = $isPreorder;

        DB::transaction(function () use ($produk, $validated, $hasVarians, $variansData, $hasInitialStock, $initialStock, $originalInitialStock): void {
            $produk->update($validated);

            if ($hasVarians) {
                $this->syncCustomVariants($produk, $variansData);
            } elseif ($hasInitialStock) {
                $this->syncStandardVariant($produk, (int) $initialStock, $originalInitialStock);
            }
        }, 3);

        $produk->refresh();
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->loadSum(['activeVarians as active_stock_sum'], 'stock');

        $warnings = [];
        if ($produk->price_warning) {
            $warnings[] = $produk->price_warning;
        }

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $produk,
            'warnings' => $warnings,
        ]);
    }

    public function updateStatus(Request $request, Produk $produk): JsonResponse
    {
        $newStatus = $request->has('is_active')
            ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : ! $produk->is_active;

        $produk->update(['is_active' => $newStatus]);
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->loadSum(['activeVarians as active_stock_sum'], 'stock');

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Produk berhasil {$statusText}.",
            'data' => $produk,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variansData
     */
    private function syncCustomVariants(Produk $produk, array $variansData): void
    {
        $existing = $produk->varians()->orderBy('id')->lockForUpdate()->get();
        $keptIds = [];

        foreach ($variansData as $item) {
            $trimmedName = trim((string) $item['name']);
            $lowerName = strtolower($trimmedName);
            $requestedStock = max(0, (int) ($item['stock'] ?? 0));
            $originalStock = array_key_exists('original_stock', $item) && $item['original_stock'] !== null
                ? max(0, (int) $item['original_stock'])
                : null;
            $isActive = array_key_exists('is_active', $item)
                ? filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $variant = null;
            if (! empty($item['id'])) {
                $variant = $existing->firstWhere('id', (int) $item['id']);
            }
            if (! $variant) {
                $variant = $existing->first(
                    fn (ProdukVarian $v): bool => strtolower(trim($v->name)) === $lowerName
                );
            }

            if ($variant) {
                $conflict = $existing->first(
                    fn (ProdukVarian $v): bool => $v->id !== $variant->id && strtolower(trim($v->name)) === $lowerName
                );
                if ($conflict) {
                    $conflict->update(['name' => $conflict->name.' (arsip-'.$conflict->id.')', 'is_active' => false]);
                }

                // Hindari lost-update stok bila ada checkout paralel antara form dibuka dan disimpan.
                $resolvedStock = $originalStock !== null
                    ? max(0, (int) $variant->stock + ($requestedStock - $originalStock))
                    : $requestedStock;

                $variant->update([
                    'name' => $trimmedName,
                    'stock' => $resolvedStock,
                    'is_active' => $isActive,
                ]);
                $keptIds[] = $variant->id;
            } else {
                $created = $produk->varians()->create([
                    'name' => $trimmedName,
                    'stock' => $requestedStock,
                    'is_active' => $isActive,
                ]);
                $keptIds[] = $created->id;
            }
        }

        if ($keptIds !== []) {
            $produk->varians()->whereNotIn('id', $keptIds)->update(['is_active' => false]);
        }
    }

    private function syncStandardVariant(Produk $produk, int $stock, ?int $originalStock = null): void
    {
        $existing = $produk->varians()->orderBy('id')->lockForUpdate()->get();
        $standard = $existing->first(
            fn (ProdukVarian $v): bool => strtolower(trim($v->name)) === 'standar'
        ) ?? ($existing->count() === 1 ? $existing->first() : null);

        if ($standard) {
            $resolvedStock = $originalStock !== null
                ? max(0, (int) $standard->stock + (max(0, $stock) - max(0, $originalStock)))
                : max(0, $stock);

            $standard->update([
                'name' => 'Standar',
                'stock' => $resolvedStock,
                'is_active' => true,
            ]);
            $produk->varians()->where('id', '!=', $standard->id)->update(['is_active' => false]);
        } else {
            $created = $produk->varians()->create([
                'name' => 'Standar',
                'stock' => max(0, $stock),
                'is_active' => true,
            ]);
            $produk->varians()->where('id', '!=', $created->id)->update(['is_active' => false]);
        }
    }
}
