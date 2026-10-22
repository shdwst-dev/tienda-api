<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    |
    | Configuración de los tokens que emite el login (firmados con HS256).
    | "secret" debe tener al menos 32 caracteres: genera uno con
    | `openssl rand -hex 32`. "ttl" son los minutos de vida de cada token.
    |
    */

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL', 30),

];
