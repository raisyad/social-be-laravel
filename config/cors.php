<?php

return [

    // Karena backend = API token (Bearer) & frontend Vue terpisah domain,
    // cukup expose rute API saja.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Ganti dengan origin frontend kamu (tambahkan localhost Vite kalau perlu)
    'allowed_origins' => [
        'http://localhost:5173',
        'http://back-end.localhost/',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Token mode → tidak pakai cookie session, jadi false
    'supports_credentials' => false,
];
