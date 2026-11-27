<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Pkdev\Reporter\Reporters\ExceptionReporter;
use Pkdev\Reporter\Tests\Fixtures\PackageException;
use Pkdev\Reporter\Tests\TestCase;
use ReflectionProperty;
use RuntimeException;

class ExceptionReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('f', 36)]);
        $this->hubResponds(new Response(200));
    }

    public function test_its_own_exceptions_are_never_reported(): void
    {
        app(ExceptionReporter::class)->report(new PackageException('loop'));

        $this->assertSame([], $this->sentPaths());

        app(ExceptionReporter::class)->report(new RuntimeException('host failure'));

        $this->assertSame(['/api/v1/exception'], $this->sentPaths());
    }

    public function test_a_long_message_is_cut_to_valid_utf8(): void
    {
        app(ExceptionReporter::class)->report(new RuntimeException(str_repeat('é', 4000)));

        $message = $this->sentBody(0)['message'];
        $this->assertLessThanOrEqual(5000, strlen($message));
        $this->assertTrue(mb_check_encoding($message, 'UTF-8'));
    }

    public function test_the_same_exception_is_reported_once_a_minute(): void
    {
        $exception = new RuntimeException('repeat');

        app(ExceptionReporter::class)->report($exception);
        app(ExceptionReporter::class)->report($exception);

        $this->assertSame(['/api/v1/exception'], $this->sentPaths());
    }

    public function test_a_cache_outage_skips_the_dedupe_and_still_sends(): void
    {
        Cache::shouldReceive('has')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));
        Cache::shouldReceive('put')->never();

        app(ExceptionReporter::class)->report(new RuntimeException('host failure'));

        $this->assertSame(['/api/v1/exception'], $this->sentPaths());
        $this->assertSame('host failure', $this->sentBody(0)['message']);
    }

    public function test_in_a_web_request_the_report_is_sent_after_the_response(): void
    {
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);

        app(ExceptionReporter::class)->report(new RuntimeException('during a request'));

        $this->assertSame([], $this->sentPaths());

        $this->app->terminate();

        $this->assertSame(['/api/v1/exception'], $this->sentPaths());
        $this->assertSame('during a request', $this->sentBody(0)['message']);
    }

    public function test_in_the_console_and_queue_the_report_is_sent_at_once(): void
    {
        $this->assertTrue($this->app->runningInConsole());

        app(ExceptionReporter::class)->report(new RuntimeException('in a job'));

        $this->assertSame(['/api/v1/exception'], $this->sentPaths());
    }

    public function test_exceptions_use_their_own_timeout_and_retries(): void
    {
        $failure = new ConnectException('Connection timed out', new Request('POST', 'https://hub.test'));
        $this->hubResponds($failure, $failure, $failure);

        app(ExceptionReporter::class)->report(new RuntimeException('slow hub'));

        $this->assertCount(2, $this->sentRequests);
        $this->assertSame(2.0, $this->sentRequests[0]['options']['timeout']);
    }

    public function test_nothing_is_reported_without_a_key(): void
    {
        config(['pkdev-reporter.site_key' => null]);

        app(ExceptionReporter::class)->report(new RuntimeException('no key'));

        $this->assertSame([], $this->sentPaths());
    }
}
