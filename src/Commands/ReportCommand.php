<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Pkdev\Reporter\Reporters\HeartbeatReporter;
use Pkdev\Reporter\Support\Enroller;
use Pkdev\Reporter\Support\SiteKey;

class ReportCommand extends Command
{
    protected $signature = 'pkdev:report';

    protected $description = 'Send a heartbeat and queue snapshot to the PKDev control plane.';

    public function handle(HeartbeatReporter $reporter, Enroller $enroller): int
    {
        if (!config('pkdev-reporter.enabled')) { return self::SUCCESS; }

        try {
            $key = SiteKey::ensure();
        } catch (\Throwable $exception) {
            Log::warning('[pkdev/reporter] Could not create the site key file: '.$exception->getMessage());

            return self::SUCCESS;
        }

        $status = $reporter->send();

        if ($status === 401 && SiteKey::fromFile()) {
            $enroller->enroll($key);

            return self::SUCCESS;
        }

        if ($status !== null && $status !== 403 && ($status < 200 || $status >= 300)) {
            Log::warning('[pkdev/reporter] /api/v1/heartbeat answered '.$status);
        }

        return self::SUCCESS;
    }
}
