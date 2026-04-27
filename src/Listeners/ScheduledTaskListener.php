<?php

namespace Pkdev\Reporter\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Pkdev\Reporter\Http\PkdevClient;
use Pkdev\Reporter\Support\Redactor;

class ScheduledTaskListener
{
    public function handleFinished(ScheduledTaskFinished $event): void
    {
        if (!$this->shouldReport()) {
            return;
        }

        try {
            app(PkdevClient::class)->post('/api/v1/cron-ping', [
                'command_name' => $this->extractCommandName($event->task),
                'status'       => 'success',
                'exit_code'    => 0,
                'duration_ms'  => (int) round($event->runtime * 1000),
                'fired_at'     => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Never break the host app
        }
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        if (!$this->shouldReport()) {
            return;
        }

        try {
            app(PkdevClient::class)->post('/api/v1/cron-ping', [
                'command_name'      => $this->extractCommandName($event->task),
                'status'            => 'failed',
                'exit_code'         => 1,
                'duration_ms'       => null,
                'exception_message' => $event->exception ? Redactor::clean($event->exception->getMessage()) : null,
                'fired_at'          => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Never break the host app
        }
    }

    private function extractCommandName(\Illuminate\Console\Scheduling\Event $task): string
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
            && !empty(config('pkdev-reporter.site_key'));
    }
}
