# Architecture — pkdev/reporter

Implementation rules specific to this project: where code goes, what must not be done, and
the conventions a change has to respect.

Code **style** is not here — the house baseline is
`~/personal/launchpad/standards/php-style.md`, loaded by `/prime`, and no project overrides
it.

## Package-Specific
- All public classes are stable API. Mark internal/experimental ones with `@internal`.
- Facades are fine — this is a Laravel-only package and facades are the standard pattern. `DB`, `Redis`, `Log`, `Event` are all appropriate. Avoid facades only if writing framework-agnostic code (which this isn't).
- All HTTP calls go through `PkdevClient`. Never raw Guzzle elsewhere in the package.
- Any facade call that depends on optional infrastructure (Redis, Horizon) must be wrapped in `try/catch (\Throwable)` so the host app degrades gracefully if that service isn't available.

## Testing
- Use Orchestra Testbench for Laravel context.
- Unit test `QueueInspector` with seeded DB queue tables.
- Feature test `HeartbeatReporter` with Guzzle's `MockHandler`.
- Explicitly test the failure mode: `PkdevClient::post()` must return `false` (not throw) when the server is unreachable.
- Test the recursion guard in `ExceptionReporter`.
