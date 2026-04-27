<?php

namespace Pkdev\Reporter\Reporters;

use Illuminate\Support\Facades\Cache;
use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\Redactor;

class ExceptionReporter
{
    public function __construct(private readonly PkdevClient $client) {}

    public function report(\Throwable $exception): void
    {
        if (!$this->shouldReport()) {
            return;
        }

        // Don't report exceptions thrown by this package — would cause infinite loops
        if (str_starts_with(get_class($exception), 'Pkdev\\Reporter\\')) {
            return;
        }

        // Suppress duplicate reports of the same exception for 60 seconds, scoped to this app
        $key = 'pkdev:exc:'.md5(config('app.name').get_class($exception).$exception->getFile().$exception->getLine());
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, 60);

        try {
            $this->client->post('/api/v1/exception', [
                'class'       => get_class($exception),
                'message'     => Redactor::clean($exception->getMessage()),
                'file'        => $exception->getFile(),
                'line'        => $exception->getLine(),
                'trace'       => Redactor::clean(mb_substr($exception->getTraceAsString(), 0, 5000)),
                'occurred_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Never break the host app
        }
    }

    private function shouldReport(): bool
    {
        return config('pkdev-reporter.enabled', true)
            && config('pkdev-reporter.features.exceptions', true)
            && !empty(config('pkdev-reporter.api_url'))
            && !empty(config('pkdev-reporter.site_key'));
    }
}
