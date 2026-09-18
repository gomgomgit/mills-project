# e2e-web — browser tests for the Laravel/Livewire web app

317 Playwright tests across 68 spec files, covering the web screens
(login, master data, station data browsers/details/forms, dashboard,
Mills Setting, user management).

## Where these came from

They were converted on 2026-09-16 from `backend/tests/Browser/*.php`. Those
files held Playwright **JavaScript** behind a `<?php` tag and a PHP
docblock — 63 of the 68 were not even parseable as PHP — so they were never
registered in `phpunit.xml` and had **never run once**, while
`test-strategy.json`'s `done_definition` still demanded "All single-screen
browser tests pass" for every screen.

Three things had to change before a single one could run:

- `BASE_URL` was hardcoded to `http://localhost:8000` in all 68 copies. The
  dev server had since moved to 8001, so every spec would have failed at its
  first navigation. It is now `E2E_WEB_BASE_URL`, one place.
- Each file carried its own byte-identical copy of a `login()` helper.
  They now share `tests/support/auth.ts`.
- The accounts and records the specs reference had never existed anywhere.
  See the seeder below.

## Running them

The suite talks to a running app and a seeded database. It deliberately has
no Playwright `webServer` block: starting the app blindly would run tests
against whatever state your database happened to be in.

```bash
# 1. backend, from backend/
php artisan serve                         # note the port it picks
php artisan db:seed --class=BrowserTestFixtureSeeder

# 2. this suite, from e2e-web/
npm install
npx playwright install chromium           # first time only
npm test

# if the backend is not on 8000:
E2E_WEB_BASE_URL=http://localhost:8001 npm test
```

## Fixtures

`backend/database/seeders/BrowserTestFixtureSeeder.php` creates what the
specs assume: ~40 accounts (`<prefix>-admin01`, `<prefix>-supervisor01`,
password `Passw0rd!`), a `PL Mill A` production line carrying an active
station of all 18 types, a `PL Tanpa <Station>` line per family for the
"production line without this station" scenarios, and the pre-existing
`*-BROWSER-EDIT` records the edit flows open by name.

It is idempotent and is deliberately **not** wired into `DatabaseSeeder` —
it creates accounts with a known password, which has no business running as
part of a normal `db:seed`.

## Expect failures

These specs were written against an assumed UI and never verified, so some
encode selectors and flows that never matched the real app (the pilot
conversion found one expecting `.ef-field__error` where the form renders
`.pf-field__error`). A failure here means "this spec was never true", not
necessarily "the app is broken" — read each one before trusting it.
