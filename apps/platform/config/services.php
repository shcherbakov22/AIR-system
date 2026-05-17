<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

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

    'edge_clients' => [
        'shared_token' => env('EDGE_CLIENT_SHARED_TOKEN', 'dev-edge-token'),
    ],

    'local_tls' => [
        'root_ca_path' => env(
            'LOCAL_TLS_ROOT_CA_PATH',
            '/var/lib/caddy/.local/share/caddy/pki/authorities/local/root.crt'
        ),
        'root_ca_route' => env('LOCAL_TLS_ROOT_CA_ROUTE', '/companion/root-ca.crt'),
    ],

    'companion_updates' => [
        'enabled' => env('COMPANION_UPDATES_ENABLED', true),
        'channel' => env('COMPANION_UPDATES_CHANNEL', 'stable'),
        'version' => env('COMPANION_UPDATES_VERSION', '0.1.23'),
        'windows_package_path' => env(
            'COMPANION_WINDOWS_UPDATE_PACKAGE_PATH',
            storage_path('app/companion-updates/air-companion-windows.zip')
        ),
        'windows_installer_bundle_path' => env(
            'COMPANION_WINDOWS_INSTALLER_BUNDLE_PATH',
            storage_path('app/companion-updates/air-companion-windows-installer.zip')
        ),
        'browser_extension_bundle_path' => env(
            'COMPANION_BROWSER_EXTENSION_BUNDLE_PATH',
            storage_path('app/companion-updates/air-look-extension.zip')
        ),
    ],

    'ai_overseer' => [
        'enabled' => env('AI_OVERSEER_ENABLED', true),
        'provider' => env('AI_OVERSEER_PROVIDER', 'openrouter'),
        'model' => env('AI_OVERSEER_MODEL', 'openai/gpt-oss-120b'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'confidence_threshold' => (int) env('AI_OVERSEER_CONFIDENCE_THRESHOLD', 75),
        'auto_apply_skip' => env('AI_OVERSEER_AUTO_APPLY_SKIP', true),
        'prompt_version' => env('AI_OVERSEER_PROMPT_VERSION', 'ai-overseer-v1'),
    ],

];
