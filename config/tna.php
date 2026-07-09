<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kasalukuyang TNA Period
    |--------------------------------------------------------------------------
    | Ang TNA ay isinasagawa kada 3 taon. Palitan lang ito (o ang .env)
    | pagdating ng susunod na cycle (hal. 2029-2031).
    */
    'period' => env('TNA_PERIOD', '2026-2028'),
];