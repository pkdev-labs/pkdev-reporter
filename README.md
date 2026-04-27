# pkdev/reporter

Telemetry reporter for [PKDev](https://app.pkdev.dev) — sends heartbeats, queue snapshots, cron status, exceptions, and deployment events from your Laravel site to the PKDev control plane.

**Private package.** Install via VCS repository (see below).

---

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

---

## Installation

### 1. Add the VCS repository to `composer.json`

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:pkdev-labs/pkdev-reporter.git"
    }
]
```

### 2. Require the package

```bash
composer require pkdev/reporter
```

### 3. Run the interactive installer

```bash
php artisan pkdev:install
```

The installer will ask for your PKDev API URL and site key (generated in the PKDev Ops panel under **Sites → Create**), write them to `.env`, publish the config, and send a test heartbeat to confirm connectivity.

---

## What it does automatically

Once installed, the package hooks into your application with zero additional code:

| Feature | How |
|---|---|
| **Heartbeat + queue snapshot** | `pkdev:report` command scheduled every minute via the Laravel scheduler |
| **Cron monitoring** | Listens to `ScheduledTaskFinished` / `ScheduledTaskFailed` events — every scheduled command is tracked automatically |
| **Exception reporting** | Hooks into Laravel's exception handler via `reportable()` — unhandled exceptions are reported with class, message, file, line, and trace |
| **Deployment notifications** | Via the included [Deployer](https://deployer.org) recipe (optional) |

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `PKDEV_ENABLED` | No | Set `false` to silence the package entirely (default: `true`) |
| `PKDEV_API_URL` | Yes | PKDev hub URL — `https://app.pkdev.dev` in production, `http://host.docker.internal:8090` in Sail |
| `PKDEV_SITE_KEY` | Yes | Bearer token from the PKDev Ops panel |
| `PKDEV_QUEUES` | No | Comma-separated queue names to monitor (default: `default`) |

Feature toggles (all default `true`):

| Variable | Description |
|---|---|
| `PKDEV_FEATURE_HEARTBEAT` | Enable/disable heartbeat + queue reporting |
| `PKDEV_FEATURE_CRON` | Enable/disable cron monitoring |
| `PKDEV_FEATURE_EXCEPTIONS` | Enable/disable exception reporting |

---

## Deployer integration

If you deploy with [Deployer](https://deployer.org), require the included recipe in your `deploy.php` to send deployment notifications:

```php
require 'vendor/pkdev/reporter/deployer/pkdev.php';
```

The recipe reads `PKDEV_API_URL` and `PKDEV_SITE_KEY` from your server's `.env` automatically. It hooks into `deploy:success` and `deploy:failed` and sends the branch, commit, deployer username, duration, and status to PKDev.

> **Note:** The deployer recipe only fires over HTTPS. HTTP URLs are silently skipped.

---

## Artisan commands

| Command | Description |
|---|---|
| `php artisan pkdev:install` | Interactive setup — writes `.env`, publishes config, tests connectivity |
| `php artisan pkdev:report` | Manually send a heartbeat + queue snapshot |
| `php artisan pkdev:ping` | Send a single connectivity test without writing anything |

---

## Local dev (Sail)

If your spoke site runs in Laravel Sail and the PKDev hub also runs in Sail, set:

```
PKDEV_API_URL=http://host.docker.internal:8090
```

`host.docker.internal` resolves to your host machine from inside the Sail container, allowing the spoke to reach the hub's exposed port.

---

## Security

Every request is signed with an HMAC body signature (`X-PKDev-Signature: sha256=...`). The hub verifies this before processing any payload. Once all spoke reporters are confirmed live, set `PKDEV_HMAC_REQUIRED=true` in the hub's `.env` to enforce signatures on all inbound requests.
