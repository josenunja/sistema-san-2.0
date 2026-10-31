<?php

return [
    'single' => env('BUSINESS_MODE', 'platform') === 'single',
    'slug' => env('BUSINESS_SLUG', ''),
    'name' => env('BUSINESS_NAME', ''),
    'type' => env('BUSINESS_TYPE', 'general'),
    'internal_documents_only' => (bool) env('BUSINESS_INTERNAL_DOCUMENTS_ONLY', true),
    'limits' => [
        'product' => (int) env('BUSINESS_PRODUCT_LIMIT', 5000),
        'warehouse' => (int) env('BUSINESS_WAREHOUSE_LIMIT', 10),
        'user' => (int) env('BUSINESS_USER_LIMIT', 25),
    ],
];
