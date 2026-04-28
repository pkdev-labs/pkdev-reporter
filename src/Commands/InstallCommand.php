<?php

namespace Pkdev\Reporter\Commands;

use Illuminate\Console\Command;
use Pkdev\Reporter\Http\PkdevClient;

class InstallCommand extends Command
{
    protected $signature = 'pkdev:install';

    protected $description = 'Configure the PKDev reporter for this site.';

    public function handle(): int
    {
        $this->line('');
        $this->line('  <fg=cyan>pk</><fg=white>dev</> reporter setup');
        $this->line('  ─────────────────────────────');
        $this->line('');

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file not found at '.$envPath);
            return self::FAILURE;
        }

        // ── Collect inputs ────────────────────────────────────────────────

        $apiUrl = $this->ask(
            'PKDev API URL',
            $this->currentEnvValue($envPath, 'PKDEV_API_URL') ?: 'http://host.docker.internal:8090'
        );

        $siteKey = $this->secret('Site API key (from the PKDev Ops panel)');

        if (!$siteKey) {
            $this->error('Site key is required.');
            return self::FAILURE;
        }

        $queues = $this->ask('Queue names to monitor (comma-separated)', 'default');

        // ── Write to .env ─────────────────────────────────────────────────

        $this->writeEnvValues($envPath, [
            'PKDEV_ENABLED'  => 'true',
            'PKDEV_API_URL'  => $apiUrl,
            'PKDEV_SITE_KEY' => $siteKey,
            'PKDEV_QUEUES'   => $queues,
        ]);

        $this->line('  <fg=green>✓</> .env updated');

        // ── Publish config if not already published ───────────────────────

        if (!file_exists(config_path('pkdev-reporter.php'))) {
            $this->callSilent('vendor:publish', ['--tag' => 'pkdev-reporter-config']);
            $this->line('  <fg=green>✓</> Config published to config/pkdev-reporter.php');
        }

        // ── Test connectivity ─────────────────────────────────────────────

        $this->line('');
        $this->line('  Testing connection...');

        // Re-boot the config so the new values are picked up in this process
        config([
            'pkdev-reporter.api_url'  => $apiUrl,
            'pkdev-reporter.site_key' => $siteKey,
            'pkdev-reporter.enabled'  => true,
        ]);

        $client = new PkdevClient();

        $ok = $client->post('/api/v1/heartbeat', [
            'reported_at' => now()->utc()->toIso8601String(),
            'environment' => config('app.env'),
            'queues'      => [],
            'horizon'     => null,
        ]);

        $this->line('');

        if ($ok) {
            $this->line('  <fg=green>✓</> Connected — PKDev is receiving data from this site');
            $this->line('');
            $this->line('  Add the scheduler to your crontab if not already running:');
            $this->line('  <fg=yellow>* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1</>');
            $this->line('');

            if ($this->confirm('Do you use Deployer for deployments?', false)) {
                $recipePath = 'vendor/pkdev/reporter/deployer/pkdev.php';
                $this->line('  Add this to your <fg=yellow>deploy.php</>:');
                $this->line('');
                $this->line("  <fg=cyan>require '{$recipePath}';</>");
                $this->line('');
                $this->line('  The recipe reads PKDEV_API_URL and PKDEV_SITE_KEY from your .env automatically.');
                $this->line('  To override per-deploy, add before the require:');
                $this->line('  <fg=cyan>set(\'pkdev_api_url\', \''.$apiUrl.'\');</>');
                $this->line('  <fg=cyan>set(\'pkdev_site_key\', \'<your-site-key>\');  // use the value from your .env</>');
                $this->line('');
            }

            return self::SUCCESS;
        }

        $this->line('  <fg=red>✗</> Could not reach PKDev — env vars written but connectivity failed');
        $this->line('  Check PKDEV_API_URL is reachable from this server.');
        $this->line('  Run <fg=yellow>php artisan pkdev:ping</> to retry once the URL is correct.');
        $this->line('');

        return self::FAILURE;
    }

    private function currentEnvValue(string $envPath, string $key): ?string
    {
        $contents = file_get_contents($envPath);
        if (preg_match('/^'.$key.'=(.*)$/m', $contents, $matches)) {
            return trim($matches[1], " \t\"'");
        }
        return null;
    }

    private function writeEnvValues(string $envPath, array $values): void
    {
        $contents = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;

            if (preg_match('/^'.$key.'=/m', $contents)) {
                $contents = preg_replace('/^'.$key.'=.*$/m', $line, $contents);
            } else {
                $contents .= PHP_EOL.$line;
            }
        }

        file_put_contents($envPath, $contents, LOCK_EX);
    }
}
