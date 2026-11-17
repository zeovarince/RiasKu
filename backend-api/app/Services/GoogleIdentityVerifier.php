<?php

namespace App\Services;

use Google\Client;
use Throwable;

class GoogleIdentityVerifier
{
    public function verify(string $credential): ?array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return null;
        }

        try {
            $client = new Client(['client_id' => $clientId]);
            $claims = $client->verifyIdToken($credential);

            return is_array($claims) ? $claims : null;
        } catch (Throwable) {
            return null;
        }
    }
}
