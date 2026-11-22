<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Keranjang;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Services\MidtransService;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        protected MidtransService $midtransService,
        protected OrderStatusService $orderStatusService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->with(OrderStatusService::orderRelations(false))
            ->with('statusLogs')
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where('order_code', 'like', "%{$search}%");
        }

        if ($status = trim((string) $request->query('status'))) {
            if ($status !== 'all' && $status !== 'Semua') {
                $normalized = strtolower($status);
                $query->where(function ($sub) use ($normalized): void {
                    $sub->where('order_status', $normalized)
                        ->orWhere(function ($legacy) use ($normalized): void {
                            $legacy->whereNull('order_status')
                                ->where('status', $normalized === 'menunggu_pembayaran' ? 'pending' : $normalized);
                        });
                });
            }
        }

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'message' => 'Daftar pesanan berhasil dimuat.',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $orders = $query->get();

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
            if (! str_contains($city, 'surabaya')) {
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
            if (! $cart || $cart->items->isEmpty()) {
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

        [$order, $channel, $isCod] = DB::transaction(function () use ($validated, $user, $orderItemsData): array {
            $totalProductAmount = 0;
            $preparedItems = [];

            $indexedItems = [];
            foreach (array_values($orderItemsData) as $origIndex => $itemData) {
                $indexedItems[] = ['index' => $origIndex, 'item' => $itemData];
            }

            // Urutkan deterministik sebelum lockForUpdate agar urutan item berbeda tidak memicu deadlock MySQL.
            usort($indexedItems, static function (array $a, array $b): int {
                $prodCmp = ((int) $a['item']['produk_id']) <=> ((int) $b['item']['produk_id']);
                if ($prodCmp !== 0) {
                    return $prodCmp;
                }

                return ((int) ($a['item']['produk_varian_id'] ?? 0)) <=> ((int) ($b['item']['produk_varian_id'] ?? 0));
            });

            foreach ($indexedItems as $entry) {
                $origIndex = $entry['index'];
                $itemData = $entry['item'];
                $product = Produk::query()->whereKey($itemData['produk_id'])->lockForUpdate()->firstOrFail();

                if (! $product->is_active) {
                    abort(422, "Produk {$product->name} saat ini sedang dinonaktifkan.");
                }

                $variantId = $itemData['produk_varian_id'] ?? null;
                $variant = null;

                if ($variantId) {
                    $variant = ProdukVarian::query()
                        ->whereKey($variantId)
                        ->where('produk_id', $product->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();
                    if (! $variant) {
                        abort(422, 'Varian produk tidak ditemukan.');
                    }
                } else {
                    $variant = ProdukVarian::query()
                        ->where('produk_id', $product->id)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();
                    $variantId = $variant?->id;
                }

                $qty = max(1, (int) ($itemData['quantity'] ?? 1));
                $availableStock = $variant ? (int) $variant->stock : (int) $product->total_stock;
                $isLinePreorder = $availableStock <= 0 && (bool) $product->is_preorder;

                if ($isLinePreorder) {
                    if ($qty > 99) {
                        abort(422, "Jumlah Pre-Order untuk produk {$product->name} maksimal 99 pcs.");
                    }
                    $stockDeducted = false;
                } else {
                    if ($qty > $availableStock) {
                        abort(422, "Stok untuk produk {$product->name} tidak mencukupi (sisa {$availableStock}).");
                    }
                    if ($variant) {
                        $variant->decrement('stock', $qty);
                    }
                    $stockDeducted = true;
                }

                $price = (int) $product->retail_price;
                $lineSubtotal = $price * $qty;
                $totalProductAmount += $lineSubtotal;

                $preparedItems[$origIndex] = [
                    'produk_id' => $product->id,
                    'produk_varian_id' => $variantId,
                    'product_name' => $product->name,
                    'variant_name' => $variant ? $variant->name : null,
                    'product_sku' => $product->sku,
                    'unit_price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $lineSubtotal,
                    'is_preorder' => $isLinePreorder,
                    'preorder_estimate_days' => $isLinePreorder ? $product->preorder_estimate_days : null,
                    'stock_deducted' => $stockDeducted,
                ];
            }

            ksort($preparedItems);
            $preparedItems = array_values($preparedItems);

            $hasPreorderItems = collect($preparedItems)->contains(fn (array $item): bool => (bool) $item['is_preorder']);
            $preorderEstimateDays = $hasPreorderItems
                ? (int) collect($preparedItems)->filter(fn (array $item): bool => (bool) $item['is_preorder'])->max('preorder_estimate_days')
                : null;

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
                'has_preorder_items' => $hasPreorderItems,
                'preorder_estimate_days' => $preorderEstimateDays ?: null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($preparedItems as $item) {
                $order->items()->create($item);
            }

            $this->orderStatusService->recordInitialLog(
                $order,
                'pelanggan',
                $user->id,
                $validated['customer_name'],
                $isCod ? 'Pesanan COD Surabaya dibuat dan sedang diproses.' : 'Pesanan dibuat, menunggu pembayaran.',
            );

            // Bersihkan keranjang belanja
            $cart = Keranjang::where('pelanggan_id', $user->id)->first();
            if ($cart) {
                $cart->items()->delete();
            }

            return [$order, $channel, $isCod];
        }, 3);

        // Inisiasi Pembayaran (PPL-19 Midtrans Dual-Mode / COD) di luar DB::transaction agar lock tidak tertahan HTTP eksternal
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

        $order->load(OrderStatusService::orderRelations(true));

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
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $order = Pesanan::query()
            ->where('pelanggan_id', $request->user()->id)
            ->where(function ($sub) use ($id): void {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->with(OrderStatusService::orderRelations(true))
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail pesanan berhasil dimuat.',
            'data' => $order,
        ]);
    }

    public function complete(Request $request, int|string $id): JsonResponse
    {
        $customer = $request->user();
        $order = Pesanan::query()
            ->where('pelanggan_id', $customer->id)
            ->where(function ($sub) use ($id): void {
                if (is_numeric($id)) {
                    $sub->where('id', (int) $id)->orWhere('order_code', (string) $id);
                } else {
                    $sub->where('order_code', (string) $id);
                }
            })
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        if ($order->resolvedOrderStatus() !== 'dikirim') {
            return response()->json([
                'message' => 'Konfirmasi pesanan selesai hanya dapat dilakukan saat pesanan sedang dikirim.',
            ], 422);
        }

        if ($order->isCodOrder() && ! in_array($order->payment_status, ['dibayar', 'lunas', 'settlement'], true)) {
            return response()->json([
                'message' => 'Pesanan COD belum dikonfirmasi pembayarannya oleh Admin.',
            ], 422);
        }

        $updated = $this->orderStatusService->transition($order, 'selesai', [
            'actor_type' => 'pelanggan',
            'actor_id' => $customer->id,
            'actor_name' => $customer->username ?? $order->customer_name ?? 'Pelanggan',
            'note' => 'Pelanggan mengonfirmasi pesanan telah diterima dengan baik.',
        ]);

        return response()->json([
            'message' => "Terima kasih! Pesanan {$updated->order_code} telah selesai.",
            'data' => $updated,
        ]);
    }

    public function track(string $orderCode): JsonResponse
    {
        $code = trim($orderCode);

        $order = Pesanan::query()
            ->where('order_code', $code)
            ->with(['items', 'latestPembayaran', 'statusLogs'])
            ->first();

        if (! $order) {
            return response()->json([
                'message' => "Pesanan dengan kode {$code} tidak ditemukan.",
            ], 404);
        }

        $payment = $order->latestPembayaran;
        $safePayment = $payment ? [
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'payment_type' => $payment->payment_type,
            'bank' => $payment->bank,
            'va_number' => $payment->va_number,
            'bill_key' => $payment->bill_key,
            'biller_code' => $payment->biller_code,
            'qr_string' => $payment->qr_string,
            'qr_url' => $payment->qr_url,
            'deeplink_url' => $payment->deeplink_url,
            'gross_amount' => $payment->gross_amount,
            'transaction_status' => $payment->transaction_status,
            'paid_at' => $payment->paid_at,
            'expiry_time' => $payment->expiry_time,
        ] : null;

        $publicItems = $order->items->map(fn ($item): array => [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'unit_price' => (int) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'subtotal' => (int) ($item->subtotal ?? $item->line_total ?? 0),
            'line_total' => (int) ($item->line_total ?? $item->subtotal ?? 0),
            'is_preorder' => (bool) $item->is_preorder,
            'preorder_estimate_days' => $item->preorder_estimate_days,
        ])->values();

        $publicLogs = $order->statusLogs->map(fn ($log): array => [
            'id' => $log->id,
            'from_status' => $log->from_status,
            'to_status' => $log->to_status,
            'actor_type' => $log->actor_type,
            'tracking_number' => $log->tracking_number,
            'note' => $log->note,
            'created_at' => $log->created_at,
        ])->values();

        return response()->json([
            'message' => 'Informasi pelacakan pesanan berhasil ditemukan.',
            'data' => [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name ?: $order->recipient_name,
                'customer_phone' => Pesanan::maskPhone($order->customer_phone ?: $order->recipient_phone),
                'shipping_city' => $order->shipping_city ?: $order->destination_city,
                'shipping_district' => $order->shipping_district ?: $order->destination_district,
                'shipping_courier' => $order->shipping_courier ?: trim((string) ($order->courier.' '.($order->courier_service ?? ''))),
                'shipping_etd' => $order->shipping_etd,
                'tracking_number' => $order->tracking_number,
                'order_status' => $order->resolvedOrderStatus(),
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'payment_channel' => $order->payment_channel,
                'total_product_amount' => $order->total_product_amount ?? $order->subtotal ?? 0,
                'shipping_cost' => $order->shipping_cost ?? 0,
                'total_amount' => $order->total_amount ?? $order->total ?? 0,
                'has_preorder_items' => (bool) $order->has_preorder_items,
                'preorder_estimate_days' => $order->preorder_estimate_days,
                'items' => $publicItems,
                'payment' => $safePayment,
                'status_logs' => $publicLogs,
                'created_at' => $order->created_at,
            ],
        ]);
    }
}
