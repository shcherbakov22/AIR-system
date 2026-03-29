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

    'remote_control' => [
        'enabled' => env('REMOTE_CONTROL_ENABLED', true),
        'gateway_url' => env('REMOTE_CONTROL_GATEWAY_URL', 'http://127.0.0.1:9821'),
        'ready_heartbeat_max_age_seconds' => (int) env('REMOTE_CONTROL_READY_HEARTBEAT_MAX_AGE_SECONDS', 120),
    ],

    'companion_updates' => [
        'enabled' => env('COMPANION_UPDATES_ENABLED', true),
        'channel' => env('COMPANION_UPDATES_CHANNEL', 'stable'),
        'version' => env('COMPANION_UPDATES_VERSION', '0.1.7'),
        'windows_package_path' => env(
            'COMPANION_WINDOWS_UPDATE_PACKAGE_PATH',
            storage_path('app/companion-updates/air-companion-windows.zip')
        ),
        'windows_installer_bundle_path' => env(
            'COMPANION_WINDOWS_INSTALLER_BUNDLE_PATH',
            storage_path('app/companion-updates/air-companion-windows-installer.zip')
        ),
    ],

];
