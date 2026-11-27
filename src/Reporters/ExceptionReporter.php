<?php

namespace Pkdev\Reporter\Reporters;

use Illuminate\Support\Facades\Cache;
use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\Redactor;
use Pkdev\Reporter\Support\SiteKey;

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

        try {
            if ($this->isDuplicate($exception)) { return; }

            $this->client->post('/api/v1/exception', [
                'class' => get_class($exception),
                'message' => mb_strcut(Redactor::clean($exception->getMessage()), 0, 5000),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => Redactor::clean(mb_substr($exception->getTraceAsString(), 0, 5000)),
                'occurred_at' => now()->utc()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Never break the host app
        }
    }

    /**
     * The same exception is reported once a minute per app. A cache that fails skips the check, so the report still goes.
     */
    private function isDuplicate(\Throwable $exception): bool
    {
        $key = 'pkdev:exc:'.md5(config('app.name').get_class($exception).$exception->getFile().$exception->getLine());

        try {
            if (Cache::has($key)) { return true; }

            Cache::put($key, true, 60);
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function shouldReport(): bool
    {
        return config('pkdev-reporter.enabled', true)
            && config('pkdev-reporter.features.exceptions', true)
            && !empty(config('pkdev-reporter.api_url'))
            && SiteKey::current() !== null;
    }
}
