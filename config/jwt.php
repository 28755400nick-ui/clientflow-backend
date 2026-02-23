<?php

return [
    'secret'      => env('JWT_SECRET'),
    'access_ttl'  => (int) env('JWT_ACCESS_TTL', 15),   // minutos
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 7),   // días
];
