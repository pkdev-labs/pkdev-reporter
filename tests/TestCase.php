<?php

namespace Pkdev\Reporter\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use Pkdev\Reporter\PkdevReporterServiceProvider;
use Psr\Http\Message\RequestInterface;

abstract class TestCase extends Orchestra
{
    protected string $sandbox;

    /** @var list<array{request: RequestInterface}> */
    protected array $sentRequests = [];

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir().'/pkdev-reporter-'.bin2hex(random_bytes(6));
        mkdir($this->sandbox.'/storage/app', 0775, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->sandbox);
    }

    protected function getPackageProviders($app): array
    {
        return [PkdevReporterServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->useStoragePath($this->sandbox.'/storage');
        $app->useEnvironmentPath($this->sandbox);

        $app['config']->set('pkdev-reporter.enabled', true);
        $app['config']->set('pkdev-reporter.api_url', 'https://hub.test');
        $app['config']->set('pkdev-reporter.site_key', null);
        $app['config']->set('pkdev-reporter.http_retry_delay_ms', 0);
        $app['config']->set('app.url', 'https://wyldbeemeads.com');
        $app['config']->set('app.name', 'Wyld Bee Meads');
    }

    /**
     * Answer the reporter's requests in order with these responses or exceptions.
     */
    protected function hubResponds(mixed ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sentRequests));

        $this->app->instance('pkdev-reporter.http', new Client(['handler' => $stack]));
    }

    /**
     * @return list<string>
     */
    protected function sentPaths(): array
    {
        return array_map(fn (array $entry): string => $entry['request']->getUri()->getPath(), $this->sentRequests);
    }

    protected function sentBody(int $index): array
    {
        return json_decode((string) $this->sentRequests[$index]['request']->getBody(), true);
    }
}
