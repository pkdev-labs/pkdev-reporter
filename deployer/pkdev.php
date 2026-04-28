<?php

/**
 * PKDev Deployer Recipe
 *
 * Usage in your deploy.php:
 *
 *   require 'vendor/pkdev/reporter/deployer/pkdev.php';
 *
 * Credentials are read from the server's .env (PKDEV_API_URL, PKDEV_SITE_KEY).
 * No secrets needed in CI — the artisan command runs on the server.
 */

namespace Deployer;

set('pkdev_start_time', null);

before('deploy', 'pkdev:start');
after('deploy:success', 'pkdev:success');
after('deploy:failed', 'pkdev:failed');

desc('Record PKDev deployment start time');
task('pkdev:start', function (): void {
    set('pkdev_start_time', microtime(true));
})->hidden();

desc('Notify PKDev of successful deployment');
task('pkdev:success', function (): void {
    pkdev_notify('completed');
})->hidden();

desc('Notify PKDev of failed deployment');
task('pkdev:failed', function (): void {
    pkdev_notify('failed');
})->hidden();

function pkdev_notify(string $status): void
{
    $start      = get('pkdev_start_time');
    $durationMs = $start ? (int) round((microtime(true) - $start) * 1000) : null;

    try {
        $commit        = runLocally('git rev-parse --short=12 HEAD 2>/dev/null || echo ""');
        $commitMessage = runLocally('git log -1 --pretty=%s 2>/dev/null || echo ""');
    } catch (\Throwable) {
        $commit        = null;
        $commitMessage = null;
    }

    $args = implode(' ', array_filter([
        '--status='.escapeshellarg($status),
        '--branch='.escapeshellarg(get('branch', 'main')),
        $commit        ? '--commit='.escapeshellarg(trim($commit))        : '',
        $commitMessage ? '--commit-message='.escapeshellarg(trim($commitMessage)) : '',
        '--deployer='.escapeshellarg((string) get('local_user', '')),
        '--environment='.escapeshellarg((string) get('labels/stage', get('stage', 'production'))),
        $durationMs !== null ? '--duration-ms='.escapeshellarg((string) $durationMs) : '',
    ]));

    try {
        run("cd {{release_or_current_path}} && php artisan pkdev:notify-deployment $args");
    } catch (\Throwable) {
        // Never fail a deployment because of PKDev reporting
    }
}
