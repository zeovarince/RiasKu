<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keranjang;
use App\Models\KeranjangItem;
use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function formatItem(KeranjangItem $item): array
    {
        $produk = $item->produk;
        $varian = $item->varian;
        $unitPrice = $produk ? (int) ($produk->retail_price ?? 0) : 0;
        $variantStock = $varian ? (int) $varian->stock : ($produk ? (int) $produk->total_stock : 0);
        $isActiveVariant = $varian ? (bool) $varian->is_active : true;
        $isPreorderItem = $produk
            ? ((bool) $produk->is_active && $isActiveVariant && $variantStock <= 0 && (bool) $produk->is_preorder)
            : false;
        $stockLabel = $varian
            ? $varian->resolveStockLabel((bool) $produk?->is_preorder)
            : ($produk?->stock_label ?? 'Stok Habis');

        return [
            'id' => $item->id,
            'cart_id' => $item->keranjang_id,
            'product_id' => $item->produk_id,
            'variant_id' => $item->produk_varian_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => $unitPrice,
            'is_preorder_item' => $isPreorderItem,
            'preorder_estimate_days' => $isPreorderItem ? $produk?->preorder_estimate_days : null,
            'stock_label' => $stockLabel,
            'product' => $produk ? [
                'id' => $produk->id,
                'sku' => $produk->sku,
                'code' => $produk->sku ?? "PRODUK #{$produk->id}",
                'name' => $produk->name,
                'price' => $unitPrice,
                'stock' => $produk->total_stock,
                'stock_label' => $produk->stock_label,
                'image_url' => $produk->image_url,
                'category' => $produk->kategori?->slug ?? 'uncategorized',
                'categoryLabel' => $produk->kategori?->name ?? 'Lainnya',
                'art' => $produk->art,
                'type' => $produk->type,
                'is_preorder' => (bool) $produk->is_preorder,
                'preorder_estimate_days' => $produk->preorder_estimate_days,
            ] : null,
            'variant' => $varian ? [
                'id' => $varian->id,
                'name' => $varian->name,
                'stock' => (int) $varian->stock,
                'price' => $unitPrice,
                'stock_label' => $stockLabel,
                'is_preorder_item' => $isPreorderItem,
                'preorder_estimate_days' => $isPreorderItem ? $produk?->preorder_estimate_days : null,
            ] : null,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Keranjang::firstOrCreate(['pelanggan_id' => $user->id]);

        $items = KeranjangItem::query()
            ->where('keranjang_id', $cart->id)
            ->with(['produk.kategori', 'produk.primaryFoto', 'produk.activeVarians', 'varian'])
            ->get()
            ->map(fn (KeranjangItem $item) => $this->formatItem($item));

        return response()->json([
            'message' => 'Keranjang belanja berhasil dimuat.',
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:produk,id'],
            'variant_id' => ['nullable', 'integer', 'exists:produk_varian,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $cart = Keranjang::firstOrCreate(['pelanggan_id' => $user->id]);

        $product = Produk::with(['kategori', 'activeVarians'])->findOrFail($validated['product_id']);

        if (! $product->is_active) {
            return response()->json([
                'message' => 'Produk tidak tersedia atau sedang dinonaktifkan.',
            ], 422);
        }

        $variantId = $validated['variant_id'] ?? null;
        $variant = null;

        if ($variantId) {
            $variant = $product->activeVarians->firstWhere('id', $variantId);
            if (! $variant) {
                return response()->json([
                    'message' => 'Varian yang dipilih tidak valid untuk produk ini.',
                ], 422);
            }
        } elseif ($product->activeVarians->isNotEmpty()) {
            // Gunakan varian pertama secara otomatis jika produk memiliki varian tetapi request tidak mengirim variant_id
            $variant = $product->activeVarians->first();
            $variantId = $variant->id;
        }

        $requestedQty = max(1, (int) ($validated['quantity'] ?? 1));
        $maxStock = $variant ? (int) $variant->stock : (int) $product->total_stock;
        $isVariantPreorder = $maxStock <= 0 && (bool) $product->is_preorder;

        if (! $isVariantPreorder && $maxStock <= 0) {
            return response()->json([
                'message' => 'Stok produk ini sedang habis.',
            ], 422);
        }

        if ($isVariantPreorder && $requestedQty > 99) {
            return response()->json([
                'message' => 'Jumlah Pre-Order maksimal 99 pcs per pesanan.',
            ], 422);
        }

        $item = KeranjangItem::query()
            ->where('keranjang_id', $cart->id)
            ->where('produk_id', $product->id)
            ->where('produk_varian_id', $variantId)
            ->first();

        if ($item) {
            $newQuantity = $item->quantity + $requestedQty;
            if ($isVariantPreorder) {
                $newQuantity = min(99, $newQuantity);
            } elseif ($newQuantity > $maxStock) {
                $newQuantity = max($item->quantity, $maxStock);
            }
            $item->quantity = $newQuantity;
            $item->save();
        } else {
            $initialQty = $isVariantPreorder ? min(99, $requestedQty) : min($requestedQty, max(1, $maxStock));
            $item = KeranjangItem::create([
                'keranjang_id' => $cart->id,
                'produk_id' => $product->id,
                'produk_varian_id' => $variantId,
                'quantity' => $initialQty,
            ]);
        }

        $item->load(['produk.kategori', 'produk.primaryFoto', 'produk.activeVarians', 'varian']);

        return response()->json([
            'message' => 'Item berhasil ditambahkan ke keranjang.',
            'data' => $this->formatItem($item),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $cart = Keranjang::where('pelanggan_id', $user->id)->firstOrFail();

        $item = KeranjangItem::query()
            ->where('keranjang_id', $cart->id)
            ->where('id', $id)
            ->with(['produk.activeVarians', 'varian'])
            ->first();

        if (! $item) {
            return response()->json([
                'message' => 'Item keranjang tidak ditemukan.',
            ], 404);
        }

        if (! $item->produk?->is_active || ($item->produk_varian_id && ! $item->varian?->is_active)) {
            return response()->json([
                'message' => 'Produk atau varian sudah tidak tersedia.',
            ], 422);
        }

        $maxStock = $item->varian ? (int) $item->varian->stock : (int) $item->produk->total_stock;
        $isVariantPreorder = $maxStock <= 0 && (bool) $item->produk->is_preorder;
        $nextQty = (int) $validated['quantity'];

        if (! $isVariantPreorder && $maxStock <= 0) {
            return response()->json([
                'message' => 'Stok produk ini sedang habis.',
            ], 422);
        }

        if ($isVariantPreorder && $nextQty > 99) {
            return response()->json([
                'message' => 'Jumlah Pre-Order maksimal 99 pcs.',
            ], 422);
        }

        if (! $isVariantPreorder && $nextQty > $maxStock) {
            return response()->json([
                'message' => "Jumlah melebihi stok yang tersedia (maksimal {$maxStock}).",
            ], 422);
        }

        $item->quantity = $nextQty;
        $item->save();

        $item->load(['produk.kategori', 'produk.primaryFoto', 'produk.activeVarians', 'varian']);

        return response()->json([
            'message' => 'Jumlah item keranjang berhasil diperbarui.',
            'data' => $this->formatItem($item),
        ]);
    }

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        $cart = Keranjang::where('pelanggan_id', $user->id)->firstOrFail();

        $item = KeranjangItem::query()
            ->where('keranjang_id', $cart->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return response()->json([
                'message' => 'Item keranjang tidak ditemukan.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item keranjang berhasil dihapus.',
            'data' => [
                'id' => (int) $id,
            ],
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = Keranjang::where('pelanggan_id', $user->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'message' => 'Keranjang server berhasil dikosongkan.',
            'data' => [],
        ]);
    }
}
