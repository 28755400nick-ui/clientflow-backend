<?php

/*
|--------------------------------------------------------------------------
| CORS — Producción segura
|--------------------------------------------------------------------------
| CORS_ALLOWED_ORIGINS en .env acepta múltiples orígenes separados por coma:
|   CORS_ALLOWED_ORIGINS=https://cascavel.site,https://www.cascavel.site
|
| En desarrollo usa: CORS_ALLOWED_ORIGINS=http://localhost:3000
|--------------------------------------------------------------------------
*/

$rawOrigins = env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000');

$allowedOrigins = array_map(
    'trim',
    explode(',', $rawOrigins)
);

return [
    'paths'                    => ['api/*'],

    'allowed_methods'          => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins'          => $allowedOrigins,

    'allowed_origins_patterns' => [],

    // Solo los headers necesarios — nunca '*' en producción
    'allowed_headers'          => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],

    // Exponer el header de paginación si se usa Link header
    'exposed_headers'          => ['X-Total-Count'],

    // 1 hora de preflight cache
    'max_age'                  => 3600,

    'supports_credentials'     => false,
];
