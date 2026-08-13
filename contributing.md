# Contributing

## Dev setup

1. Clone and `cd` in.
2. `cp application/config/database.php.example application/config/database.php` and fill in your local DB credentials (only needed if the feature you're working on touches the database).
3. Run it locally: `php -S localhost:8000` from the project root, or point Apache/Nginx at the project root.
4. `ENVIRONMENT` defaults to `development` (see `index.php`) unless the `CI_ENV` server var is set, so [DevelBar](application/third_party/DevelBar) shows automatically — use it to check queries, session state, and per-view memory while you work.

## Adding a feature

Prefer an HMVC module over adding to the flat `application/controllers/` tree — it keeps a feature's controller, model, and views together and portable. See `application/modules/example/` for a working, runnable reference (no DB required to try it — visit `/example`). Copy its layout:

```
application/modules/<name>/
  controllers/<Name>.php   — extends MX_Controller
  models/<name>_model.php  — extends CI_Model
  views/*.php
  config/                  (optional: routes.php, autoload.php)
```

Delete `application/modules/example/` in your own fork/deployment once you don't need the reference anymore — it's not meant to ship in a real app.

## Code style

Match the surrounding CodeIgniter 3 style already in the file you're editing: tabs for indentation, `defined('BASEPATH') OR exit('No direct script access allowed');` at the top of every PHP file, `snake_case` for methods/variables. Don't introduce a different formatting convention into an existing file.

## PHP compatibility

This starter targets PHP 8.1–8.5. If you touch anything in `system/core/*.php` (re-vendoring a newer CodeIgniter 3 copy, for instance), re-check that `#[\AllowDynamicProperties]` is still present on all 15 core classes — see the note in `README.md`. Avoid PHP 7-era patterns removed in PHP 8 (`each()`, `create_function()`, curly-brace string offsets, passing `null` to non-nullable internal function parameters).

## Testing changes

There's no automated test suite for the application layer (the `tests/` directory is CodeIgniter 3's own framework test suite, unrelated to your app code). Before opening a PR:

- Boot the app (`php -S localhost:8000`) and confirm the page(s) you touched load with no "A PHP Error was encountered" boxes (those indicate a live PHP notice/warning/deprecation, not just a cosmetic bug).
- If you touched HMVC loading, confirm both a direct module route (`/your-module`) and, if relevant, a `Modules::run()`/`$this->load->module()` call still work.
- If you touched DevelBar, confirm it still renders and that clicking through its sections doesn't throw JS errors in the browser console.

## Pull requests

Keep PRs focused on one change. Describe what you tested manually, since there's no CI test suite to lean on for the application layer.
