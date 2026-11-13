<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Pkdev\Reporter\Support\Enroller;
use Pkdev\Reporter\Support\SiteKey;
use Pkdev\Reporter\Tests\TestCase;

class ReportCommandTest extends TestCase
{
    public function test_a_site_with_no_key_generates_one_and_enrolls_when_the_hub_does_not_know_it(): void
    {
        $this->hubResponds(new Response(401), new Response(202));

        $this->artisan('pkdev:report')->assertSuccessful();

        $key = SiteKey::current();
        $this->assertMatchesRegularExpression(SiteKey::FORMAT, $key);
        $this->assertSame(['/api/v1/heartbeat', '/api/v1/enroll'], $this->sentPaths());
        $this->assertSame([
            'url' => 'https://wyldbeemeads.com',
            'name' => 'Wyld Bee Meads',
            'environment' => 'testing',
            'key' => $key,
        ], $this->sentBody(1));
        $this->assertSame('Bearer '.$key, $this->sentRequests[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_it_enrolls_at_most_once_an_hour(): void
    {
        $this->hubResponds(new Response(401), new Response(202), new Response(401));

        $this->artisan('pkdev:report');
        $this->artisan('pkdev:report');

        $this->assertSame(['/api/v1/heartbeat', '/api/v1/enroll', '/api/v1/heartbeat'], $this->sentPaths());

        touch(storage_path(Enroller::MARKER), time() - Enroller::RETRY_SECONDS - 1);
        $this->hubResponds(new Response(401), new Response(202));
        $this->sentRequests = [];

        $this->artisan('pkdev:report');

        $this->assertSame(['/api/v1/heartbeat', '/api/v1/enroll'], $this->sentPaths());
    }

    public function test_a_pending_site_stays_quiet(): void
    {
        Log::spy();
        $this->hubResponds(new Response(403, [], '{"status":"pending"}'));

        $this->artisan('pkdev:report')->assertSuccessful();

        $this->assertSame(['/api/v1/heartbeat'], $this->sentPaths());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_configured_key_the_hub_refuses_is_logged_and_never_enrolls(): void
    {
        Log::spy();
        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('c', 36)]);
        $this->hubResponds(new Response(401));

        $this->artisan('pkdev:report')->assertSuccessful();

        $this->assertSame(['/api/v1/heartbeat'], $this->sentPaths());
        $this->assertFileDoesNotExist(SiteKey::path());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_disabled_reporter_sends_nothing_and_writes_nothing(): void
    {
        config(['pkdev-reporter.enabled' => false]);
        $this->hubResponds();

        $this->artisan('pkdev:report')->assertSuccessful();

        $this->assertSame([], $this->sentPaths());
        $this->assertFileDoesNotExist(SiteKey::path());
    }
}
