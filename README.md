# CodeIgniter 3 Starter — PHP 8.4+, HMVC, DevelBar

![CI](https://github.com/godzam/codeigniter3-new/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%E2%80%938.5-777bb4)
![License](https://img.shields.io/badge/license-MIT-blue)

A ready-to-run [CodeIgniter 3](https://codeigniter.com/userguide3/) starting point for people who want a **classic, lightweight PHP MVC framework** without fighting PHP 8 deprecation warnings, and without spending a day wiring up modules and a dev toolbar by hand.

If you've ever done a fresh CodeIgniter 3 install on PHP 8.2+ and immediately seen a wall of `Deprecated: Creation of dynamic property` warnings — this fixes that, plus adds two things almost every real project ends up needing: a way to organize code into modules (HMVC), and an in-browser debug toolbar (DevelBar).

## Table of contents

- [What's included](#whats-included)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Project structure](#project-structure)
- [Working with HMVC modules](#working-with-hmvc-modules)
- [DevelBar](#develbar)
- [Troubleshooting](#troubleshooting)
- [PHP 8.2+ compatibility notes](#php-82-compatibility-notes)
- [Contributing](#contributing)
- [License & credits](#license--credits)

## What's included

| | |
|---|---|
| **PHP 8.2–8.5 compatible** | CodeIgniter 3's "super object" pattern attaches things like `config`, `benchmark`, and `uri` to controllers/loader/router as properties at *runtime*, which PHP 8.2+ deprecates. This starter declares that pattern explicitly allowed (`#[\AllowDynamicProperties]`) across every core class, so a fresh page load is warning-free. |
| **HMVC (Modular Extensions)** | Instead of one flat `application/controllers` + `application/models` + `application/views`, group each feature into its own self-contained module: `application/modules/blog/{controllers,models,views}`. Modules can even call each other. A working example ships in `application/modules/example/`. |
| **DevelBar** | A toolbar docked to the bottom of the page (development only) showing benchmarks, database queries, session data, loaded config, and a per-view memory breakdown — so you're not guessing what a request actually did. |

None of this is exotic — it's the same combination ([wiredesignz HMVC](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) + [DevelBar](https://github.com/JCSama/CodeIgniter-develbar)) that's powered plenty of CodeIgniter 3 apps for years — just vendored in, patched for current PHP, and verified working together.

## Requirements

- **PHP 8.1 or newer** (this repo is developed against 8.3/8.4 and targets 8.5)
- A web server (Apache, Nginx, [Laragon](https://laragon.org/), [XAMPP](https://www.apachefriends.org/)) — or nothing at all, PHP's built-in server works fine for local development
- MySQL/MariaDB, or any other database CodeIgniter 3's Query Builder supports — only if your app actually uses a database (the example module doesn't)

New to CodeIgniter? The [official CI3 user guide](https://codeigniter.com/userguide3/) is still the best reference for the parts of the framework this starter doesn't change (routing, models, form validation, etc.) — everything here is additive.

## Quick start

1. **Get the code.**
   ```bash
   git clone https://github.com/godzam/codeigniter3-new.git my-app
   cd my-app
   ```

2. **Set up your database config** (skip this if you're just poking around — the homepage and `/example` module don't need a database):
   ```bash
   cp application/config/database.php.example application/config/database.php
   ```
   Then open `application/config/database.php` and fill in your own `hostname`, `username`, `password`, and `database`.

3. **Run it.** Pick one:

   - **Built-in PHP server** (fastest way to try it out, no Apache/Nginx setup needed):
     ```bash
     php -S localhost:8000
     ```
     Then open http://localhost:8000

   - **Apache/Nginx/Laragon/XAMPP** — point your web server's document root at this project's root folder (the one `index.php` lives in), then visit whatever hostname you configured.

4. **(Optional) Set your base URL.** If you're not using the built-in server on `localhost`, set `$config['base_url']` in `application/config/config.php` — otherwise CodeIgniter will auto-detect it, which usually works fine for local dev.

5. **Confirm it's working.** You should see the default CodeIgniter welcome page, with the DevelBar toolbar docked at the bottom (development mode is on by default — see [DevelBar](#develbar)). Visit `/example` to see a working HMVC module in action.

That's it — you're up and running. See [CONTRIBUTING.md](CONTRIBUTING.md) if you plan to extend this yourself.

## Project structure

```
application/
  config/
    database.php.example    — copy to database.php and fill in your credentials
  core/
    MY_Loader.php            — extends MX_Loader (HMVC) + per-view memory tracking for DevelBar
    MY_Router.php            — extends MX_Router (HMVC)
  modules/
    example/                 — working reference HMVC module (see below), safe to delete
  third_party/
    MX/                      — HMVC (Modular Extensions) library
    DevelBar/                — dev toolbar
system/
  core/*.php                 — patched with #[AllowDynamicProperties] for PHP 8.2+
```

Everything else (`application/controllers`, `application/models`, `application/views`, `application/helpers`, etc.) works exactly like a normal, unmodified CodeIgniter 3 install — this starter only *adds* capability, it doesn't take anything away.

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
- Delete `application/modules/example/` once you don't need the reference anymore — it's a teaching example, not something meant to ship in a real app.

See [`application/third_party/MX/Controller.php`](application/third_party/MX/Controller.php) for the full API (autoloading per-module, view partials, etc.), or the [upstream fork's docs](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) for the original write-up.

## DevelBar

- Enabled by default whenever `ENVIRONMENT === 'development'` (that's the default — see `index.php` — unless a `CI_ENV` server variable says otherwise). Toggle it in `application/third_party/DevelBar/config/develbar.php` (`$config['enable_develbar']`).
- If your app grows a real auth system, you can extend the gate to also show DevelBar for a super-admin account in production — see the comment in that config file for exactly how, and use whatever your app's *actual* super-admin check is, not a guess.
- Sections available: Benchmarks, Memory Usage (with a per-view breakdown — click it to see which views ate the most memory), Request, Database, Hooks, Models, Libraries, Helpers, Views, Config, Session, and Ajax (live request inspection).

## Troubleshooting

**I see a blank page or a 500 error.**
Turn on PHP error display and check your PHP version (`php -v`) — this starter needs 8.1+. If you're on shared hosting, check its error log; `display_errors` is often off by default there.

**"Deprecated: Creation of dynamic property" warnings are back.**
This usually means `system/core/` got replaced with a fresh, unpatched CodeIgniter 3 download. See [PHP 8.2+ compatibility notes](#php-82-compatibility-notes) below.

**Database connection errors on a page that doesn't even use the database.**
Make sure `application/config/database.php` exists (step 2 of [Quick start](#quick-start)) — CodeIgniter fatals if a controller loads the `database` library and the config file is missing, even if that library call happens somewhere unexpected (an autoloaded library, for instance).

**DevelBar isn't showing up.**
Check `ENVIRONMENT` — it only shows in `development` by default. `index.php` sets this from the `CI_ENV` server variable if present, otherwise defaults to `development`.

**I want clean URLs without `index.php` in them.**
That's standard CodeIgniter 3 `.htaccess`/URL rewriting, unrelated to anything in this starter — see the [CI3 URLs guide](https://codeigniter.com/userguide3/general/urls.html).

## PHP 8.2+ compatibility notes

If you ever re-vendor a fresh copy of CodeIgniter 3's `system/` directory (upgrading to a newer point release, for instance), you'll need to reapply `#[\AllowDynamicProperties]` to these 15 classes in `system/core/*.php` — it's a patch on top of upstream CI3, not part of it:

`URI`, `Router`, `Controller`, `Loader`, `Model`, `Input`, `Config`, `Exceptions`, `Hooks`, `Log`, `Utf8`, `Lang`, `Benchmark`, `Security`, `Output`

## Contributing

Bug reports, fixes, and improvements are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for dev setup, how to add a module, code style, and what to manually verify before opening a PR (there's no automated test suite for the application layer).

## License & credits

CodeIgniter 3 itself is MIT-licensed (see [`license.txt`](license.txt)). The vendored libraries carry their own licenses — check their directories under `application/third_party/` before assuming terms:

- [HMVC / Modular Extensions](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions) — originally by wiredesignz
- [DevelBar](https://github.com/JCSama/CodeIgniter-develbar) — by JCSama
