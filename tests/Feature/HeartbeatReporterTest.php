<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Pkdev\Reporter\Reporters\HeartbeatReporter;
use Pkdev\Reporter\Tests\TestCase;
use RuntimeException;

class HeartbeatReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('a', 36)]);
    }

    private function freshSchedule(): Schedule
    {
        $schedule = new Schedule;
        $this->app->instance(Schedule::class, $schedule);

        return $schedule;
    }

    public function test_heartbeats_keep_the_general_timeout_and_retries(): void
    {
        $failure = new ConnectException('Connection timed out', new Request('POST', 'https://hub.test'));
        $this->hubResponds($failure, $failure, $failure);

        app(HeartbeatReporter::class)->send();

        $this->assertCount(3, $this->sentRequests);
        $this->assertSame(5.0, $this->sentRequests[0]['options']['timeout']);
    }

    public function test_the_heartbeat_carries_the_schedule(): void
    {
        config(['app.timezone' => 'America/Chicago']);
        $schedule = $this->freshSchedule();
        $schedule->command('backup:run')->dailyAt('03:15')->timezone('America/New_York');
        $schedule->job('App\Jobs\PruneStaleCarts')->hourly();
        $schedule->call(fn () => null)->name('send-digests')->everyFiveMinutes();
        $schedule->call(fn () => null)->everyMinute();
        $schedule->command('pkdev:report')->everyMinute();
        $this->hubResponds(new Response(200));

        app(HeartbeatReporter::class)->send();

        $this->assertSame([
            ['command' => 'artisan backup:run', 'expression' => '15 3 * * *', 'timezone' => 'America/New_York'],
            ['command' => 'App\Jobs\PruneStaleCarts', 'expression' => '0 * * * *', 'timezone' => 'America/Chicago'],
            ['command' => 'send-digests', 'expression' => '*/5 * * * *', 'timezone' => 'America/Chicago'],
        ], $this->sentBody(0)['schedule']);
    }

    public function test_tasks_limited_to_other_environments_are_left_out(): void
    {
        $schedule = $this->freshSchedule();
        $schedule->command('reports:send')->daily()->environments(['production']);
        $schedule->command('cache:warm')->daily()->environments(['production', 'testing']);
        $this->hubResponds(new Response(200));

        app(HeartbeatReporter::class)->send();

        $this->assertSame(['artisan cache:warm'], array_column($this->sentBody(0)['schedule'], 'command'));
    }

    public function test_an_empty_schedule_is_sent_as_an_empty_list(): void
    {
        $this->freshSchedule();
        $this->hubResponds(new Response(200));

        app(HeartbeatReporter::class)->send();

        $this->assertSame([], $this->sentBody(0)['schedule']);
    }

    public function test_a_schedule_that_fails_to_load_still_sends_the_heartbeat(): void
    {
        Log::spy();
        $this->app->bind(Schedule::class, fn () => throw new RuntimeException('routes/console.php failed'));
        $this->hubResponds(new Response(200));

        $this->assertSame(200, app(HeartbeatReporter::class)->send());

        $this->assertSame([], $this->sentBody(0)['schedule']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_schedule_names_each_task_the_way_its_cron_ping_does(): void
    {
        $schedule = $this->freshSchedule();
        $schedule->command('backup:run')->daily();
        $schedule->job('App\Jobs\PruneStaleCarts')->hourly();
        $schedule->exec('rsync -a /var/www/storage /mnt/backup')->daily();
        $this->hubResponds(new Response(200), new Response(200), new Response(200), new Response(200));

        app(HeartbeatReporter::class)->send();
        foreach ($schedule->events() as $event) {
            $event->exitCode = 0;
            event(new ScheduledTaskFinished($event, 0.1));
        }

        $pingedNames = array_map(fn (int $index): string => $this->sentBody($index)['command_name'], [1, 2, 3]);
        $this->assertSame(array_column($this->sentBody(0)['schedule'], 'command'), $pingedNames);
        $this->assertSame('artisan backup:run', $pingedNames[0]);
        $this->assertSame('App\Jobs\PruneStaleCarts', $pingedNames[1]);
        $this->assertStringStartsWith('rsync -a /var/www/storage /mnt/backup', $pingedNames[2]);
    }
}
