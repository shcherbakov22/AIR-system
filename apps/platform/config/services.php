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

    'network_control' => [
        'enabled' => env('NETWORK_CONTROL_ENABLED', false),
        'base_url' => env('NETWORK_CONTROL_BASE_URL'),
        'token' => env('NETWORK_CONTROL_TOKEN'),
        'local_gateway_enabled' => env('LOCAL_GATEWAY_ENABLED', false),
        'gateway_server_ipv4' => env('LOCAL_GATEWAY_SERVER_IPV4', '192.168.11.228'),
        'gateway_dns_ipv4' => env('LOCAL_GATEWAY_DNS_IPV4', '192.168.11.228'),
        'gateway_nft_binary' => env('LOCAL_GATEWAY_NFT_BINARY', 'nft'),
        'gateway_table_name' => env('LOCAL_GATEWAY_TABLE_NAME', 'air_companion'),
    ],

    'local_tls' => [
        'root_ca_path' => env(
            'LOCAL_TLS_ROOT_CA_PATH',
            '/var/lib/caddy/.local/share/caddy/pki/authorities/local/root.crt'
        ),
        'root_ca_route' => env('LOCAL_TLS_ROOT_CA_ROUTE', '/companion/root-ca.crt'),
    ],

];
