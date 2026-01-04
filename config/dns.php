<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DNS Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the expected DNS settings for different services.
    | You can add multiple services with their own nameservers and IPs.
    |
    */

    'services' => [
        'whm' => [
            'nameservers' => array_filter(explode(',', env('DNS_WHM_NAMESERVERS', 'ns1.revisionalpha.com,ns2.revisionalpha.com'))),
            'valid_ips' => array_filter(explode(',', env('DNS_WHM_VALID_IPS', '51.83.76.40,51.195.217.63,66.70.189.5'))),
            'spf_include' => env('DNS_WHM_SPF_INCLUDE', 'spf.revisionalpha.com'),
        ],

        // Example: Add more services as needed
        // 'vps' => [
        //     'nameservers' => array_filter(explode(',', env('DNS_VPS_NAMESERVERS', ''))),
        //     'valid_ips' => array_filter(explode(',', env('DNS_VPS_VALID_IPS', ''))),
        //     'spf_include' => env('DNS_VPS_SPF_INCLUDE', ''),
        // ],
        
        // 'mailer' => [
        //     'nameservers' => array_filter(explode(',', env('DNS_MAILER_NAMESERVERS', ''))),
        //     'valid_ips' => array_filter(explode(',', env('DNS_MAILER_VALID_IPS', ''))),
        //     'spf_include' => env('DNS_MAILER_SPF_INCLUDE', ''),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Service
    |--------------------------------------------------------------------------
    |
    | The default service to use for DNS validation if none is specified.
    |
    */

    'default_service' => env('DNS_DEFAULT_SERVICE', 'whm'),
];

