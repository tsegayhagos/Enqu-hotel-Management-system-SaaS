<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central domain
    |--------------------------------------------------------------------------
    | When set (e.g. "enku.test"), a request to "grand.enku.test" resolves the
    | hotel whose slug is "grand". Leave null to disable sub-domain resolution.
    */
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN'),

    /*
    | Header that API / mobile clients (Sanctum tokens) use to pick a hotel.
    */
    'api_header' => 'X-Hotel-Id',

];
