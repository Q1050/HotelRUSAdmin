<?php

return [
    'number' => env('APP_VERSION', is_file(base_path('VERSION')) ? trim(file_get_contents(base_path('VERSION'))) : '1.0.0'),
    'name' => env('APP_RELEASE_NAME', 'Accounting Exports'),
];
