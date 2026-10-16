<?php

return [
    'driver' => env('GAME_PROVIDER_DRIVER', 'demo'),

    'session_ttl_minutes' => (int) env('GAME_SESSION_TTL', 240),

    'currency' => env('GAME_CURRENCY', 'PHP'),

    'language' => env('GAME_LANGUAGE', 'en'),

    'lobby_url' => env('GAME_LOBBY_URL', env('FRONTEND_URL', 'http://localhost:3000') . '/games'),

    'drivers' => [
        'demo' => [
            'name' => 'ManpakBet Demo',
        ],

        'aggregator' => [
            'name' => env('GAME_AGGREGATOR_NAME', 'Game Aggregator'),
            'base_url' => env('GAME_AGGREGATOR_URL'),
            'launch_path' => env('GAME_AGGREGATOR_LAUNCH_PATH', '/game/launch'),
            'operator_id' => env('GAME_AGGREGATOR_OPERATOR_ID'),
            'secret' => env('GAME_AGGREGATOR_SECRET'),
            'launch_url_field' => env('GAME_AGGREGATOR_URL_FIELD', 'url'),
            'timeout' => (int) env('GAME_AGGREGATOR_TIMEOUT', 15),
            'signature_header' => env('GAME_AGGREGATOR_SIGNATURE_HEADER', 'X-Signature'),
            'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('GAME_AGGREGATOR_ALLOWED_IPS', ''))))),
        ],
    ],
];
