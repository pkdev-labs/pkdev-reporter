<?php

namespace Pkdev\Reporter\Support;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;

/**
 * The name a scheduled task reports under. Cron pings and the heartbeat's schedule both use it,
 * which is how the hub matches a ping to the task that should have sent it.
 *
 * @internal
 */
class ScheduledCommandName
{
    public const OWN_REPORT = 'artisan pkdev:report';

    public static function of(Event $event): string
    {
        $summary = $event->getSummaryForDisplay();

        // Extract "artisan <command>" from full shell string (e.g. "'/usr/bin/php' 'artisan' pkdev:report > ...")
        if (preg_match("/artisan['\s]+([a-zA-Z0-9:_-]+)/", $summary, $matches)) { return 'artisan '.$matches[1]; }

        return mb_substr(Redactor::clean($summary), 0, 255);
    }

    /**
     * A closure scheduled without a name or description, which every such task shares as "Callback".
     */
    public static function isUnnamedClosure(Event $event): bool
    {
        return $event instanceof CallbackEvent
            && !is_string($event->description)
            && $event->getSummaryForDisplay() === 'Callback';
    }
}
