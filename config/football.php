<?php
return [
    'provider' => env('FOOTBALL_PROVIDER', 'api-football'),
    'highlightly_key' => env('HIGHLIGHTLY_API_KEY', ''),
    'api_football_key' => env('FOOTBALL_API_KEY', ''),
    'season' => env('FOOTBALL_SEASON'),
    'daily_limit' => (int) env('FOOTBALL_DAILY_LIMIT', 90),
    'spacing_ms' => 6500,
];
