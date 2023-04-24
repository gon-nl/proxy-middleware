<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable or disable TRUSTED mode
    |--------------------------------------------------------------------------
    |
    | When you don't know the IP address of the proxy server, you can enable
    | TRUSTED mode to trust all proxies. This is not recommended, but it can
    | be useful in some situations. Make sure the server can only be reached
    | by a trusted load balancer or reverse proxy if you enable this option!
    */

    'trust-all' => (bool) env('TRUSTED_PLATFORM', false),
];