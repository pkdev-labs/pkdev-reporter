<?php

namespace Pkdev\Reporter;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pkdev\Reporter\Listeners\ScheduledTaskListener;
use Pkdev\Reporter\Reporters\ExceptionReporter;

class PkdevReporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/pkdev-reporter.php',
            'pkdev-reporter'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/pkdev-reporter.php' => config_path('pkdev-reporter.php'),
        ], 'pkdev-reporter-config');

        $this->commands([
            Commands\InstallCommand::class,
            Commands\ReportCommand::class,
            Commands\PingCommand::class,
            Commands\TestCommand::class,
        ]);

        if (config('pkdev-reporter.features.cron', true)) {
            Event::listen(ScheduledTaskFinished::class, [ScheduledTaskListener::class, 'handleFinished']);
            Event::listen(ScheduledTaskFailed::class,   [ScheduledTaskListener::class, 'handleFailed']);
        }

        if (config('pkdev-reporter.features.exceptions', true)) {
            $this->callAfterResolving(
                \Illuminate\Contracts\Debug\ExceptionHandler::class,
                function ($handler): void {
                    if (method_exists($handler, 'reportable')) {
                        $handler->reportable(function (\Throwable $e): void {
                            app(ExceptionReporter::class)->report($e);
                        });
                    }
                }
            );
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!config('pkdev-reporter.enabled')) {
                return;
            }

            $schedule->command('pkdev:report')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground()
                ->onFailure(function (): void {
                    // Silently swallow — never let reporting kill the host app's scheduler
                });
        });
    }
}
