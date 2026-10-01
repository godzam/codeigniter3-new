# CodeIgniter 3 Starter — PHP 8.4+, HMVC, DevelBar

![CI](https://github.com/godzam/codeigniter3-new/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%E2%80%938.5-777bb4)
![License](https://img.shields.io/badge/license-MIT-blue)

A ready-to-run [CodeIgniter 3](https://codeigniter.com/userguide3/) starting point for people who want a **classic, lightweight PHP MVC framework** without fighting PHP 8 deprecation warnings, and without spending a few days wiring up modules, auth, a dev toolbar, and modern tooling by hand.

If you've ever done a fresh CodeIgniter 3 install on PHP 8.2+ and immediately seen a wall of `Deprecated: Creation of dynamic property` warnings — this fixes that, then builds out everything a real project ends up needing on top: modular code organization, login/registration, database migrations, static analysis, and a CI pipeline that actually proves it all works.

## Table of contents

- [What's included](#whats-included)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Project structure](#project-structure)
- [Working with HMVC modules](#working-with-hmvc-modules)
- [Template & theming](#template--theming)
- [Authentication](#authentication)
- [CLI console (migrations & seeders)](#cli-console-migrations--seeders)
- [DevelBar](#develbar)
- [Code quality tooling](#code-quality-tooling)
- [Docker](#docker)
- [Troubleshooting](#troubleshooting)
- [PHP 8.2+ compatibility notes](#php-82-compatibility-notes)
- [Contributing](#contributing)
- [License & credits](#license--credits)

## What's included

| | |
|---|---|
| **PHP 8.2–8.5 compatible** | CodeIgniter 3's "super object" pattern attaches things like `config`, `benchmark`, and the database driver to controllers/loader/router as properties at *runtime*, which PHP 8.2+ deprecates. This starter declares that pattern explicitly allowed (`#[\AllowDynamicProperties]`) across every core class that needs it, so nothing warns. |
| **HMVC (Modular Extensions)** | Instead of one flat `application/controllers` + `application/models` + `application/views`, group each feature into its own self-contained module: `application/modules/blog/{controllers,models,views}`. Modules can even call each other. A working example ships in `application/modules/example/`. |
| **Bootstrap 5 template & theming** | Public, auth, and admin layouts (Bootstrap 5.3, Bootstrap Icons, SweetAlert2 — all vendored, no CDN). An admin **Settings** page controls the primary/secondary colors, light/dark/auto mode, corner roundness, font, sidebar and navbar style, app name and logo — stored in the database, with a live preview. |
| **Authentication** | Email/password register, login, and logout (`application/modules/auth`), password hashing, session-backed `is_logged_in()`/`require_login()`/`require_role()` helpers, and per-IP login rate limiting. Not a full framework — a real, working starting point for your own auth. |
| **Database migrations & seeders** | CI3's built-in `Migration` library, enabled and wired to a CLI runner, plus a small seeder pattern CI3 doesn't ship with (`application/seeds/`). |
| **DevelBar** | A toolbar docked to the bottom of the page (development only) showing benchmarks, database queries, session data, loaded config, and a per-view memory breakdown. |
| **`.env` support** | Optional — every setting that reads from it has a hardcoded fallback, so nothing breaks if `.env` doesn't exist. |
| **Security defaults** | CSRF protection on, a conservative security-headers hook (CSP, X-Frame-Options, etc.), rate limiting available for any endpoint that needs it. |
| **Static analysis & style** | PHPStan (level 3, scoped to code this project owns) and PHP-CS-Fixer (PSR-12), both wired as `composer` scripts and in CI. |
| **Tests** | PHPUnit for the application layer — real unit tests plus HTTP-level feature tests that boot the app as a subprocess — separate from CodeIgniter's own framework test suite in `tests/`. |
| **Structured logging** | An opt-in Monolog-backed `Logger` library alongside CI3's native `log_message()`, for when you want multiple handlers or structured context. |
| **`/health` endpoint** | A JSON status endpoint for load balancers/uptime monitors. |
| **Docker** | `Dockerfile` + `docker-compose.yml` (app + MySQL + phpMyAdmin) for a one-command local environment. |
| **CI** | GitHub Actions: boots the app and hits real routes on PHP 8.1–8.4, plus separate PHPStan/CS-Fixer/PHPUnit jobs. Dependabot keeps dependencies current. |

None of the vendored pieces are exotic — HMVC and DevelBar are the same libraries ([wiredesignz HMVC](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) + [DevelBar](https://github.com/JCSama/CodeIgniter-develbar)) that have powered plenty of CodeIgniter 3 apps for years — just vendored in, patched for current PHP, and verified working together, with modern tooling and a real (if minimal) auth system layered on top.

## Requirements

- **PHP 8.1 or newer** (this repo is developed against 8.3/8.4 and targets 8.5)
- **[Composer](https://getcomposer.org/)** — required now (auth, `.env`, logging, and all dev tooling depend on it), unlike a bare CodeIgniter 3 install
- A web server (Apache, Nginx, [Laragon](https://laragon.org/), [XAMPP](https://www.apachefriends.org/)) — or nothing at all, PHP's built-in server works fine for local development
- MySQL/MariaDB, or any other database CodeIgniter 3's Query Builder supports — needed for auth/migrations; the homepage and `/example` module work without one

New to CodeIgniter? The [official CI3 user guide](https://codeigniter.com/userguide3/) is still the best reference for the parts of the framework this starter doesn't change (routing, Query Builder, form validation, etc.) — everything here is additive.

## Quick start

1. **Get the code and install dependencies.**
   ```bash
   git clone https://github.com/godzam/codeigniter3-new.git my-app
   cd my-app
   composer install
   ```

2. **Set up your database** (skip this if you're just poking around — the homepage and `/example` module don't need one; auth, migrations, and `/health` do):
   ```bash
   cp application/config/database.php.example application/config/database.php
   cp .env.example .env
   ```
   Edit `.env` with your DB credentials, or edit `application/config/database.php` directly — both work, `.env` just avoids editing a tracked-in-spirit config file. Then create the database and run:
   ```bash
   php index.php console migrate
   php index.php console seed
   ```
   The seeder creates a default admin (`admin@example.com` / `password`) so there's something to log in with — **change or remove that before deploying anywhere real.**

3. **Run it.** Pick one:

   - **Built-in PHP server** (fastest way to try it out, no Apache/Nginx setup needed):
     ```bash
     php -S localhost:8000
     ```
     Then open http://localhost:8000

   - **Apache/Nginx/Laragon/XAMPP** — point your web server's document root at this project's root folder (the one `index.php` lives in). A root `.htaccess` handles clean URLs already (needs `mod_rewrite`).

   - **Docker** — see [Docker](#docker) below.

4. **(Optional) Set your base URL.** If you're not using the built-in server on `localhost`, set `$config['base_url']` in `application/config/config.php` — otherwise CodeIgniter will auto-detect it, which usually works fine for local dev.

5. **Confirm it's working.** You should see the default CodeIgniter welcome page, with DevelBar docked at the bottom (development mode is on by default). Visit `/example` for a working HMVC module, `/register` to create an account, and `/health` for the JSON status check.

That's it — you're up and running. See [CONTRIBUTING.md](CONTRIBUTING.md) if you plan to extend this yourself.

## Project structure

```
application/
  config/
    database.php.example    — copy to database.php and fill in your credentials
    production/              — example of CI3's environment-specific config overrides
  core/
    MY_Loader.php            — extends MX_Loader (HMVC) + per-view memory tracking for DevelBar
    MY_Router.php            — extends MX_Router (HMVC)
  controllers/
    Console.php              — CLI-only: migrate, seed
    Health.php                — GET /health
  config/
    app_settings.php         — the settings schema (fields on the Settings page)
    menu.php                 — admin sidebar items
  helpers/
    auth_helper.php           — is_logged_in(), current_user(), require_login(), require_role()
    theme_helper.php           — theme CSS variables, asset_url(), flash(), menu_items()
  views/layouts/                — public / auth / admin layouts
  libraries/
    Settings.php                — DB-backed settings with defaults
    Template.php                 — $this->template->render('view', $data, 'admin')
    Ratelimiter.php            — fixed-window rate limiting (file cache, no Redis needed)
    Logger.php                  — opt-in Monolog logging
    Seeder.php                   — seeder runner (application/seeds/*_seeder.php)
  hooks/
    SecurityHeaders.php          — CSP, X-Frame-Options, etc.
  migrations/                     — CI3 migrations (enabled)
  seeds/                           — seeder classes
  modules/
    example/                       — working reference HMVC module, safe to delete
    auth/                            — register/login/logout
    dashboard/                        — landing page after login (admin layout)
    settings/                          — admin Settings page (/admin/settings)
  src/                                — Composer-autoloaded App\ namespace for plain PHP classes
  third_party/
    MX/                                — HMVC (Modular Extensions) library
    DevelBar/                           — dev toolbar
system/
  core/*.php, database/DB_driver.php, libraries/Driver.php
                                         — patched with #[AllowDynamicProperties] for PHP 8.2+
assets/                                  — css/, js/, and vendor/ (Bootstrap, Bootstrap Icons, SweetAlert2)
tests-app/                               — PHPUnit for application code (not CI3's own tests/)
phpstan.neon.dist, .php-cs-fixer.dist.php — static analysis / style config
Dockerfile, docker-compose.yml            — containerized dev environment
```

Everything else (`application/models`, `application/views`, `application/helpers`, etc.) works exactly like a normal, unmodified CodeIgniter 3 install — this starter only *adds* capability, it doesn't take anything away.

## Working with HMVC modules

`application/modules/example/` is a working, runnable reference — visit `/example` and read its three files:

- `controllers/Example.php`
- `models/Example_model.php`
- `views/index.php`

It deliberately doesn't touch the database, so it works right after clone with zero setup. Copy its layout to start a real module:

```
application/modules/blog/
  controllers/Blog.php
  models/Blog_model.php
  views/index.php
  config/                 (optional: routes.php, autoload.php, etc.)
```

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Blog extends MX_Controller
{
    public function index()
    {
        $this->load->view('index');
    }
}
```

A few things worth knowing:

- Hitting `/blog` routes straight to the module controller — no extra routing config needed.
- Call one module from another (or from inside a view) with `Modules::run('blog/index')`, or `$this->load->module('blog')` to load it as an object and call its methods directly.
- Cross-module loading works too: `$this->load->model('blog/blog_model')` loads a model that lives in a *different* module.
- A model that queries the database needs to load it itself — see `application/modules/auth/models/User_model.php`'s constructor for the pattern (don't rely on the caller remembering to pass a connect flag).
- Delete `application/modules/example/` once you don't need the reference anymore — it's a teaching example, not something meant to ship in a real app.

See [`application/third_party/MX/Controller.php`](application/third_party/MX/Controller.php) for the full API (autoloading per-module, view partials, etc.), or the [upstream fork's docs](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) for the original write-up.

## Template & theming

Pages are written as plain content views and wrapped in a layout:

```php
// in any controller (inside a module, 'index' resolves to that module's view first)
$this->template
    ->set_title('Users')
    ->set_breadcrumbs(['Home' => '', 'Users' => null])
    ->render('index', ['users' => $users], 'admin');   // 'public' | 'auth' | 'admin'
```

| Layout | For | Look |
|---|---|---|
| `public` | the website | top navbar, container, footer |
| `auth` | login, register, forgot password | centered card |
| `admin` | everything behind login | sidebar, top bar with breadcrumbs, user menu |

They live in `application/views/layouts/`; edit them freely. Add a sidebar item in `application/config/menu.php`:

```php
array('label' => 'Users', 'icon' => 'bi-people', 'url' => 'users', 'role' => 'admin'),
```

### The Settings page

Log in as an admin and open **/admin/settings** (run `php index.php console migrate` first so the `settings` table exists). Out of the box:

- **General** — application name, tagline, logo, footer text
- **Appearance** — primary and secondary color, default color mode (light / dark / auto), whether visitors get a light/dark switch, corner roundness, font
- **Layout** — full-width content, compact sidebar, navbar filled with the primary color

Hover/active shades, subtle backgrounds, readable text color, and the dark-mode variants are derived from the two colors you pick, so any color works. The panel on the right previews your changes before you save. Every setting has a default, so the site renders normally before a database exists.

**Add your own settings** — declare the field in `application/config/app_settings.php` and it appears in the form, validated, with no other code:

```php
$config['app_settings']['general']['fields']['support_email'] = array(
    'label' => 'Support email', 'type' => 'text', 'default' => '',
);
```

Types: `text`, `textarea`, `color`, `select`, `switch`, `number`, `password` (stored as plain text — for third-party keys, not user passwords). Read a value anywhere with `$this->settings->get('support_email')` or `app_setting('support_email')` in a view. A module can register its own group with `$this->settings->register('blog', [...])`.

### SweetAlert2

Loaded on every layout and themed to match the current color mode:

```php
flash('success', 'Saved!');            // in a controller, shown as a toast after the redirect
```
```js
App.toast('success', 'Done');          // from JavaScript
App.confirm({title: 'Delete it?'}).then(r => r.isConfirmed && ...);
```
```html
<a href="/users/5/delete" data-confirm="Delete this user?">Delete</a>     <!-- asks first -->
<form method="post" data-confirm="Send the invoice?">...</form>
```

### Assets

Bootstrap, Bootstrap Icons, and SweetAlert2 are vendored in `assets/vendor/` (no CDN, works offline) — to upgrade one, replace its folder with the new release's files. Your own CSS/JS go in `assets/css/app.css` and `assets/js/app.js`. `asset_url('css/app.css')` adds a cache-busting `?v=` so edits show up immediately.

## Authentication

`application/modules/auth` is a minimal but real email/password system:

- `GET`/`POST /register`, `GET`/`POST /login`, `GET /logout` (short URLs — see `application/config/routes.php`)
- Passwords hashed with `password_hash()`/`password_verify()`
- Login attempts throttled at 5 per 60 seconds per IP (`application/libraries/Ratelimiter.php`)
- A single `role` column on `users` (seeded default: `user`; the seeded admin gets `admin`) for basic access control

Use it from any controller via `application/helpers/auth_helper.php` (autoloaded — no `$this->load->helper()` needed):

```php
require_login();               // redirect to /login if not authenticated
require_role('admin');         // require_login() + 403 if the role doesn't match
is_logged_in();                // bool
current_user();                // array or null: id, name, email, role
has_role('admin');             // bool
```

The `users` table comes from `application/migrations/20260813120000_create_users_table.php` — run `php index.php console migrate` to create it. This is intentionally minimal (no password reset, no email verification, no OAuth) — extend `application/modules/auth/controllers/Auth.php` for anything beyond that.

## CLI console (migrations & seeders)

CodeIgniter 3 has no built-in artisan-like CLI. `application/controllers/Console.php` is the idiomatic CI3 way to get one — a normal controller invoked from the command line instead of over HTTP, refusing to run any other way:

```bash
php index.php console migrate          # run pending migrations
php index.php console seed             # run every application/seeds/*_seeder.php
php index.php console seed users       # run one seeder by name
```

Add a migration with CI3's normal `CI_Migration` API in `application/migrations/`. Add a seeder by creating `application/seeds/<name>_seeder.php` with a class extending `CI_Seeder` and implementing `run()` — see `application/seeds/users_seeder.php`.

## DevelBar

- Enabled by default whenever `ENVIRONMENT === 'development'` (that's the default — see `index.php` — unless a `CI_ENV` variable says otherwise). Toggle it in `application/third_party/DevelBar/config/develbar.php` (`$config['enable_develbar']`).
- You can extend the gate to also show DevelBar for your seeded admin account in production — see the comment in that config file for exactly how, using `has_role('admin')` from the auth helper (or your own check) rather than a guess.
- Sections available: Benchmarks, Memory Usage (with a per-view breakdown), Request, Database, Hooks, Models, Libraries, Helpers, Views, Config, Session, and Ajax (live request inspection).

## Code quality tooling

All wired as Composer scripts and run in CI:

```bash
composer analyze     # PHPStan (level 3), scoped to application/ code this project owns
composer cs-check     # PHP-CS-Fixer, dry run
composer cs-fix        # PHP-CS-Fixer, applies fixes
composer test            # PHPUnit — tests-app/, not CI3's own tests/
composer serve             # php -S localhost:8000
```

Scope is deliberate: `system/`, `application/third_party/`, and CI3's own `tests/` are vendored/framework code this project doesn't own — linting or type-checking it would just be noise and make it harder to diff against upstream if it's ever re-vendored. See `phpstan.neon.dist` and `.php-cs-fixer.dist.php` for the exact paths.

CodeIgniter 3's dynamic "super object" pattern means PHPStan needs a bit of help knowing what `$this->load`, `$this->db`, etc. actually are — see the `@property` docblocks on `CI_Controller`, `CI_Model`, `MX_Controller`, and individual controllers/models for how that's handled. Follow the same pattern in your own code and PHPStan stays useful instead of noisy.

## Docker

```bash
docker compose up --build
```

Starts the app on http://localhost:8080, MySQL on `3306`, and phpMyAdmin on http://localhost:8081 (user `root`, password `secret` — this is a local dev default, change it for anything beyond your own machine). The container copies `database.php.example` to `database.php` automatically and reads connection details from `docker-compose.yml`'s `environment:` block via the same `getenv()` fallback pattern `.env` uses locally — no extra config needed to get a working database inside the container.

Run migrations/seeders inside the running container:
```bash
docker compose exec app php index.php console migrate
docker compose exec app php index.php console seed
```

> **Note:** the Docker setup follows the same patterns verified elsewhere in this README (env-var-driven config, the migrate/seed CLI, the smoke-tested routes) but wasn't run end-to-end in a container during development of this starter — there was no Docker runtime available in that environment. Sanity-check it (`docker compose up --build`, then hit `/health`) before relying on it for anything real, and please open an issue if something doesn't match.

## Troubleshooting

**I see a blank page or a 500 error.**
Turn on PHP error display and check your PHP version (`php -v`) — this starter needs 8.1+. If you're on shared hosting, check its error log; `display_errors` is often off by default there.

**"Deprecated: Creation of dynamic property" warnings are back.**
This usually means `system/` got replaced with a fresh, unpatched CodeIgniter 3 download. See [PHP 8.2+ compatibility notes](#php-82-compatibility-notes) below.

**"Class not found" / autoload errors right after cloning.**
Run `composer install` — it's required now, not optional (auth, `.env`, and dev tooling all depend on the Composer autoloader).

**Database connection errors on a page that doesn't even use the database.**
Make sure `application/config/database.php` exists (step 2 of [Quick start](#quick-start)) — CodeIgniter fatals if a controller loads the `database` library and the config file is missing. Auth (`/login`, `/register`) and `/health` both load it.

**DevelBar isn't showing up.**
Check `ENVIRONMENT` — it only shows in `development` by default.

**Login says "Too many attempts."**
That's the rate limiter (5 attempts/60s per IP) — wait it out, or clear `application/cache/` locally.

**I want clean URLs without `index.php` in them.**
Already set up — a root `.htaccess` handles the rewrite, and `application/config/config.php` already has `index_page` set accordingly. If your server doesn't have `mod_rewrite`, set `$config['index_page'] = 'index.php'` there instead (see the comment in `.htaccess`).

## PHP 8.2+ compatibility notes

If you ever re-vendor a fresh copy of CodeIgniter 3's `system/` directory (upgrading to a newer point release, for instance), you'll need to reapply `#[\AllowDynamicProperties]` in three places — patches on top of upstream CI3, not part of it:

- All 15 classes in `system/core/*.php`: `URI`, `Router`, `Controller`, `Loader`, `Model`, `Input`, `Config`, `Exceptions`, `Hooks`, `Log`, `Utf8`, `Lang`, `Benchmark`, `Security`, `Output`
- `system/database/DB_driver.php`'s `CI_DB_driver` (covers every DB driver through inheritance) — only throws once something actually connects to the database, so it's easy to miss on a quick check
- `system/libraries/Driver.php`'s `CI_Driver_Library` (covers `CI_Cache` and any other driver-composite library) — only throws once a driver (e.g. the file cache used by `Ratelimiter`) actually loads

## Contributing

Bug reports, fixes, and improvements are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for dev setup, how to add a module, code style, and what to run before opening a PR.

## License & credits

CodeIgniter 3 itself is MIT-licensed (see [`license.txt`](license.txt)). The vendored libraries carry their own licenses — check their directories under `application/third_party/` before assuming terms:

- [HMVC / Modular Extensions](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) — originally by wiredesignz
- [DevelBar](https://github.com/JCSama/CodeIgniter-develbar) — by JCSama
