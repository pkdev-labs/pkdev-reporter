<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Pkdev\Reporter\Http\PkdevClient;

class TestCommand extends Command
{
    protected $signature = 'pkdev:test';

    protected $description = 'Send a test payload through every PKDev pipeline and verify each one is received.';

    public function handle(PkdevClient $client): int
    {
        $apiUrl  = config('pkdev-reporter.api_url');
        $siteKey = config('pkdev-reporter.site_key');

        $this->line('');
        $this->line('<fg=cyan>PKDev Reporter — Connection Test</>');
        $this->line('<fg=gray>Hub: '.($apiUrl ?: '<none>').'</>');
        $this->line('<fg=gray>Key: '.($siteKey ? '···'.substr($siteKey, -4) : '<none>').'</>');
        $this->line('');

        if (!$apiUrl || !$siteKey) {
            $this->line('  <fg=red>✗ Config missing — run pkdev:install first</>');
            $this->line('');
            return self::FAILURE;
        }

        $checks = [
            'Heartbeat'        => fn () => $client->post('/api/v1/heartbeat', [
                'reported_at' => now()->utc()->toIso8601String(),
                'environment' => config('app.env'),
                'queues'      => [],
                'horizon'     => null,
            ]),
            'Cron ping'        => fn () => $client->post('/api/v1/cron-ping', [
                'command_name' => 'artisan pkdev:test',
                'status'       => 'success',
                'exit_code'    => 0,
                'duration_ms'  => 1,
                'fired_at'     => now()->utc()->toIso8601String(),
            ]),
            'Exception report' => fn () => $client->post('/api/v1/exception', [
                'class'       => 'Pkdev\Reporter\TestException',
                'message'     => 'PKDev test — ignore this',
                'file'        => 'pkdev:test',
                'line'        => 0,
                'trace'       => null,
                'occurred_at' => now()->utc()->toIso8601String(),
            ]),
            'Deployment'       => fn () => $client->post('/api/v1/deployment', [
                'branch'         => 'test',
                'commit'         => null,
                'commit_message' => 'PKDev test — ignore this',
                'deployer'       => 'pkdev:test',
                'environment'    => config('app.env'),
                'status'         => 'completed',
                'duration_ms'    => 1,
                'deployed_at'    => now()->utc()->toIso8601String(),
            ]),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $label => $check) {
            $ok = $check();
            $pad = str_pad($label, 18);

            if ($ok) {
                $this->line("  <fg=green>✓</> {$pad}<fg=gray>→ hub received</>");
                $passed++;
            } else {
                $this->line("  <fg=red>✗</> {$pad}<fg=red>→ no response or error</>");
                $failed++;
            }
        }

        $this->line('');

        if ($failed === 0) {
            $this->line("<fg=green>All {$passed} checks passed. Reporter is fully operational.</>");
        } else {
            $this->line("<fg=red>{$failed} check(s) failed.</> Check your hub logs for details.");
        }

        $this->line('');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
