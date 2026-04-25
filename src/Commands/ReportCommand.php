<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Pkdev\Reporter\Reporters\HeartbeatReporter;

class ReportCommand extends Command
{
    protected $signature = 'pkdev:report';

    protected $description = 'Send a heartbeat and queue snapshot to the PKDev control plane.';

    public function handle(HeartbeatReporter $reporter): int
    {
        if (!config('pkdev-reporter.enabled')) {
            return self::SUCCESS;
        }

        $reporter->report();

        return self::SUCCESS;
    }
}
