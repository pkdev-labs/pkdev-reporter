# pkdev/reporter — Project Guidelines (AUTHORITATIVE)

## Mission
A Laravel package installed into every site Phil owns. Reports telemetry (heartbeats, queue stats, cron status, exceptions, deployments) to the PKDev control plane without requiring configuration or code changes in the host app beyond two env vars.

## Compatibility
- PHP: 8.3+
- Laravel: 11.x, 12.x, 13.x

## Public Interface (what host apps interact with)
1. `PKDEV_API_URL` — env var, base URL of the PKDev hub
2. `PKDEV_SITE_KEY` — env var, the Bearer token for this site
3. `pkdev:ping` — artisan command, manual connectivity check
4. `vendor:publish --tag=pkdev-reporter-config` — optional config override
5. `require __DIR__.'/vendor/pkdev/reporter/deployer/pkdev.php'` — optional deploy hook

Everything else is automatic via ServiceProvider boot.

## What Auto-Registers on Boot
When `PKDEV_ENABLED=true` (default):
- Schedules `pkdev:report` every minute via the host's scheduler
- Listens to `ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed` events
- Hooks the exception handler via `reportable()` callback

## Failure Mode (Non-Negotiable)
The host site's stability takes priority over PKDev reporting. Rules:
- All HTTP calls go through `PkdevClient::post()` — which catches everything and returns bool.
- If PKDev is unreachable, the host continues normally. The only sign of failure is a log warning prefixed with `[pkdev/reporter]`.
- Timeout: 5 seconds. Retries: 2x with exponential backoff. Total worst case: ~7.5 seconds per call — acceptable for background/queued contexts, not for request lifecycle. Exception reports are async-safe (called from exception handler, not middleware).

## Recursion Guard
`ExceptionReporter` must not report its own exceptions back to PKDev. Guard: skip any exception whose class or file path contains `Pkdev\Reporter` or `pkdev/reporter`.

## Out of Scope
- No UI (no routes, no Blade views)
- No database tables in the host app
- No modification of host app's data
- No AI integration of any kind
- No new cron entries — uses the host's existing Laravel scheduler
