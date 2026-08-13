# CodeIgniter 3 Starter — PHP 8.4+, HMVC, DevelBar

A CodeIgniter 3 (3.2.0-dev) starting point that's ready for modern PHP out of the box:

- **PHP 8.2–8.5 compatible** — the framework's "super object" pattern (controllers/loader/router carrying dynamically-attached properties) is patched so it doesn't throw `Deprecated: Creation of dynamic property` warnings.
- **HMVC (Modular Extensions)** — build features as self-contained modules (`controllers/`, `models/`, `views/`, `config/` per module) instead of one flat `application/` tree.
- **DevelBar** — an in-page dev toolbar (benchmarks, queries, session, config, per-view memory usage) that only ever shows in development.

## Requirements

- PHP 8.1+ (developed against 8.3/8.4; targets 8.5)
- A web server (Apache/Nginx) or just `php -S` for local dev
- MySQL/MariaDB (or any driver CI3's Query Builder supports) if you use the database layer

## Getting started

1. Clone the repo and `cd` into it.
2. Copy the database config template and fill in your own credentials:
   ```
   cp application/config/database.php.example application/config/database.php
   ```
3. Point your web server's document root at the project root (`index.php` lives there), or run the built-in PHP server for local dev:
   ```
   php -S localhost:8000
   ```
4. Set `$config['base_url']` in `application/config/config.php` if you're not relying on auto-detection.
5. Visit the site. On `ENVIRONMENT === 'development'` (the default when the `CI_ENV` server var isn't set) you should see the DevelBar docked at the bottom of the page.

## Project layout

```
application/
  core/
    MY_Loader.php      — extends MX_Loader (HMVC) + per-view memory tracking for DevelBar
    MY_Router.php       — extends MX_Router (HMVC)
  modules/               — HMVC modules go here (empty scaffold)
  third_party/
    MX/                  — HMVC (Modular Extensions) library
    DevelBar/             — dev toolbar
system/
  core/*.php              — patched with #[AllowDynamicProperties] for PHP 8.2+
```

## Creating an HMVC module

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

- Hitting `/blog` routes straight to the module controller — no extra routing config needed.
- Call one module from another (or from a view) with `Modules::run('blog/index')`, or `$this->load->module('blog')` to load it as an object.
- Cross-module loading works too: `$this->load->model('blog/blog_model')`.

See the [Modular Extensions notes](application/third_party/MX/Controller.php) for the full API (autoloading per-module, view partials, etc.) — the original documentation lives at the [upstream fork](https://github.com/5112n4/wiredesignz-codeigniter-modular-extensions).

## DevelBar

- Enabled by default whenever `ENVIRONMENT === 'development'`; toggle it in `application/third_party/DevelBar/config/develbar.php` (`$config['enable_develbar']`).
- If your app has a real auth system, consider gating it on your super-admin session flag as well (see the comment in that config file) so it can also show for admins in production — don't do this until you have real auth, and use whatever your app's actual super-admin check is.
- Sections: Benchmarks, Memory Usage (with a per-view breakdown), Request, Database, Hooks, Models, Libraries, Helpers, Views, Config, Session, Ajax.

## PHP 8.2+ compatibility notes

If you ever re-vendor a fresh copy of CodeIgniter 3's `system/` directory, you'll need to reapply `#[\AllowDynamicProperties]` to the 15 classes in `system/core/*.php` (URI, Router, Controller, Loader, Model, Input, Config, Exceptions, Hooks, Log, Utf8, Lang, Benchmark, Security, Output) — it's not part of upstream CI3.

## License

CodeIgniter 3 itself is MIT-licensed (see `license.txt`). The vendored HMVC and DevelBar libraries carry their own licenses (see their respective directories under `application/third_party/`).
