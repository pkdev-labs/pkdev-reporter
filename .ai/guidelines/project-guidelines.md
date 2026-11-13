# pkdev/reporter — Project Guidelines (AUTHORITATIVE)

## Mission
A Laravel package installed into every site Phil owns. Reports telemetry (heartbeats, queue stats, cron status, exceptions, deployments) to the PKDev control plane with no configuration or code changes in the host app: `composer require`, deploy, and approve the site in PKDev.

## Compatibility
- PHP: 8.3+
- Laravel: 12.x, 13.x

## Public Interface (what host apps interact with)
1. `PKDEV_SITE_KEY` — optional env var, a key minted in the Ops panel. Without it the site generates `storage/app/pkdev-site.key` and enrolls through `POST /api/v1/enroll`
2. `PKDEV_ENABLED` / `PKDEV_API_URL` — optional; on in production only, `https://app.pkdev.dev`
3. `pkdev:ping`, `pkdev:test` — artisan commands, manual connectivity checks
4. `vendor:publish --tag=pkdev-reporter-config` — optional config override
5. `require __DIR__.'/vendor/pkdev/reporter/deployer/pkdev.php'` — optional deploy hook

Everything else is automatic via ServiceProvider boot.

## What Auto-Registers on Boot
When enabled (production by default):
- Schedules `pkdev:report` every minute via the host's scheduler. It is the only code that writes the key file, so the file belongs to the scheduler's user
- Listens to `ScheduledTaskFinished`, `ScheduledTaskFailed` and `ScheduledBackgroundTaskFinished`, and never reports `pkdev:report` itself
- Hooks the exception handler via `reportable()` callback

## Failure Mode (Non-Negotiable)
The host site's stability takes priority over PKDev reporting. Rules:
- All HTTP calls go through `PkdevClient` — `send()` returns the status code or null, `post()` returns bool, and neither throws. Only transport failures and 5xx are retried; a 4xx is final.
- If PKDev is unreachable, the host continues normally. The only sign of failure is a log warning prefixed with `[pkdev/reporter]`.
- Timeout: 5 seconds. Retries: 2x with exponential backoff. Total worst case: ~7.5 seconds per call — acceptable for background/queued contexts, not for request lifecycle. Exception reports are async-safe (called from exception handler, not middleware).

## Recursion Guard
`ExceptionReporter` must not report its own exceptions back to PKDev. Guard: skip any exception whose class or file path contains `Pkdev\Reporter` or `pkdev/reporter`.

## Out of Scope
- No UI (no routes, no Blade views)
- No database tables in the host app
- No modification of host app's data, beyond the key file and its enrollment marker in `storage/app`
- No AI integration of any kind
- No new cron entries — uses the host's existing Laravel scheduler
