<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keranjang;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(protected MidtransService $midtransService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->with(['items.produk', 'latestPembayaran'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'message' => 'Daftar pesanan berhasil dimuat.',
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_district' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', 'string', 'in:cod,online'],
            'payment_channel' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
        ]);

        // PPL-20: Validasi Ketat COD Khusus Kota Surabaya
        if ($validated['payment_method'] === 'cod' || $validated['payment_channel'] === 'cash' || $validated['payment_channel'] === 'cod') {
            $city = strtolower(trim($validated['shipping_city']));
            if (!str_contains($city, 'surabaya')) {
                return response()->json([
                    'message' => 'Metode pembayaran COD hanya tersedia untuk pengiriman wilayah Kota Surabaya.',
                    'errors' => [
                        'shipping_city' => ['Wilayah ini di luar jangkauan layanan COD Surabaya. Silakan gunakan Pembayaran Online.'],
                    ],
                ], 422);
            }
        }

        $user = $request->user();
        $orderItemsData = [];

        // Ambil item dari cart jika payload items kosong
        if (empty($validated['items'])) {
            $cart = Keranjang::where('pelanggan_id', $user->id)->with(['items.produk', 'items.varian'])->first();
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'message' => 'Keranjang belanja Anda masih kosong. Silakan pilih produk terlebih dahulu.',
                ], 422);
            }

            foreach ($cart->items as $cartItem) {
                $orderItemsData[] = [
                    'produk_id' => $cartItem->produk_id,
                    'produk_varian_id' => $cartItem->produk_varian_id,
                    'quantity' => $cartItem->quantity,
                ];
            }
        } else {
            $orderItemsData = $validated['items'];
        }

        return DB::transaction(function () use ($validated, $user, $orderItemsData) {
            $totalProductAmount = 0;
            $preparedItems = [];

            foreach ($orderItemsData as $itemData) {
                $product = Produk::with('activeVarians')->findOrFail($itemData['produk_id']);

                if (!$product->is_active) {
                    abort(422, "Produk {$product->name} saat ini sedang dinonaktifkan.");
                }

                $variantId = $itemData['produk_varian_id'] ?? null;
                $variant = null;

                if ($variantId) {
                    $variant = $product->activeVarians->firstWhere('id', $variantId);
                    if (!$variant) {
                        abort(422, "Varian produk tidak ditemukan.");
                    }
                } elseif ($product->activeVarians->isNotEmpty()) {
                    $variant = $product->activeVarians->first();
                    $variantId = $variant->id;
                }

                $qty = max(1, (int) ($itemData['quantity'] ?? 1));
                $stock = $variant ? $variant->stock : $product->total_stock;

                if (!$product->is_preorder && $qty > $stock) {
                    abort(422, "Stok untuk produk {$product->name} tidak mencukupi (sisa {$stock}).");
                }

                $price = (int) $product->retail_price;
                $lineSubtotal = $price * $qty;
                $totalProductAmount += $lineSubtotal;

                // Kurangi stok jika bukan pre-order
                if (!$product->is_preorder) {
                    if ($variant) {
                        $variant->decrement('stock', $qty);
                    }
                }

                $preparedItems[] = [
                    'produk_id' => $product->id,
                    'produk_varian_id' => $variantId,
                    'product_name' => $product->name,
                    'variant_name' => $variant ? $variant->name : null,
                    'product_sku' => $product->sku,
                    'unit_price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $shippingCost = 0; // Bebas ongkir COD Surabaya / promo toko
            $totalAmount = $totalProductAmount + $shippingCost;

            $isCod = $validated['payment_method'] === 'cod';
            $channel = $isCod ? 'cash' : $validated['payment_channel'];

            $order = Pesanan::create([
                'pelanggan_id' => $user->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_district' => $validated['shipping_district'] ?? 'Surabaya',
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                'shipping_courier' => $isCod ? 'Kurir Toko RiasKu (COD)' : 'JNE Regular / Pengiriman RiasKu',
                'shipping_cost' => $shippingCost,
                'total_product_amount' => $totalProductAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_channel' => $channel,
                'payment_status' => $isCod ? 'menunggu_pembayaran_cod' : 'menunggu_pembayaran',
                'order_status' => $isCod ? 'diproses' : 'menunggu_pembayaran',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($preparedItems as $item) {
                $order->items()->create($item);
            }

            // Bersihkan keranjang belanja
            $cart = Keranjang::where('pelanggan_id', $user->id)->first();
            if ($cart) {
                $cart->items()->delete();
            }

            // Inisiasi Pembayaran (PPL-19 Midtrans Dual-Mode / COD)
            $chargeResult = $this->midtransService->charge($order, $channel);

            $payment = Pembayaran::create([
                'pesanan_id' => $order->id,
                'transaction_id' => $chargeResult['transaction_id'],
                'payment_type' => $chargeResult['payment_type'],
                'bank' => $chargeResult['bank'] ?? null,
                'va_number' => $chargeResult['va_number'] ?? null,
                'bill_key' => $chargeResult['bill_key'] ?? null,
                'biller_code' => $chargeResult['biller_code'] ?? null,
                'qr_string' => $chargeResult['qr_string'] ?? null,
                'qr_url' => $chargeResult['qr_url'] ?? null,
                'deeplink_url' => $chargeResult['deeplink_url'] ?? null,
                'gross_amount' => $chargeResult['gross_amount'],
                'transaction_status' => $chargeResult['transaction_status'],
                'payload_response' => $chargeResult['payload_response'],
                'expiry_time' => $chargeResult['expiry_time'] ?? null,
            ]);

            $order->load(['items.produk', 'latestPembayaran']);

            return response()->json([
                'message' => $isCod
                    ? 'Pesanan COD Surabaya berhasil dibuat dan sedang diproses.'
                    : 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.',
                'data' => [
                    'order' => $order,
                    'payment' => array_merge($payment->toArray(), [
                        'instructions' => $chargeResult['instructions'] ?? [],
                    ]),
                ],
            ], 201);
        });
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $order = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->where(function ($sub) use ($id) {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->with(['items.produk', 'latestPembayaran'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail pesanan berhasil dimuat.',
            'data' => $order,
        ]);
    }

    public function track(string $orderCode): JsonResponse
    {
        $order = Pesanan::query()
            ->where('order_code', $orderCode)
            ->with(['items', 'latestPembayaran'])
            ->first();

        if (!$order) {
            return response()->json([
                'message' => "Pesanan dengan kode {$orderCode} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'message' => 'Informasi pelacakan pesanan berhasil ditemukan.',
            'data' => [
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name,
                'customer_phone' => substr($order->customer_phone, 0, 4) . '****' . substr($order->customer_phone, -3),
                'shipping_city' => $order->shipping_city,
                'shipping_courier' => $order->shipping_courier,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'payment_channel' => $order->payment_channel,
                'total_amount' => $order->total_amount,
                'items' => $order->items,
                'payment' => $order->latestPembayaran,
                'created_at' => $order->created_at,
            ],
        ]);
    }
}
