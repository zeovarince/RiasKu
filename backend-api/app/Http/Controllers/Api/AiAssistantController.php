<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Services\AiAssistantService;
use App\Services\AiCatalogContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiAssistantService $assistantService,
        protected AiCatalogContextService $catalogContext,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'message' => 'Status penyedia AI berhasil dimuat.',
            'data' => $this->assistantService->getProviderStatus(),
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'context_product_id' => ['nullable', 'integer', 'exists:produk,id'],
        ], [
            'message.required' => 'Pertanyaan atau pesan wajib diisi.',
            'message.min' => 'Pesan minimal terdiri dari 2 karakter.',
            'message.max' => 'Pesan maksimal 1000 karakter.',
            'session_token.uuid' => 'Format token sesi percakapan tidak valid.',
        ]);

        $customer = $this->resolveOptionalCustomer();

        $result = $this->assistantService->chat(
            message: $validated['message'],
            sessionToken: $validated['session_token'] ?? null,
            customer: $customer,
            contextProductId: isset($validated['context_product_id']) ? (int) $validated['context_product_id'] : null,
        );

        return response()->json([
            'message' => 'Respons asisten AI berhasil dibuat.',
            'data' => $result,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['nullable', 'uuid'],
        ]);

        $customer = $this->resolveOptionalCustomer();
        $result = $this->assistantService->getHistory(
            sessionToken: $validated['session_token'] ?? null,
            customer: $customer,
        );

        return response()->json([
            'message' => 'Riwayat percakapan asisten AI berhasil dimuat.',
            'data' => $result,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $rawTokens = $request->input('tokens', []);
        if (is_string($rawTokens)) {
            $rawTokens = array_filter(array_map('trim', explode(',', $rawTokens)));
        }

        $guestTokens = is_array($rawTokens)
            ? collect($rawTokens)
                ->filter(fn ($t): bool => is_string($t) && Str::isUuid($t))
                ->unique()
                ->take(30)
                ->values()
                ->all()
            : [];

        $rawActive = $request->query('active_token');
        $activeToken = is_string($rawActive) && Str::isUuid($rawActive) ? $rawActive : null;

        $customer = $this->resolveOptionalCustomer();
        $sessions = $this->assistantService->listSessions($customer, $guestTokens, $activeToken);

        return response()->json([
            'message' => 'Daftar sesi percakapan asisten AI berhasil dimuat.',
            'data' => $sessions,
        ]);
    }

    public function renameSession(Request $request, string $sessionToken): JsonResponse
    {
        if (! Str::isUuid($sessionToken)) {
            abort(422, 'Format token sesi percakapan tidak valid.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:120'],
        ], [
            'title.required' => 'Judul percakapan wajib diisi.',
            'title.max' => 'Judul percakapan maksimal 120 karakter.',
        ]);

        $customer = $this->resolveOptionalCustomer();
        $updated = $this->assistantService->renameSession($sessionToken, $validated['title'], $customer);

        if ($updated === null) {
            return response()->json([
                'message' => 'Sesi percakapan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Judul sesi percakapan berhasil diperbarui.',
            'data' => $updated,
        ]);
    }

    public function deleteSession(string $sessionToken): JsonResponse
    {
        if (! Str::isUuid($sessionToken)) {
            abort(422, 'Format token sesi percakapan tidak valid.');
        }

        $customer = $this->resolveOptionalCustomer();
        $deleted = $this->assistantService->deleteSession($sessionToken, $customer);

        if (! $deleted) {
            return response()->json([
                'message' => 'Sesi percakapan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Sesi percakapan berhasil dihapus.',
            'data' => [
                'deleted' => true,
                'session_token' => $sessionToken,
            ],
        ]);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $rawToken = $request->input('session_token') ?? $request->query('session_token');
        if ($rawToken !== null) {
            $request->merge(['session_token' => $rawToken]);
            $request->validate([
                'session_token' => ['required', 'uuid'],
            ]);
        }

        $customer = $this->resolveOptionalCustomer();
        $deleted = $this->assistantService->clearHistory(
            sessionToken: $rawToken ? (string) $rawToken : null,
            customer: $customer,
        );

        return response()->json([
            'message' => $deleted
                ? 'Percakapan asisten AI berhasil direset.'
                : 'Tidak ada sesi percakapan aktif untuk dihapus.',
            'data' => [
                'cleared' => $deleted,
            ],
        ]);
    }

    public function suggestions(): JsonResponse
    {
        return response()->json([
            'message' => 'Daftar saran pertanyaan asisten AI berhasil dimuat.',
            'data' => $this->catalogContext->getDynamicSuggestions(),
        ]);
    }

    private function resolveOptionalCustomer(): ?Pelanggan
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof Pelanggan ? $user : null;
    }
}
