<?php

return [
    'single' => env('BUSINESS_MODE', 'platform') === 'single',
    'slug' => env('BUSINESS_SLUG', ''),
    'name' => env('BUSINESS_NAME', ''),
    'type' => env('BUSINESS_TYPE', 'general'),
    'limits' => [
        'product' => (int) env('BUSINESS_PRODUCT_LIMIT', 5000),
        'warehouse' => (int) env('BUSINESS_WAREHOUSE_LIMIT', 10),
        'user' => (int) env('BUSINESS_USER_LIMIT', 25),
    ],
];
