<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set trusted proxy IP addresses. Both IPv4 and IPv6 addresses are
    | supported, along with CIDR notation.
    |
    | Supported values:
    | - null / empty: Do not trust any reverse proxies (direct connection).
    | - '*': Trust all reverse proxies (e.g. behind AWS ALB, Cloudflare, Nginx).
    | - '192.168.1.1,192.168.1.2': Comma-separated list of proxy IP addresses.
    | - ['192.168.1.1', '10.0.0.0/8']: Array of proxy IP addresses or subnets.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', null),

    /*
    |--------------------------------------------------------------------------
    | Trusted Headers
    |--------------------------------------------------------------------------
    |
    | Which headers should be used to detect proxy forwarding.
    |
    */

    'headers' => Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB,

];
