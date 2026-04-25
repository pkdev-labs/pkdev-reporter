<?php

/**
 * PKDev Deployer Recipe
 *
 * Usage in your deploy.php:
 *
 *   require 'vendor/pkdev/reporter/deployer/pkdev.php';
 *
 * Configure via environment variables or Deployer set():
 *   PKDEV_API_URL  — your PKDev hub URL (e.g. https://app.pkdev.dev)
 *   PKDEV_SITE_KEY — site API key from the PKDev ops panel
 *
 * Or in deploy.php:
 *   set('pkdev_api_url',  'https://app.pkdev.dev');
 *   set('pkdev_site_key', 'pk_...');
 */

namespace Deployer;

use GuzzleHttp\Client;

set('pkdev_api_url',  getenv('PKDEV_API_URL') ?: null);
set('pkdev_site_key', getenv('PKDEV_SITE_KEY') ?: null);
set('pkdev_start_time', null);

// Record deploy start time
before('deploy', 'pkdev:start');

// Notify on success or failure
after('deploy:success', 'pkdev:success');
after('deploy:failed',  'pkdev:failed');

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
    $apiUrl  = get('pkdev_api_url');
    $siteKey = get('pkdev_site_key');

    if (!$apiUrl || !$siteKey) {
        return;
    }

    $start      = get('pkdev_start_time');
    $durationMs = $start ? (int) round((microtime(true) - $start) * 1000) : null;

    try {
        $commit        = runLocally('git rev-parse --short=12 HEAD 2>/dev/null || echo ""');
        $commitMessage = runLocally('git log -1 --pretty=%s 2>/dev/null || echo ""');
    } catch (\Throwable) {
        $commit        = null;
        $commitMessage = null;
    }

    try {
        $client = new Client(['timeout' => 5]);
        $client->post(rtrim($apiUrl, '/').'/api/v1/deployment', [
            'headers' => [
                'Authorization' => 'Bearer '.$siteKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'branch'         => get('branch', 'main'),
                'commit'         => $commit ?: null,
                'commit_message' => $commitMessage ?: null,
                'deployer'       => get('local_user', null),
                'environment'    => get('labels/stage', get('stage', 'production')),
                'status'         => $status,
                'duration_ms'    => $durationMs,
                'deployed_at'    => date('c'),
            ],
        ]);
    } catch (\Throwable) {
        // Never fail a deployment because of PKDev reporting
    }
}
