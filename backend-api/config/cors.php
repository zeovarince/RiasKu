<?php

$configuredOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$frontendUrl = rtrim(trim((string) env('FRONTEND_URL', '')), '/');
if ($frontendUrl !== '' && ! in_array($frontendUrl, $configuredOrigins, true)) {
    $configuredOrigins[] = $frontendUrl;
}

$isProduction = env('APP_ENV', 'local') === 'production';
$localDefaults = $isProduction ? [] : [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_merge($configuredOrigins, $localDefaults))),
    'allowed_origins_patterns' => $isProduction ? [] : ['#^http://(localhost|127\.0\.0\.1):(5173|5174)$#'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
