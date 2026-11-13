<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Pkdev\Reporter\Reporters\ExceptionReporter;
use Pkdev\Reporter\Tests\Fixtures\PackageException;
use Pkdev\Reporter\Tests\TestCase;
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

    public function test_nothing_is_reported_without_a_key(): void
    {
        config(['pkdev-reporter.site_key' => null]);

        app(ExceptionReporter::class)->report(new RuntimeException('no key'));

        $this->assertSame([], $this->sentPaths());
    }
}
