# e2e-web — browser tests for the Laravel/Livewire web app

557 Playwright tests across 79 spec files (2026-10-04), covering the web screens
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

The suite talks to a running app and a seeded database — **its own
database**, `mill_smart_log_e2e`, never the dev database `mill_smart_log`.
Until 2026-10-04 it ran against the dev server and left its residue there
(faker corporates, fixture accounts, 282 Weighbridge rows dated year 7278).
It deliberately has no Playwright `webServer` block: you start the e2e
server yourself.

```bash
# 0. once: the e2e environment file + database
cp backend/.env.e2e.example backend/.env.e2e   # then fill APP_KEY / DB_* like backend/.env
createdb mill_smart_log_e2e                    # or: psql -c "CREATE DATABASE mill_smart_log_e2e"

# 1. reset + seed the e2e database (DESTRUCTIVE — migrate:fresh)
cd e2e-web
npm run db:prepare          # scripts/prepare-db.sh: refuses unless --env=e2e resolves to a *_e2e database

# 2. the e2e server, in its own terminal (leave the dev server on :8000 alone)
npm run serve               # cd ../backend && php artisan serve --env=e2e --port=8001

# 3. the suite
npm install
npx playwright install chromium           # first time only
npm test
```

What each piece does:

- `backend/.env.e2e` — a copy of `.env` with `APP_ENV=e2e`,
  `APP_URL=http://localhost:8001`, `DB_DATABASE=mill_smart_log_e2e` and
  `SANCTUM_STATEFUL_DOMAINS` on port 8001. Laravel loads it for every
  `--env=e2e` command. It must say `APP_ENV=e2e`: `php artisan serve` hands
  only `APP_ENV` to its child server process, which then picks its env file
  from that. `.env.e2e` is gitignored; `.env.e2e.example` is the committed
  template.
- `scripts/prepare-db.sh` — `migrate:fresh --seed` (DatabaseSeeder: grading
  parameters, operational targets, demo accounts and the demo station data
  the dashboards read) plus `BrowserTestFixtureSeeder`.
- `tests/support/global-setup.ts` — before every run it **asks the server who
  it is**, over HTTP: `GET /api/e2e/identity`, a route registered only when
  `APP_ENV=e2e` (`backend/routes/api.php`). A non-e2e server answers 404 and
  the run stops with a message naming the cause. That check exists because the
  older, config-only guard could be satisfied while a *different* server
  answered on the port: `php artisan serve` without `--env` raises its own port
  when 8000 is taken, so a `local` server on the dev database can occupy
  :8001. On 2026-10-06 exactly that happened — a 16.7-minute run pointed at the
  dev server and all 24 of its tests failed as login timeouts, with nothing
  naming the reason. The route reports `env` and `database` so the CLI path
  (which seeds) and the HTTP path (which the tests read) are proven to be the
  same database. The older checks remain as secondary ones: `--env=e2e` must
  resolve to a `*_e2e` database, and the suite's baseURL must equal that
  environment's `APP_URL` — `SANCTUM_STATEFUL_DOMAINS` is derived from
  `APP_URL`, so a mismatch turns every stateful `/api/*` call into a 401. Then
  it re-runs
  `BrowserTestFixtureSeeder`. The seeder is idempotent and restores what the
  specs consume (renamed `*-BROWSER-EDIT` records, deactivated accounts, the
  period fixture), so **a second run without `db:prepare` starts from the
  same fixture state**. `E2E_SKIP_SEED=1` skips the re-seed when iterating on
  one spec.
- `tests/support/global-teardown.ts` and the six `laporan-*` specs —
  `php artisan e2e:prune-records --force --env=e2e`, which deletes station
  records (Weighbridge included, by `record_datetime`) and Reporting Periods
  dated 1970-01-01..2019-12-31: the "lanes" the report specs plant their data
  in (`tests/support/period-lanes.ts`). The command refuses any environment
  other than `e2e` with a `*_e2e` database.

Overrides: `E2E_WEB_BASE_URL` (default `http://localhost:8001`) and
`E2E_BACKEND_ENV` (default `e2e`, the `--env` every artisan call uses).

If :8001 is already taken by another process, run the e2e server on a free
port and carry that port through all three settings at once — `APP_URL` and
`SANCTUM_STATEFUL_DOMAINS` for the server, `E2E_WEB_BASE_URL` for the suite:

```bash
APP_URL=http://localhost:8002 \
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8002,127.0.0.1,127.0.0.1:8002 \
  php artisan serve --env=e2e --port=8002        # in backend/

E2E_WEB_BASE_URL=http://localhost:8002 APP_URL=http://localhost:8002 npx playwright test
```

### Running one spec on its own

Every spec is meant to pass **alone, straight after `npm run db:prepare`** —
not only as part of a whole-suite run. Two cross-spec dependencies used to
break that, both found on 2026-10-06 and both fixed:

- The four newest report specs (`laporan-grading`, `laporan-threshing`,
  `laporan-pressing`, `laporan-depricarping`) do not plant their own period
  the way the six older ones do. They silently borrowed the `Prasyarat Form …`
  period that the 18 `form-*` specs plant — satisfied only because `form-*`
  sorts before `laporan-*` and `workers: 1`. Run alone on a fresh database,
  their period picker was empty, `[data-testid="coverage"]` never appeared and
  nearly every scenario failed as "element(s) not found", reading exactly like
  a broken screen. Each now calls `seedOpenPeriodForForms()` in its own
  `beforeAll`; the helper is idempotent and reuses an existing period, so
  nothing changes when `form-*` did run first.
- `laporan-grading` additionally needs **both** unit groups present — its two
  invariants are about bunches and kilograms never being summed. The fixture
  record `GR-BROWSER-EDIT` carried only a `kg` detail row, so the bunch group
  rendered `parameter-bunch-empty` and both scenarios failed unless
  `form-grading` had run first and left a bunch row behind.
  `BrowserTestFixtureSeeder::gradingEditRecord()` now plants a second detail
  row with `uom = bunch`.

Verified: from `db:prepare`, those four specs alone → 63 passed; the same four
plus `form-*`, `detail-grading`, `data-browser-grading` and
`audit-fix-20261005` in alphabetical order → 105 passed.

### Dates: the report specs live in the past

The server rejects station records dated later than tomorrow (WIB;
`EVENT_DATE_MAX_DAYS_AHEAD`, default 1). The rule stays ON in `.env.e2e` —
the suite should test the same server production runs. So the six
`laporan-*` specs, which used to plant their periods and records in year
2600+, now plant them in 1970–2019 (`laneIsoDate()` / `laneOffset()` in
`tests/support/period-lanes.ts`), one disjoint lane per spec. A Reporting
Period that frames station records can no longer be deleted (409
`PERIOD_HAS_RECORDS`), so those specs sweep their lane with
`e2e:prune-records` at the start of `beforeAll` and in `afterAll`, before the
by-prefix period cleanup.

Period specs that need a period framing records (`detail-periode-pelaporan`'s
"dialog tutup") use the seeded period `Fixture Periode Agustus 2026` in
`Mill Periode Uji` instead of creating and deleting one; the "Periode Terbuka
Hari Ini" panel scenarios create their today-period in that record-free mill,
because `BU Browser Test` holds the undeletable `Prasyarat Form …` period
(2020-01-01..today+7, `tests/support/period-fixture.ts`).

## Fixtures

`backend/database/seeders/BrowserTestFixtureSeeder.php` creates what the
specs assume: ~40 accounts (`<prefix>-admin01`, `<prefix>-supervisor01`,
password `Passw0rd!`), a `PL Mill A` production line carrying an active
station of all 18 types, a `PL Tanpa <Station>` line per family for the
"production line without this station" scenarios, and the pre-existing
`*-BROWSER-EDIT` records the edit flows open by name.

It also creates `Mill Periode Uji` (Sterilizer, Clarification and Effluent
Plant stations, one unverified Effluent Plant record on 2026-08-05 and the
period `Fixture Periode Agustus 2026` around it) for the Reporting Period specs.

It is idempotent and is deliberately **not** wired into `DatabaseSeeder` —
it creates accounts with a known password, which has no business running as
part of a normal `db:seed`.

## Expect failures

These specs were written against an assumed UI and never verified, so some
encode selectors and flows that never matched the real app (the pilot
conversion found one expecting `.ef-field__error` where the form renders
`.pf-field__error`). A failure here means "this spec was never true", not
necessarily "the app is broken" — read each one before trusting it.
