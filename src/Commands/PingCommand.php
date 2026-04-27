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

        $ok = $client->ping();

        if ($ok) {
            $this->info('PKDev responded OK. Reporter is connected.');
            return self::SUCCESS;
        }

        $this->warn('PKDev did not confirm the ping — check Laravel logs for details.');
        return self::SUCCESS;
    }
}
