# Changelog

## 1.2.0

- **The heartbeat carries the schedule.** Every heartbeat, including `pkdev:ping` and `pkdev:test`, sends `schedule`: a list of `{ command, expression, timezone }`, one per task scheduled in the current environment. `command` is the same name the task's cron pings use. Closures with no name or description and `pkdev:report` itself are left out, and a task with no timezone falls back to `app.timezone`. If the schedule cannot be read, the list is empty and a warning is logged.
- **Exceptions are sent after the response.** In a web request the exception post runs on `app()->terminating()`. Console commands and queue jobs still send at once.
- **Shorter exception timeouts.** New config keys `exception_timeout` (2 seconds) and `exception_retries` (1) apply to exception posts only, cutting the worst case from about 16.5s to about 4.5s. Heartbeats and deploy notices keep `http_timeout` and `http_retries`.
- **A cache outage can't break exception reporting.** If the cache fails, the 60-second dedupe is skipped and the report is still sent.
- `PkdevClient::send()` and `post()` take optional `timeout` and `retries` arguments.
