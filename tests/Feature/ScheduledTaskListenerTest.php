<?php

namespace Pkdev\Reporter\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Pkdev\Reporter\Tests\TestCase;
use RuntimeException;

class ScheduledTaskListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['pkdev-reporter.site_key' => 'pk_'.str_repeat('e', 36)]);
        $this->hubResponds(new Response(200), new Response(200));
    }

    private function task(string $command = "'/usr/bin/php' 'artisan' backup:run", ?int $exitCode = 0, bool $background = false): Event
    {
        $task = new Event(app(CacheEventMutex::class), $command);
        $task->exitCode = $exitCode;
        $task->runInBackground = $background;

        return $task;
    }

    public function test_a_successful_run_reports_success(): void
    {
        event(new ScheduledTaskFinished($this->task(), 1.25));

        $this->assertSame(['/api/v1/cron-ping'], $this->sentPaths());
        $this->assertSame('artisan backup:run', $this->sentBody(0)['command_name']);
        $this->assertSame('success', $this->sentBody(0)['status']);
        $this->assertSame(1250, $this->sentBody(0)['duration_ms']);
    }

    public function test_a_failed_run_reports_one_failure_and_no_success(): void
    {
        $task = $this->task(exitCode: 2);

        event(new ScheduledTaskFinished($task, 0.5));
        event(new ScheduledTaskFailed($task, new RuntimeException('Scheduled command failed with exit code [2].'), 0.5));

        $this->assertSame(['/api/v1/cron-ping'], $this->sentPaths());
        $this->assertSame('failed', $this->sentBody(0)['status']);
        $this->assertSame(2, $this->sentBody(0)['exit_code']);
        $this->assertSame(500, $this->sentBody(0)['duration_ms']);
    }

    public function test_a_background_task_reports_when_it_finishes_not_when_it_launches(): void
    {
        $task = $this->task(exitCode: null, background: true);

        event(new ScheduledTaskFinished($task, 0.01));
        $this->assertSame([], $this->sentPaths());

        $task->exitCode = 1;
        event(new ScheduledBackgroundTaskFinished($task));

        $this->assertSame(['/api/v1/cron-ping'], $this->sentPaths());
        $this->assertSame('failed', $this->sentBody(0)['status']);
        $this->assertSame(1, $this->sentBody(0)['exit_code']);
    }

    public function test_its_own_report_command_is_never_reported(): void
    {
        $task = $this->task("'/usr/bin/php' 'artisan' pkdev:report", background: true);

        event(new ScheduledTaskFinished($task, 0.01));
        $task->exitCode = 0;
        event(new ScheduledBackgroundTaskFinished($task));

        $this->assertSame([], $this->sentPaths());
    }
}
