# Contributing

## Dev setup

1. Clone and `cd` in.
2. `composer install` — required now, not optional (auth, `.env`, and all dev tooling depend on the Composer autoloader).
3. `cp application/config/database.php.example application/config/database.php` and `cp .env.example .env`, then fill in your local DB credentials (only needed if the feature you're working on touches the database — the homepage and `/example` don't).
4. `php index.php console migrate` and `php index.php console seed` if you need the `users` table / a login to test with.
5. Run it locally: `php -S localhost:8000` from the project root, or point Apache/Nginx/Docker at it (see `README.md`).
6. `ENVIRONMENT` defaults to `development` (see `index.php`) unless the `CI_ENV` var is set, so [DevelBar](application/third_party/DevelBar) shows automatically — use it to check queries, session state, and per-view memory while you work.

## Adding a feature

Prefer an HMVC module over adding to the flat `application/controllers/` tree — it keeps a feature's controller, model, and views together and portable. See `application/modules/example/` (no DB required, visit `/example`) or `application/modules/auth/` (a real one, with a model that connects to the database) for working references. Copy their layout:

```
application/modules/<name>/
  controllers/<Name>.php   — extends MX_Controller
  models/<name>_model.php  — extends CI_Model
  views/*.php
  config/                  (optional: routes.php, autoload.php)
```

If your model queries the database, load it in the model's own constructor (see `application/modules/auth/models/User_model.php`) rather than relying on the caller to pass a connect flag to `$this->load->model()` — easy to forget, and it fails at the first query, not at load time.

Delete `application/modules/example/` in your own fork/deployment once you don't need the reference anymore — it's not meant to ship in a real app.

## Code style

This project uses PHP-CS-Fixer (PSR-12) on the code it owns: `application/{controllers,core,models,helpers,hooks,libraries,modules,src}`. Run `composer cs-fix` before committing, or `composer cs-check` to see what would change without applying it. `defined('BASEPATH') OR exit('No direct script access allowed');` still belongs at the top of every PHP file (CI3 convention, not something CS-Fixer touches).

`system/` and `application/third_party/` are vendored/framework code, deliberately **excluded** from CS-Fixer's scope (see `.php-cs-fixer.dist.php`) — don't reformat them; it just creates noise and makes re-vendoring harder. If you need to patch something there (a PHP 8 compat fix, for instance), match the surrounding tabs-and-`array()` style already in that file instead of converting it to PSR-12.

## Static analysis

`composer analyze` runs PHPStan (level 3), scoped the same way as CS-Fixer. CodeIgniter 3's dynamic "super object" pattern (`$this->load`, `$this->db`, etc. attached at runtime, not declared) is invisible to PHPStan without help — see the `@property` docblocks on `CI_Controller`, `CI_Model`, `MX_Controller`, and individual controllers/models for the established pattern, and follow it for new dynamically-attached properties instead of reaching for `@phpstan-ignore`. If PHPStan flags something that's a genuine CI3-static-analysis-gap (not a real bug), a narrow, well-commented `ignoreErrors` entry in `phpstan.neon.dist` is the right tool — see the existing one for `CI_DB_query_builder::insert_id()` as a model for how to justify it.

## PHP compatibility

This starter targets PHP 8.1–8.5. If you touch anything in `system/core/*.php`, `system/database/DB_driver.php`, or `system/libraries/Driver.php` (re-vendoring a newer CodeIgniter 3 copy, for instance), re-check that `#[\AllowDynamicProperties]` is still present where it's needed — see the note in `README.md`. A clean homepage load doesn't prove PHP 8.2+ compatibility elsewhere: exercise every distinct code path at least once (DB connection, cache driver, CLI entry point, a real form POST) before assuming a re-vendor is clean. Avoid PHP 7-era patterns removed in PHP 8 (`each()`, `create_function()`, curly-brace string offsets, passing `null` to non-nullable internal function parameters).

## Testing changes

`composer test` runs the application-layer PHPUnit suite in `tests-app/` (unit tests plus HTTP-level feature tests that boot the app as a real subprocess) — separate from `tests/`, which is CodeIgniter 3's own framework test suite and unrelated to your app code. Before opening a PR:

- `composer analyze`, `composer cs-check`, and `composer test` all pass.
- Boot the app (`php -S localhost:8000`) and confirm the page(s) you touched load with no "A PHP Error was encountered" boxes (those indicate a live PHP notice/warning/deprecation, not just a cosmetic bug).
- If you touched HMVC loading, confirm both a direct module route (`/your-module`) and, if relevant, a `Modules::run()`/`$this->load->module()` call still work.
- If you touched auth, confirm register → login → logout end to end, and that the rate limiter still kicks in after repeated failed logins.
- If you touched DevelBar, confirm it still renders and that clicking through its sections doesn't throw JS errors in the browser console.
- If you added a migration, confirm `php index.php console migrate` applies it cleanly against an empty database.

## Pull requests

Keep PRs focused on one change. Note which of the checks above you ran manually (especially anything CI doesn't cover, like clicking through DevelBar in a browser).
