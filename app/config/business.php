<?php

return [
    'single' => env('BUSINESS_MODE', 'platform') === 'single',
    'slug' => env('BUSINESS_SLUG', ''),
    'name' => env('BUSINESS_NAME', ''),
    'type' => env('BUSINESS_TYPE', 'general'),
];
