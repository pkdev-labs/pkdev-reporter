<?php

namespace Pkdev\Reporter\Support;

use Pkdev\Reporter\Http\PkdevClient;

/**
 * @internal
 */
class Enroller
{
    public const MARKER = 'app/pkdev-site.enroll';

    public const RETRY_SECONDS = 3600;

    public function __construct(private readonly PkdevClient $client) {}

    public function enroll(string $key): ?int
    {
        $marker = storage_path(self::MARKER);

        if (is_file($marker) && filemtime($marker) > time() - self::RETRY_SECONDS) { return null; }

        touch($marker);

        return $this->client->send('/api/v1/enroll', [
            'url' => config('app.url'),
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'key' => $key,
        ]);
    }
}
