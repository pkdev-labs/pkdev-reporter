<?php

return [
    'enabled' => env('PKDEV_ENABLED', env('APP_ENV') === 'production'),

    'api_url' => env('PKDEV_API_URL', 'https://app.pkdev.dev'),

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
     * Exception reports only. In a web request they are sent after the response.
     */
    'exception_timeout' => 2,
    'exception_retries' => 1,

    /*
     * Feature toggles — disable individual features without removing the package.
     */
    'features' => [
        'heartbeat' => env('PKDEV_FEATURE_HEARTBEAT', true),
        'cron' => env('PKDEV_FEATURE_CRON', true),
        'exceptions' => env('PKDEV_FEATURE_EXCEPTIONS', true),
    ],
];
