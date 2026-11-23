<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Harus persis sama dengan Authorized redirect URI di Google Cloud Console.
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/auth/google/callback'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'riasku_admin' => [
        'name' => env('RIASKU_ADMIN_NAME', 'Admin RiasKu'),
        'email' => env('RIASKU_ADMIN_EMAIL'),
        'password' => env('RIASKU_ADMIN_PASSWORD'),
    ],

    'midtrans' => [
        'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'base_url' => env('MIDTRANS_BASE_URL', 'https://api.sandbox.midtrans.com'),
    ],

    'ai_assistant' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'provider_chain' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_PROVIDER_CHAIN', 'gemini,groq,openrouter'))
        ))),
        'local_engine_enabled' => (bool) env('AI_LOCAL_ENGINE', true),
        'local_engine_model' => env('AI_LOCAL_MODEL', 'riasku-smart-local-v2'),
        'gemini_api_key' => env('GEMINI_API_KEY', ''),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'gemini_fallback_models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-2.5-flash-lite,gemini-2.0-flash'))
        ))),
        'groq_api_key' => env('GROQ_API_KEY', ''),
        'groq_model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        'groq_base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'openrouter_api_key' => env('OPENROUTER_API_KEY', ''),
        'openrouter_model' => env('OPENROUTER_MODEL', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
        'openrouter_base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'cooldown_seconds' => (int) env('AI_RATE_LIMIT_COOLDOWN', 60),
        'error_cooldown_seconds' => (int) env('AI_ERROR_COOLDOWN', 30),
        'timeout' => (int) env('AI_TIMEOUT', 20),
        'total_budget_seconds' => (int) env('AI_TOTAL_BUDGET', 25),
        'max_history_messages' => (int) env('AI_MAX_HISTORY', 10),
        'catalog_cache_ttl' => (int) env('AI_CATALOG_CACHE_TTL', 300),
    ],

];
