# LeadBreave

LeadBreave is an internal tool for finding local businesses on Google Maps, auditing their websites and sending outreach emails with automatic follow-ups. It replaces the **LeadMap** WordPress plugin.

The workflow it supports:

1. Search Google Maps by industry and ZIP code.
2. Collect each business's name, phone, website, emails, social links and YouTube channel. Missing details can be entered by hand.
3. Audit the website (design, speed, SEO, backlinks). PageSpeed runs from a manual button.
4. Write an email with the major issues first and the minor issues below.
5. Send it.
6. Send an automatic follow-up after 2 business days if there is no reply.
7. Propose a meeting (Zoom, Google Meet or a booking link).

The build plan is in [PHASES.md](PHASES.md) and the original requirements are in [requiments.md](requiments.md). The plan has 19 phases. **Phases 0–2 are done** (foundation, app shell and design, core data model), so the app has login, users and roles, settings, the queue setup, the Aurora UI, and the database for leads and searches. Search (Phase 3) is next.

## Stack

| Part | Choice |
| --- | --- |
| Framework | Laravel 13, PHP 8.3+ |
| Admin UI | Filament 5 (Livewire), served at `/admin` |
| Roles and permissions | spatie/laravel-permission |
| Settings | spatie/laravel-settings (API keys are stored encrypted) |
| Queues | Redis + Laravel Horizon (`predis` client, so no PHP extension is needed) |
| Local services | Laravel Sail: MySQL 8.4, Redis, Mailpit |
| Tooling | Pest, Larastan (level 6), Pint, Telescope (local only) |

## Getting started

### Option A: Sail (Docker)

This is the full setup, with Redis, Horizon and Mailpit.

```sh
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan horizon
```

Open http://localhost. Mailpit is at http://localhost:8025.

If Docker says `permission denied` on `/var/run/docker.sock`, add yourself to the `docker` group (`sudo usermod -aG docker $USER`) and log in again.

### Option B: without Docker (Valet, `artisan serve`)

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Then edit `.env`:

- Point `DB_*` at your local MySQL (or set `DB_CONNECTION=sqlite`).
- Without Redis, set `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and `SESSION_DRIVER=database`. Horizon will not run in this mode.
- Set `APP_URL` to the address you open in the browser (for example `http://lead-breave.test` under Valet).

```sh
npm install && npm run build
php artisan migrate --seed
```

### Logging in

In `local` the seeder creates one user per role. The password for each is `password`.

| Email | Role |
| --- | --- |
| admin@example.com | admin |
| researcher@example.com | researcher |
| sender@example.com | sender |

In `local`, `migrate:fresh --seed` also loads demo data: 5 searches with 1,000 leads spread across the pipeline. To add the demo data without wiping anything, run `php artisan db:seed --class=DemoSeeder`.

In other environments the seeder only creates the roles. Create the first admin with tinker:

```sh
php artisan tinker --execute 'App\Models\User::create(["name" => "Admin", "email" => "you@example.com", "password" => "change-me"])->assignRole("admin");'
```

## Roles

| Role | Can |
| --- | --- |
| `admin` | Everything: manage users and settings, open Horizon and Telescope |
| `researcher` | Run searches, edit and audit leads |
| `sender` | Edit and audit leads, send outreach |

Roles and permissions are defined in [app/Enums/Role.php](app/Enums/Role.php) and [app/Enums/Permission.php](app/Enums/Permission.php). After changing them, run `php artisan db:seed --class=RolesAndPermissionsSeeder`. It is safe to run more than once. Users without a role cannot open the panel.

## Settings

Admins manage settings in the panel under **Settings**:

- **General**: company name, sender name and email, postal address (required in outreach footers), booking link and timezone.
- **API keys**: the keys are encrypted in the database and are never sent back to the browser. A blank field keeps the saved key.
  - **Google API key**: one Google Cloud key for lead search, geocoding and speed tests. In the Google Cloud console, enable **Places API (New)**, **Geocoding API** and **PageSpeed Insights API**, then restrict the key to those three APIs.
  - **Anthropic API key** (optional): used by "Draft with AI" on the audit screen.

In code, read them from the settings classes:

```php
app(App\Settings\ApiSettings::class)->google_api_key;
```

To add a setting, add a property to the class in [app/Settings](app/Settings) and a settings migration in [database/settings](database/settings).

## Queues

Each kind of work has its own queue and its own Horizon supervisor ([config/horizon.php](config/horizon.php)):

| Queue | Used for | Processes | Timeout |
| --- | --- | --- | --- |
| `search` | Google Places searches | 2 | 60s |
| `enrich` | Website crawls (emails, socials, SEO) | 1–5, auto-scaled | 120s |
| `pagespeed` | PageSpeed / Lighthouse runs | 1 | 180s |
| `mail` | Outreach emails | 1 | 60s |
| `default` | Everything else | 1–3 | 60s |

`REDIS_QUEUE_RETRY_AFTER` (240s) must stay above the longest timeout, or a slow job can run twice. A test checks this.

Horizon is at `/horizon` and Telescope at `/telescope`. Outside `local`, only admins can open them.

## Development

```sh
composer dev                                       # app server, queue, logs and Vite
php artisan test --compact                         # Pest
vendor/bin/pint                                    # format code
vendor/bin/phpstan analyse --memory-limit=1G       # Larastan, level 6
```

Tests use an in-memory SQLite database, so they need no MySQL or Redis. They do need built assets (`npm run build`), because the panel loads its theme through Vite.

The panel theme lives in [resources/css/filament/admin/theme.css](resources/css/filament/admin/theme.css), and the custom login, dashboard widgets and logo are under [resources/views/filament](resources/views/filament). After changing Tailwind classes in those views, rebuild with `npm run build` (or keep `npm run dev` running).

CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)) runs Pint, Larastan and Pest on every pull request and on pushes to `main`. Run all three before you push.

AI coding agents should follow the guidelines in [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md), which Laravel Boost generates.
