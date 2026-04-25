<?php

return [
    /*
     * Enable or disable all reporting. Set PKDEV_ENABLED=false to silence the package entirely.
     */
    'enabled' => env('PKDEV_ENABLED', true),

    /*
     * The PKDev hub API base URL.
     * Local dev: http://host.docker.internal:8090 (if host site is in Sail)
     * Production: https://app.pkdev.dev
     */
    'api_url' => env('PKDEV_API_URL'),

    /*
     * The Bearer token for this site, generated in the PKDev Ops panel.
     */
    'site_key' => env('PKDEV_SITE_KEY'),

    /*
     * Comma-separated list of queue names to monitor.
     * Example: "default,emails,sms"
     */
    'queues' => env('PKDEV_QUEUES', 'default'),

    /*
     * HTTP client settings.
     */
    'http_timeout' => 5,
    'http_retries' => 2,
    'http_retry_delay_ms' => 500,

    /*
     * Feature toggles — disable individual features without removing the package.
     */
    'features' => [
        'heartbeat'  => env('PKDEV_FEATURE_HEARTBEAT', true),
        'cron'       => env('PKDEV_FEATURE_CRON', true),
        'exceptions' => env('PKDEV_FEATURE_EXCEPTIONS', true),
    ],
];
