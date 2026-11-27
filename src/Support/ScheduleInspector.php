<?php

namespace Pkdev\Reporter\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class ScheduleInspector
{
    /**
     * The tasks this environment schedules, named the way their cron pings are. Never throws.
     *
     * @return list<array{command: string, expression: string, timezone: string}>
     */
    public function entries(): array
    {
        try {
            $environment = app()->environment();
            $entries = [];

            foreach (app(Schedule::class)->events() as $event) {
                if (!$event->runsInEnvironment($environment) || ScheduledCommandName::isUnnamedClosure($event)) { continue; }

                $command = ScheduledCommandName::of($event);

                if ($command === ScheduledCommandName::OWN_REPORT) { continue; }

                $entries[] = [
                    'command' => $command,
                    'expression' => $event->expression,
                    'timezone' => $this->timezoneOf($event),
                ];
            }

            return $entries;
        } catch (\Throwable $exception) {
            Log::warning('[pkdev/reporter] Could not read the schedule: '.$exception->getMessage());

            return [];
        }
    }

    private function timezoneOf(Event $event): string
    {
        if ($event->timezone instanceof \DateTimeZone) { return $event->timezone->getName(); }

        return is_string($event->timezone) && $event->timezone !== '' ? $event->timezone : (string) config('app.timezone');
    }
}
