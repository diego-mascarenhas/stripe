<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WHM API Credentials
    |--------------------------------------------------------------------------
    |
    | Credenciales del usuario reseller de WHM. El servidor específico
    | se obtiene desde la metadata de cada suscripción.
    |
    */

    'username' => env('WHM_USERNAME'),
    'password' => env('WHM_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Default Server
    |--------------------------------------------------------------------------
    |
    | Servidor por defecto para la creación de nuevas cuentas.
    |
    */

    'default_server' => env('WHM_DEFAULT_SERVER', 'muninn.revisionalpha.cloud'),

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */

    'api_port' => env('WHM_API_PORT', 2087),
    'verify_ssl' => env('WHM_VERIFY_SSL', true),
    'timeout' => env('WHM_TIMEOUT', 30),
];

