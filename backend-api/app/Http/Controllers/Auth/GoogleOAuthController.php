<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleCustomerService;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Alur OAuth 2.0 server-side (authorization code) untuk Google Sign-In.
 *
 * Alur ini dipakai karena tombol Google Identity Services berbasis iframe
 * gagal dimuat ketika browser memblokir storage pihak ketiga (Edge Tracking
 * Prevention, cookie pihak ketiga diblokir, dsb). Redirect penuh ke
 * accounts.google.com tetap berfungsi pada kondisi tersebut.
 *
 * State OAuth disimpan di cache (bukan session) agar endpoint awal dan
 * callback tidak bergantung pada cookie lintas host/port.
 */
class GoogleOAuthController extends Controller
{
    private const STATE_PREFIX = 'google-oauth-state:';

    private const LOGIN_PREFIX = 'google-login:';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public function redirect(Request $request): RedirectResponse
    {
        $mode = $request->query('mode') === 'signup' ? 'signup' : 'signin';
        $target = $this->safeRedirect($request->query('redirect'));

        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return $this->failure($mode, 'config_missing', $target);
        }

        $state = Str::random(48);
        $nonce = Str::random(48);

        Cache::put(self::STATE_PREFIX.hash('sha256', $state), [
            'nonce' => $nonce,
            'mode' => $mode,
            'redirect' => $target,
        ], now()->addMinutes(15));

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => (string) config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'hl' => 'id',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away(self::AUTHORIZE_URL.'?'.$query);
    }

    public function callback(
        Request $request,
        GoogleIdentityVerifier $verifier,
        GoogleCustomerService $customers,
    ): RedirectResponse {
        $state = $request->query('state');
        $state = is_string($state) ? $state : '';

        /** @var array{nonce?: string, mode?: string, redirect?: string|null}|null $payload */
        $payload = $state === ''
            ? null
            : Cache::pull(self::STATE_PREFIX.hash('sha256', $state));

        $mode = ($payload['mode'] ?? null) === 'signup' ? 'signup' : 'signin';
        $target = $this->safeRedirect($payload['redirect'] ?? null);

        if (! is_array($payload)) {
            return $this->failure($mode, 'state_mismatch', $target);
        }

        if (is_string($request->query('error')) && $request->query('error') !== '') {
            return $this->failure($mode, 'access_denied', $target);
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failure($mode, 'missing_code', $target);
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        $idToken = $response->json('id_token');

        if ($response->failed() || ! is_string($idToken) || $idToken === '') {
            return $this->failure($mode, 'token_exchange_failed', $target);
        }

        $identity = $verifier->verify($idToken);

        if (! $identity) {
            return $this->failure($mode, 'identity_invalid', $target);
        }

        if (! hash_equals((string) ($payload['nonce'] ?? ''), (string) ($identity['nonce'] ?? ''))) {
            return $this->failure($mode, 'identity_invalid', $target);
        }

        try {
            $customer = $customers->resolve($identity);
        } catch (HttpException $exception) {
            return $this->failure($mode, match ($exception->getStatusCode()) {
                403 => 'inactive',
                409 => 'account_conflict',
                default => 'identity_invalid',
            }, $target);
        }

        $loginCode = Str::random(64);
        Cache::put(self::LOGIN_PREFIX.hash('sha256', $loginCode), [
            'pelanggan_id' => $customer->id,
        ], now()->addMinutes(3));

        $query = array_filter([
            'code' => $loginCode,
            'mode' => $mode,
            'redirect' => $target,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return redirect()->away($this->frontendCallbackUrl().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function failure(string $mode, string $reason, ?string $redirect = null): RedirectResponse
    {
        $query = array_filter([
            'error' => $reason,
            'mode' => $mode,
            'redirect' => $redirect,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return redirect()->away($this->frontendCallbackUrl().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function frontendCallbackUrl(): string
    {
        return rtrim((string) config('services.frontend_url'), '/').'/auth/google/callback';
    }

    private function safeRedirect(mixed $redirect): ?string
    {
        if (! is_string($redirect) || $redirect === '' || ! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return null;
        }

        return $redirect;
    }
}
