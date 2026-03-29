<?php

return [
    'hostname'   => env('CAS_HOSTNAME', 'sso.upi.edu'),
    'port'       => env('CAS_PORT', 443),
    'uri'        => env('CAS_URI', '/cas'),
    'version'    => env('CAS_VERSION', '3.0'),
    'logout_url' => env('CAS_LOGOUT_URL', 'https://sso.upi.edu/cas/logout'),
];
