<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Pkdev\Reporter\Reporters\HeartbeatReporter;
use Pkdev\Reporter\Tests\TestCase;

class HeartbeatReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('a', 36)]);
    }

    public function test_heartbeats_keep_the_general_timeout_and_retries(): void
    {
        $failure = new ConnectException('Connection timed out', new Request('POST', 'https://hub.test'));
        $this->hubResponds($failure, $failure, $failure);

        app(HeartbeatReporter::class)->send();

        $this->assertCount(3, $this->sentRequests);
        $this->assertSame(5.0, $this->sentRequests[0]['options']['timeout']);
    }
}
