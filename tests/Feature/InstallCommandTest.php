<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\PendingCommand;
use Pkdev\Reporter\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private string $siteKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setBasePath($this->sandbox);
        $this->siteKey = 'pk_'.str_repeat('e', 36);
    }

    private function writeEnv(string $contents): void
    {
        file_put_contents($this->sandbox.'/.env', $contents);
    }

    private function envContents(): string
    {
        return file_get_contents($this->sandbox.'/.env');
    }

    private function install(string $apiUrl, string $queues): PendingCommand
    {
        return $this->artisan('pkdev:install')
            ->expectsQuestion('PKDev API URL', $apiUrl)
            ->expectsQuestion('Site API key (from the PKDev Ops panel)', $this->siteKey)
            ->expectsQuestion('Queue names to monitor (comma-separated)', $queues);
    }

    public function test_it_writes_the_reporters_keys_into_the_env_and_leaves_the_rest_alone(): void
    {
        $this->writeEnv("APP_NAME=\"Wyld Bee Meads\"\nPKDEV_SITE_KEY=pk_old\nPKDEV_ENABLED=false\nDB_HOST=127.0.0.1\n");
        $this->hubResponds(new Response(200));

        $this->install('https://app.pkdev.dev', 'default,emails')
            ->expectsConfirmation('Do you use Deployer for deployments?', 'no')
            ->assertSuccessful();

        $this->assertSame(
            "APP_NAME=\"Wyld Bee Meads\"\nPKDEV_SITE_KEY={$this->siteKey}\nPKDEV_ENABLED=true\nDB_HOST=127.0.0.1\n".PHP_EOL.'PKDEV_API_URL=https://app.pkdev.dev'.PHP_EOL.'PKDEV_QUEUES=default,emails',
            $this->envContents(),
        );
        $this->assertSame('Bearer '.$this->siteKey, $this->sentRequests[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('https://app.pkdev.dev/api/v1/heartbeat', (string) $this->sentRequests[0]['request']->getUri());
    }

    public function test_running_it_twice_replaces_each_key_rather_than_adding_another(): void
    {
        $this->writeEnv("APP_ENV=production\n");
        $this->hubResponds(new Response(200), new Response(200));

        $this->install('https://app.pkdev.dev', 'default')->expectsConfirmation('Do you use Deployer for deployments?', 'no')->assertSuccessful();
        $this->install('https://app.pkdev.dev', 'default,emails')->expectsConfirmation('Do you use Deployer for deployments?', 'no')->assertSuccessful();

        $this->assertSame(1, substr_count($this->envContents(), 'PKDEV_QUEUES='));
        $this->assertStringContainsString('PKDEV_QUEUES=default,emails', $this->envContents());
    }

    public function test_an_unreachable_hub_still_writes_the_env_and_fails_the_command(): void
    {
        $this->writeEnv("APP_ENV=production\n");
        $failure = new ConnectException('Connection refused', new Request('POST', 'https://app.pkdev.dev'));
        $this->hubResponds($failure, $failure, $failure);

        $this->install('https://app.pkdev.dev', 'default')->assertFailed();

        $this->assertStringContainsString("PKDEV_SITE_KEY={$this->siteKey}", $this->envContents());
    }

    public function test_without_a_site_key_nothing_is_written(): void
    {
        $this->writeEnv("APP_ENV=production\n");
        $this->hubResponds();

        $this->artisan('pkdev:install')
            ->expectsQuestion('PKDev API URL', 'https://app.pkdev.dev')
            ->expectsQuestion('Site API key (from the PKDev Ops panel)', '')
            ->assertFailed();

        $this->assertSame("APP_ENV=production\n", $this->envContents());
        $this->assertSame([], $this->sentPaths());
    }
}
