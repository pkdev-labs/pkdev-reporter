# pkdev/reporter — Claude Code Project Context

This is a CLEAN Composer package. No Filament, no AI integration, no project-specific code. Its only job: report telemetry from a host Laravel app to the PKDev hub.

## Read Order
1. `.ai/guidelines/project-guidelines.md` (AUTHORITATIVE)
2. `~/personal/launchpad/standards/php-style.md` (house baseline, loaded by `/prime`)
3. `README.md` — public install + usage instructions

## Critical Constraints
- Compatible with Laravel 11, 12, and 13. Stay within shared contracts.
- Failures MUST NOT break the host site. Every HTTP call is wrapped in try/catch. `PkdevClient::post()` never throws.
- Zero AI guidelines. Zero AI code. Clean package only.
- Auto-discovery via `extra.laravel.providers`. Host adds two env vars and the package handles everything else.

**American spelling everywhere:** code names, comments, tests and docs (color, center, gray,
labeled, normalize). The package has no exceptions today; a Laravel or vendor name we call but
did not name keeps its own spelling.

## Dev Setup
`pkdev` app at `../pkdev` includes this via path repository. Changes here are live instantly (symlinked into Sail at `/var/www/pkdev-reporter`). No `composer update` needed for code changes.

## Test Commands
```bash
composer install
vendor/bin/phpunit
```

Uses Orchestra Testbench for Laravel context.

## Versioning
- `v0.x.y` while API contract is unstable (current phase)
- `v1.0.0` once heartbeat + queue + cron + exceptions are stable
- Breaking changes to payload shapes require coordinated release with the pkdev hub
