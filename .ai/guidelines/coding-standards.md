# pkdev/reporter — Coding Standards

Mirrors pkdev's standards plus package-specific rules.

## PHP Style
- PHP 8.3+ compatible syntax (the floor is 8.3, not 8.5)
- Strict typing: parameter types and return types on every method
- Constructor property promotion: `public function __construct(private readonly PkdevClient $client) {}`
- Always use braces on `if` / `else` / `foreach`, even single-line
- Early returns over nested conditions
- **No space between `!` and the operand**: `!$enabled`, `!config(...)`, `!class_exists(...)`. Never `! $value`.

## Package-Specific
- All public classes are stable API. Mark internal/experimental ones with `@internal`.
- Facades are fine — this is a Laravel-only package and facades are the standard pattern. `DB`, `Redis`, `Log`, `Event` are all appropriate. Avoid facades only if writing framework-agnostic code (which this isn't).
- All HTTP calls go through `PkdevClient`. Never raw Guzzle elsewhere in the package.
- Configuration is read at call time via `config()`, not cached at boot — supports host apps that modify config at runtime.
- Any facade call that depends on optional infrastructure (Redis, Horizon) must be wrapped in `try/catch (\Throwable)` so the host app degrades gracefully if that service isn't available.

## Testing
- Use Orchestra Testbench for Laravel context.
- Unit test `QueueInspector` with seeded DB queue tables.
- Feature test `HeartbeatReporter` with Guzzle's `MockHandler`.
- Explicitly test the failure mode: `PkdevClient::post()` must return `false` (not throw) when the server is unreachable.
- Test the recursion guard in `ExceptionReporter`.

## Formatting
- Run `vendor/bin/pint --dirty` before committing.
- Pint config: Laravel preset.
