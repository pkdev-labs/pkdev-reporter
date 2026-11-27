<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Pkdev\Reporter\Tests\TestCase;

class NotifyDeploymentCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('d', 36)]);
        Carbon::setTestNow(Carbon::parse('2026-11-27 14:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_successful_deploy_posts_completed_with_what_the_recipe_passed(): void
    {
        $this->hubResponds(new Response(200));

        $this->artisan('pkdev:notify-deployment', [
            '--status' => 'completed',
            '--branch' => 'master',
            '--commit' => 'a1b2c3d4e5f6',
            '--commit-message' => 'Shop: gift cards',
            '--deployer' => 'github-actions',
            '--environment' => 'production',
            '--duration-ms' => '48210',
        ])->assertSuccessful();

        $this->assertSame(['/api/v1/deployment'], $this->sentPaths());
        $this->assertSame([
            'status' => 'completed',
            'branch' => 'master',
            'commit' => 'a1b2c3d4e5f6',
            'commit_message' => 'Shop: gift cards',
            'deployer' => 'github-actions',
            'environment' => 'production',
            'duration_ms' => 48210,
            'deployed_at' => '2026-11-27T14:30:00+00:00',
        ], $this->sentBody(0));
    }

    public function test_a_failed_deploy_posts_failed(): void
    {
        $this->hubResponds(new Response(200));

        $this->artisan('pkdev:notify-deployment', ['--status' => 'failed', '--branch' => 'master'])->assertSuccessful();

        $this->assertSame('failed', $this->sentBody(0)['status']);
    }

    public function test_with_no_options_it_posts_completed_and_the_apps_environment(): void
    {
        $this->hubResponds(new Response(200));

        $this->artisan('pkdev:notify-deployment')->assertSuccessful();

        $body = $this->sentBody(0);
        $this->assertSame('completed', $body['status']);
        $this->assertSame('testing', $body['environment']);
        $this->assertNull($body['commit']);
        $this->assertNull($body['deployer']);
        $this->assertNull($body['duration_ms']);
    }

    public function test_a_hub_that_is_down_never_fails_the_deploy(): void
    {
        $failure = new ConnectException('Connection refused', new Request('POST', 'https://hub.test'));
        $this->hubResponds($failure, $failure, $failure);

        $this->artisan('pkdev:notify-deployment', ['--status' => 'completed'])->assertSuccessful();

        $this->assertCount(3, $this->sentRequests);
    }

    public function test_a_hub_that_errors_never_fails_the_deploy(): void
    {
        $this->hubResponds(new Response(503), new Response(503), new Response(500));

        $this->artisan('pkdev:notify-deployment', ['--status' => 'failed'])->assertSuccessful();

        $this->assertCount(3, $this->sentRequests);
    }

    public function test_a_disabled_reporter_posts_nothing(): void
    {
        config(['pkdev-reporter.enabled' => false]);
        $this->hubResponds(new Response(200));

        $this->artisan('pkdev:notify-deployment', ['--status' => 'completed'])->assertSuccessful();

        $this->assertSame([], $this->sentPaths());
    }
}
