# pkdev/reporter

Telemetry reporter for [PKDev](https://app.pkdev.dev) — sends heartbeats, queue snapshots, cron status, exceptions, and deployment events from your Laravel site to the PKDev control plane.

---

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

---

## Installation

```bash
composer require pkdev/reporter
```

Then deploy. Nothing goes in `.env`.

Within a minute of the deploy, the scheduler runs `pkdev:report`. With no key configured, it generates one, saves it to `storage/app/pkdev-site.key` (with the same group and mode as the site's `.env`), and asks PKDev to enroll the site. The request shows up in the PKDev Ops panel under **Sites → waiting for approval**. Approve it and the next heartbeat lands.

Enrollment is accepted only from a server registered in PKDev, and nothing reports until it is approved. The site needs a running Laravel scheduler (`schedule:run` in cron) and a correct `APP_URL`, which is how PKDev matches it to a site it already probes.

To use a key minted in the Ops panel instead, set `PKDEV_SITE_KEY`; the site then never enrolls. `php artisan pkdev:install` writes it to `.env` for you.

---

## What it does automatically

Once installed, the package hooks into your application with zero additional code:

| Feature | How |
|---|---|
| **Heartbeat + queue snapshot** | `pkdev:report` command scheduled every minute via the Laravel scheduler |
| **Cron monitoring** | Listens to the scheduler's finished, failed and background-finished events — every scheduled command reports one row per run, `success` or `failed`, including background tasks |
| **Exception reporting** | Hooks into Laravel's exception handler via `reportable()` — unhandled exceptions are reported with class, message, file, line, and trace |
| **Deployment notifications** | Via the included [Deployer](https://deployer.org) recipe (optional) |

---

## Environment variables

All optional.

| Variable | Default | Description |
|---|---|---|
| `PKDEV_ENABLED` | on when `APP_ENV=production`, off otherwise | Set `true` or `false` to override |
| `PKDEV_API_URL` | `https://app.pkdev.dev` | PKDev hub URL; `http://host.docker.internal:8090` for a hub in Sail |
| `PKDEV_SITE_KEY` | unset | A key minted in the Ops panel. Unset, the site generates and enrolls its own |
| `PKDEV_QUEUES` | `default` | Comma-separated queue names to monitor |

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

The recipe runs `php artisan pkdev:notify-deployment` on the server, so it uses the site's own key and hub URL. It hooks into `deploy:success` and `deploy:failed` and sends the branch, commit, deployer username, duration, and status to PKDev. It swallows its own failures, so it can never fail a deploy.

---

## Artisan commands

| Command | Description |
|---|---|
| `php artisan pkdev:install` | Sets up a key minted in the Ops panel — writes `.env`, tests connectivity |
| `php artisan pkdev:report` | Send a heartbeat + queue snapshot; generates a key and enrolls if there is none |
| `php artisan pkdev:test` | Send a test payload through every pipeline |
| `php artisan pkdev:ping` | Send a single connectivity test without writing anything |

---

## Local dev (Sail)

The reporter is off outside production. To report from a site in Sail to a PKDev hub in Sail, set:

```
PKDEV_ENABLED=true
PKDEV_API_URL=http://host.docker.internal:8090
```

`host.docker.internal` resolves to your host machine from inside the Sail container, allowing the spoke to reach the hub's exposed port.

---

## Security

Every request carries the site key as a bearer token and an HMAC body signature (`X-PKDev-Signature: sha256=...`). The key file is never more readable than the site's `.env`; if the scheduler's user can't give it the `.env` file's group, it falls back to `0644` and logs one warning. Exception and cron messages pass through a redactor that blanks `password=`, `*_KEY=`, bearer tokens and DSN passwords before they leave the site.
