<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Pkdev\Reporter\Http\PkdevClient;

class NotifyDeploymentCommand extends Command
{
    protected $signature = 'pkdev:notify-deployment
                            {--status=completed : Deployment status (completed|failed)}
                            {--branch= : Branch deployed}
                            {--commit= : Short commit SHA}
                            {--commit-message= : Commit message}
                            {--deployer= : Name of deployer}
                            {--environment= : Deployment environment}
                            {--duration-ms= : Deployment duration in milliseconds}';

    protected $description = 'Notify the PKDev control plane of a deployment event.';

    public function handle(PkdevClient $client): int
    {
        if (! config('pkdev-reporter.enabled')) {
            return self::SUCCESS;
        }

        $client->post('/api/v1/deployment', [
            'status'         => $this->option('status'),
            'branch'         => $this->option('branch'),
            'commit'         => $this->option('commit') ?: null,
            'commit_message' => $this->option('commit-message') ?: null,
            'deployer'       => $this->option('deployer') ?: null,
            'environment'    => $this->option('environment') ?: config('app.env'),
            'duration_ms'    => $this->option('duration-ms') ? (int) $this->option('duration-ms') : null,
            'deployed_at'    => now()->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
