<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Produk::with(['kategori', 'varians', 'fotos', 'primaryFoto']);

        if ($request->filled('q') || $request->filled('search')) {
            $keyword = $request->input('q') ?? $request->input('search');
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('brand', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->input('kategori_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest('id')->paginate($perPage);

        // Filter stock_label if requested (calculated attribute)
        if ($request->filled('stock_label')) {
            $expectedLabel = $request->input('stock_label');
            $filteredItems = $paginator->getCollection()->filter(function (Produk $p) use ($expectedLabel): bool {
                return strcasecmp($p->stock_label, $expectedLabel) === 0;
            })->values();

            $paginator->setCollection($filteredItems);
        }

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
            if (!$estimateDays || $estimateDays < 1 || $estimateDays > 6) {
                throw ValidationException::withMessages([
                    'preorder_estimate_days' => ['Estimasi Pre-Order wajib diisi antara 1 sampai 6 hari bila Pre-Order aktif.'],
                ]);
            }
        } else {
            $validated['preorder_estimate_days'] = null;
        }

        // Aturan Harga Pack: wajib di antara modal dan eceran
        if (!empty($validated['pack_price'])) {
            $packPrice = (int) $validated['pack_price'];
            if ($packPrice <= $basePrice || $packPrice >= $retailPrice) {
                throw ValidationException::withMessages([
                    'pack_price' => [
                        'Harga pack per pcs harus berada di antara harga modal (Rp' . number_format($basePrice, 0, ',', '.') .
                        ') dan harga eceran (Rp' . number_format($retailPrice, 0, ',', '.') . ').',
                    ],
                ]);
            }
        }

        // Cek duplikasi nama varian
        if (!empty($validated['varians'])) {
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

            if (!empty($variansData)) {
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

        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto']);

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
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto']);

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
            if (!$estimateDays || $estimateDays < 1 || $estimateDays > 6) {
                throw ValidationException::withMessages([
                    'preorder_estimate_days' => ['Estimasi Pre-Order wajib diisi antara 1 sampai 6 hari bila Pre-Order aktif.'],
                ]);
            }
        } else {
            $validated['preorder_estimate_days'] = null;
        }

        // Aturan Harga Pack: wajib di antara modal dan eceran
        if (!empty($validated['pack_price'])) {
            $packPrice = (int) $validated['pack_price'];
            if ($packPrice <= $basePrice || $packPrice >= $retailPrice) {
                throw ValidationException::withMessages([
                    'pack_price' => [
                        'Harga pack per pcs harus berada di antara harga modal (Rp' . number_format($basePrice, 0, ',', '.') .
                        ') dan harga eceran (Rp' . number_format($retailPrice, 0, ',', '.') . ').',
                    ],
                ]);
            }
        }

        $validated['is_preorder'] = $isPreorder;
        $produk->update($validated);
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto']);

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
            : !$produk->is_active;

        $produk->update(['is_active' => $newStatus]);
        $produk->load(['kategori', 'varians', 'fotos', 'primaryFoto']);

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Produk berhasil {$statusText}.",
            'data' => $produk,
        ]);
    }
}
