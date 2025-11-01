<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CBU API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Central Bank of Uzbekistan API
    |
    */
    'base_url' => env('CBU_BASE_URL', 'https://cbu.uz/ru/arkhiv-kursov-valyut/json'),

    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long to cache the currency rates (in minutes)
    |
    */
    'cache_duration' => env('CBU_CACHE_DURATION', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code to use
    |
    */
    'default_currency' => env('CBU_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Calculation Scale
    |--------------------------------------------------------------------------
    |
    | The number of decimal places for BCMath calculations
    |
    */
    'scale' => env('CBU_SCALE', 2),

    /*
    |--------------------------------------------------------------------------
    | Data Source
    |--------------------------------------------------------------------------
    |
    | Determines where to fetch currency rates from:
    | - 'database': Fetch rates from local database (default, faster)
    | - 'api': Fetch rates directly from CBU API (live data, slower)
    |
    | You can also change source dynamically using:
    | CbuCurrency::source('api')->getRate('USD')
    | CbuCurrency::source(SourceType::DATABASE)->convert('USD', 'EUR', 100)
    |
    */
    'source' => env('CBU_SOURCE', 'database'),
];
