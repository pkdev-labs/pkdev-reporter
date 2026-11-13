<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\SiteKey;
use Pkdev\Reporter\Tests\TestCase;

class PkdevClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('d', 36)]);
    }

    public function test_a_client_error_is_final(): void
    {
        $this->hubResponds(new Response(422));

        $this->assertFalse(app(PkdevClient::class)->post('/api/v1/heartbeat', []));
        $this->assertCount(1, $this->sentRequests);
    }

    public function test_a_server_error_is_retried(): void
    {
        $this->hubResponds(new Response(503), new Response(502), new Response(200));

        $this->assertTrue(app(PkdevClient::class)->post('/api/v1/heartbeat', []));
        $this->assertCount(3, $this->sentRequests);
    }

    public function test_an_unreachable_hub_returns_false_and_never_throws(): void
    {
        $failure = new ConnectException('Connection refused', new Request('POST', 'https://hub.test'));
        $this->hubResponds($failure, $failure, $failure);

        $this->assertFalse(app(PkdevClient::class)->post('/api/v1/heartbeat', []));
        $this->assertNull(app(PkdevClient::class)->send('/api/v1/heartbeat', []));
    }

    public function test_the_key_is_read_when_the_request_is_made(): void
    {
        config(['pkdev-reporter.site_key' => null]);
        $client = app(PkdevClient::class);
        $key = SiteKey::ensure();
        $this->hubResponds(new Response(200));

        $client->post('/api/v1/heartbeat', ['a' => 1]);

        $request = $this->sentRequests[0]['request'];
        $this->assertSame('Bearer '.$key, $request->getHeaderLine('Authorization'));
        $this->assertSame('sha256='.hash_hmac('sha256', '{"a":1}', hash('sha256', $key)), $request->getHeaderLine('X-PKDev-Signature'));
        $this->assertSame('https://hub.test/api/v1/heartbeat', (string) $request->getUri());
    }
}
