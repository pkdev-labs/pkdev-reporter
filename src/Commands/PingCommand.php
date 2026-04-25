<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Pkdev\Reporter\Http\PkdevClient;

class PingCommand extends Command
{
    protected $signature = 'pkdev:ping';

    protected $description = 'Send a test ping to PKDev and verify connectivity.';

    public function handle(PkdevClient $client): int
    {
        if (!config('pkdev-reporter.api_url')) {
            $this->error('PKDEV_API_URL is not set.');
            return self::FAILURE;
        }

        if (!config('pkdev-reporter.site_key')) {
            $this->error('PKDEV_SITE_KEY is not set.');
            return self::FAILURE;
        }

        $this->info('Pinging PKDev at '.config('pkdev-reporter.api_url').' ...');

        $ok = $client->post('/api/v1/heartbeat', [
            'reported_at' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'queues' => [],
            'horizon' => null,
        ]);

        if ($ok) {
            $this->info('PKDev responded OK. Reporter is connected.');
            return self::SUCCESS;
        }

        $this->error('PKDev did not respond or returned an error. Check your PKDEV_API_URL and PKDEV_SITE_KEY.');
        return self::FAILURE;
    }
}
