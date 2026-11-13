<?php

namespace Pkdev\Reporter\Listeners;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\Redactor;
use Pkdev\Reporter\Support\SiteKey;

class ScheduledTaskListener
{
    /**
     * A background task fires this at launch, and a failed foreground task is followed by ScheduledTaskFailed,
     * so only a foreground success is reported here.
     */
    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if ($event->task->runInBackground || (int) $event->task->exitCode !== 0) { return; }

        $this->send($event->task, 'success', 0, (int) round($event->runtime * 1000));
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $runtime = $event->runtime ?? null;

        $this->send(
            $event->task,
            'failed',
            (int) $event->task->exitCode ?: 1,
            $runtime === null ? null : (int) round($runtime * 1000),
            Redactor::clean($event->exception->getMessage()),
        );
    }

    public function handleBackgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $exitCode = (int) $event->task->exitCode;

        $this->send($event->task, $exitCode === 0 ? 'success' : 'failed', $exitCode, null);
    }

    private function send(Event $task, string $status, int $exitCode, ?int $durationMs, ?string $exceptionMessage = null): void
    {
        if (!$this->shouldReport()) { return; }

        $commandName = $this->extractCommandName($task);

        if ($commandName === 'artisan pkdev:report') { return; }

        try {
            app(PkdevClient::class)->post('/api/v1/cron-ping', array_filter([
                'command_name' => $commandName,
                'status' => $status,
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
                'exception_message' => $exceptionMessage === null ? null : mb_strcut($exceptionMessage, 0, 5000),
                'fired_at' => now()->utc()->toIso8601String(),
            ], fn ($value) => $value !== null));
        } catch (\Throwable) {
            // Never break the host app
        }
    }

    private function extractCommandName(Event $task): string
    {
        $summary = $task->getSummaryForDisplay();

        // Extract "artisan <command>" from full shell string (e.g. "'/usr/bin/php' 'artisan' pkdev:report > ...")
        if (preg_match("/artisan['\s]+([a-zA-Z0-9:_-]+)/", $summary, $m)) {
            return 'artisan '.$m[1];
        }

        return mb_substr(Redactor::clean($summary), 0, 255);
    }

    private function shouldReport(): bool
    {
        return config('pkdev-reporter.enabled', true)
            && config('pkdev-reporter.features.cron', true)
            && !empty(config('pkdev-reporter.api_url'))
            && SiteKey::current() !== null;
    }
}
