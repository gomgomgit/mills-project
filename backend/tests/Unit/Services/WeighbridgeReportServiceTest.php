<?php

/**
 * WeighbridgeReportServiceTest — screen-143--laporan-weighbridge-web /
 * usecase-146--laporan-weighbridge-web (Laporan Periode Weighbridge).
 *
 * One test per unit_test_case in the screen's tech spec (v2) — ALL 49, in the
 * spec's own order, one discrete `it()` each — against
 * App\Services\WeighbridgeReportService. Mirrors
 * tests/Unit/Services/StorageTankReportServiceTest.php (screen-133) in
 * structure and conventions.
 *
 * NUMBERING: the spec stores unit_test_cases as a zero-based JSON array; the
 * `case N` labels below are ONE-BASED, like every sibling report test file. So
 * the spec's unit_test_cases[8] is `case 9` here, [9] is `case 10`, and so on.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY: tests/Pest.php
 * binds Tests\TestCase only to Feature/, so without this line the Unit suite
 * has no application container at all — auth()->user(), Eloquent and the
 * factories would every one of them blow up (and $this->faker inside the
 * factories resolves to a provider-less Generator, whose first symptom is
 * `Unknown format "city"` from BusinessUnitFactory).
 *
 * ======================================================================
 * WHAT MAKES THIS REPORT'S TRAPS DIFFERENT FROM THE OTHER FIVE
 * ======================================================================
 * The five earlier station reports summarise READINGS taken on a schedule.
 * This one summarises TRANSACTIONS, and every trap below follows from two
 * consequences of that:
 *
 *  1. TWO FLOWS THAT ARE NEVER SUMMED. `receive` (FFB arriving) and
 *     `dispatch` (shipments leaving) answer different questions and their
 *     load units are not even comparable. Case 20 asserts the absence of a
 *     combined figure STRUCTURALLY — the exact top-level key set, plus
 *     neither group carrying the other's breakdown key — because "there is no
 *     such number" cannot be proven by looking for a number.
 *
 *  2. NO IN-PLANT DURATION ANYWHERE, AND NOT BY OVERSIGHT. Migration
 *     2026_08_19_000010 merged arrival_datetime and dispatch_datetime into a
 *     single record_datetime and DROPPED both, so a trip carries exactly one
 *     timestamp and a duration cannot be computed at all. Case 41 asserts
 *     that absence over the EXPORT HEADER structurally: no duration-named
 *     column, and EXACTLY ONE timestamp column. A text search for "durasi"
 *     would be wrong — the screen deliberately EXPLAINS the absence in prose.
 *
 *  3. EVERY METRIC HAS ITS OWN DENOMINATOR, AND NULL IS NEVER ZERO. A trip
 *     may be weighed in (gross) and not yet weighed out (tare), so its
 *     net_weight is NULL: it counts in trip_count, counts in
 *     missing_net_weight_trip_count, and is absent from the total and the
 *     average. Cases 21-24 and 44 pin all four halves of that.
 *
 * ----------------------------------------------------------------------
 * HOW A NULL net_weight IS SEEDED — READ THIS BEFORE ADDING A FIXTURE
 * ----------------------------------------------------------------------
 * WeighbridgeRecord::booted() recomputes `net_weight = gross_weight -
 * tare_weight` on EVERY save whenever BOTH are non-null. So
 * `->create(['net_weight' => null])` is SILENTLY OVERWRITTEN by the
 * factory's own gross/tare pair and yields an ordinary weighed trip — a
 * fixture that looks like it tests the null branch and tests nothing.
 *
 * `gross_weight` is NOT NULL in the schema (2025_01_15_000007, never
 * ->change()d), so nulling gross and tare together is not an option either:
 * SQLite refuses the insert outright. The ONE shape that works is the shape
 * the domain actually produces — GROSS TAKEN, TARE NOT YET: tare_weight null
 * AND net_weight null, gross_weight left filled. That is what
 * weighbridgeReportTrip() does when it is handed net_weight null, and it is
 * the only reason the null-weight cases below can fail at all.
 *
 * ----------------------------------------------------------------------
 * WEIGHTS ARE RAW KILOGRAMS
 * ----------------------------------------------------------------------
 * No conversion happens anywhere in the service, and none is asserted. The
 * screen mock writes "ton"; the repo convention is kg (form-weighbridge and
 * data-browser-weighbridge both label the same column (kg)), and converting
 * on one screen only would make two screens quote different numbers for the
 * same row.
 *
 * ----------------------------------------------------------------------
 * CROSS-MILL SECURITY IS ASSERTED AT ITS THREE DIFFERENT SHAPES
 * ----------------------------------------------------------------------
 *   - resolveBusinessUnit() DISCARDS a bound caller's business_unit_id
 *     (cases 1, 48) — own mill, 200, a payload and NOT a refusal. A refusal
 *     would confirm the other mill exists.
 *   - resolveProductionLine() REFUSES another mill's line with 403 (case 7) —
 *     a concrete handle on another mill's data.
 *   - authorizePeriod() REFUSES another mill's period with 403 (case 13), and
 *     the ROLE GUARD RUNS BEFORE THE LOOKUP (case 14) so 403-vs-404 is never
 *     an existence oracle.
 *
 * A BOUND ACCOUNT WITH NO MILL IS 422, NEVER 403 (case 3) — corrected in spec
 * v2, and the reason is the one written in the service: incomplete input, not
 * refused access. The user is not being denied anything; their account's
 * master data is incomplete. The fail-closed half of that rule is asserted
 * with a SPY, because "the all-mills list was never built" is a claim about
 * something that did NOT happen, and only a recorded call count of zero
 * proves it.
 *
 * OPERATOR IS NOT ADMITTED ANYWHERE (cases 5, 14). There is no mobile
 * Weighbridge report yet (screen-144 is unbuilt), so unlike the five sibling
 * services this one has no Operator widening — and widening it would have to
 * touch routes/api.php, guardAccess() AND the mill-bound branch of
 * resolveBusinessUnit() together, or Operator falls into the Admin branch
 * where the client's business_unit_id IS honoured.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\WeighbridgeReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * THE SPY for cases 3 and 5.
 *
 * allBusinessUnits() is public on the service precisely so it can be
 * overridden here. Asserting only the 422 would pass just as happily against
 * an implementation that built the whole-mill list FIRST and threw
 * afterwards — and that implementation would be a cross-mill leak waiting for
 * one more refactor.
 */
class WeighbridgeReportAllBusinessUnitsSpy extends WeighbridgeReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One weighbridge_records row, with the weighing timestamp spelled out as a
 * POSITIONAL argument because it decides period membership and must never be
 * left to the factory's random default.
 *
 * `net_weight` is the ONE attribute this helper interprets rather than passes
 * through:
 *   - a number  -> gross/tare are derived so the model's `saving` hook
 *                  recomputes exactly that number instead of overwriting it;
 *   - null      -> tare_weight AND net_weight are nulled while gross_weight
 *                  stays filled. That is the only shape the schema allows
 *                  (gross_weight is NOT NULL) and the only shape the hook
 *                  leaves alone. See the file header.
 *
 * Pass $recordDatetime = null for an UNDATED trip — the row that cannot be
 * placed in any period and is counted only by undated_trip_count.
 */
function weighbridgeReportTrip(Station $station, ?string $recordDatetime, array $attributes = []): WeighbridgeRecord
{
    $net = array_key_exists('net_weight', $attributes) ? $attributes['net_weight'] : 1000.0;
    unset($attributes['net_weight']);

    $weights = $net === null
        ? ['gross_weight' => 12000.0, 'tare_weight' => null, 'net_weight' => null]
        : ['gross_weight' => (float) $net + 2000.0, 'tare_weight' => 2000.0, 'net_weight' => (float) $net];

    return WeighbridgeRecord::factory()->forStation($station)->create(array_merge([
        'record_datetime' => $recordDatetime,
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => 'Estate A',
        'destination' => null,
        'status' => RecordStatus::Saved,
    ], $weights, $attributes));
}

/** An ARUS MASUK trip: FFB arriving from an estate/supplier. */
function weighbridgeReportReceive(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    string $origin = 'Estate A',
    array $attributes = [],
): WeighbridgeRecord {
    return weighbridgeReportTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => $origin,
        'destination' => null,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** An ARUS KELUAR trip: a shipment leaving the mill for some destination. */
function weighbridgeReportDispatch(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    ?string $destination = 'Refinery X',
    array $attributes = [],
): WeighbridgeRecord {
    return weighbridgeReportTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_DISPATCH,
        'estate_supplier' => 'Estate A',
        'destination' => $destination,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** The by_origin entry for one estate/supplier label, or null. */
function weighbridgeReportOrigin(array $summary, string $label): ?array
{
    foreach ($summary[WeighbridgeReportService::FLOW_RECEIVE]['by_origin'] as $row) {
        if ($row['estate_supplier'] === $label) {
            return $row;
        }
    }

    return null;
}

/** The by_destination entry for one destination (pass null for the null group). */
function weighbridgeReportDestination(array $summary, ?string $label): ?array
{
    foreach ($summary[WeighbridgeReportService::FLOW_DISPATCH]['by_destination'] as $row) {
        if ($row['destination'] === $label) {
            return $row;
        }
    }

    return null;
}

/** trip_count of one hour bucket of one flow. */
function weighbridgeReportHour(array $summary, string $flow, int $hour): int
{
    return $summary[$flow]['hourly'][$hour]['trip_count'];
}

/** The daily row for one date, or null. */
function weighbridgeReportDaily(array $summary, string $date): ?array
{
    foreach ($summary['daily'] as $row) {
        if ($row['date'] === $date) {
            return $row;
        }
    }

    return null;
}

/** Every SQL statement run inside $callback. */
function weighbridgeReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

/** Every key name appearing ANYWHERE in a nested array, flattened. */
function weighbridgeReportAllKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = array_merge($keys, weighbridgeReportAllKeys($value));
        }
    }

    return array_values(array_unique($keys));
}

/** The body of a StreamedResponse, captured. STREAM ONCE per response. */
function weighbridgeReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** An already-captured CSV body split into non-empty lines. */
function weighbridgeReportCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** One parsed CSV row. */
function weighbridgeReportCsvRow(string $line): array
{
    return str_getcsv($line, ',', '"', '\\');
}

beforeEach(function () {
    $this->service = new WeighbridgeReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->weighbridge()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->weighbridge()->create();

    // PRODUCTION LINE IS A CHOSEN CONTEXT, NOT AN ACCOUNT BINDING — and
    // choosing one is MANDATORY on this report, unlike the five siblings. So
    // nearly every case below passes a line id explicitly.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — which is exactly why the
    // mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Alpha')
        ->create();
});

// =====================================================================
// GROUP A — WHICH MILL, WHICH LINE, WHICH PERIOD (cases 1-14)
// =====================================================================

// ---------------------------------------------------------------------
// Case 1 — a bound role's client-sent business_unit_id is DISCARDED
// ---------------------------------------------------------------------
it('case 1 — resolveBusinessUnit membuang business_unit_id kiriman klien untuk peran terikat mill', function () {
    $this->actingAs($this->supervisorA);

    // Not validated, not compared — discarded.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    // And BU-B never reaches a query builder: no statement carries its id as
    // a binding, which is the only way to prove "never passed" rather than
    // "passed and happened to return nothing".
    $bindings = [];

    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            $bindings[] = (string) $binding;
        }
    });

    $this->service->resolveBusinessUnit((string) $this->businessUnitB->id);

    expect($bindings)->not->toContain((string) $this->businessUnitB->id);
});

// ---------------------------------------------------------------------
// Case 2 — Admin's client-sent business_unit_id IS used
// ---------------------------------------------------------------------
it('case 2 — resolveBusinessUnit memakai business_unit_id kiriman klien untuk Admin', function () {
    $this->actingAs($this->admin);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);
});

// ---------------------------------------------------------------------
// Case 3 — a bound account with no mill: 422, NEVER 403, and fail CLOSED
//
// CORRECTED IN SPEC v2. business_logic 1, this unit test case, the /periods
// error_codes table and both api_test steps all used to say 403 FORBIDDEN.
// They were uniformly wrong, not ambiguous: 422 VALIDATION_ERROR is the right
// reading, for the reason written in the service — INCOMPLETE INPUT, NOT
// REFUSED ACCESS. Nothing is being denied to this user; the master data on
// their own account is unfinished. Hence the explicit `not 403` assertion.
// ---------------------------------------------------------------------
it('case 3 — resolveBusinessUnit menolak akun terikat mill yang millnya kosong: 422, bukan 403, dan daftar seluruh mill TIDAK PERNAH disusun', function () {
    $orphan = User::factory()->role(UserRole::MillManagement)->create(['business_unit_id' => null]);
    $this->actingAs($orphan);

    $spy = new WeighbridgeReportAllBusinessUnitsSpy;

    $thrown = null;
    $resolved = null;

    try {
        $resolved = $spy->resolveBusinessUnit(null);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->errors())->toHaveKey('business_unit_id');
    expect($thrown->errors()['business_unit_id'][0])
        ->toBe('Akun Anda belum terhubung ke mill. Hubungi Admin.');
    expect($resolved)->toBeNull();

    // 422, NOT 403: the account is unconfigured, not denied.
    expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
    expect($thrown->status)->toBe(422);

    // THE PROOF that it fails CLOSED. Offering the all-mills list as a way out
    // would turn one broken master-data row into a cross-mill leak.
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    // Same rule, same proof, on every entry point that resolves a mill.
    expect(fn () => $spy->listPeriods())->toThrow(ValidationException::class);
    expect(fn () => $spy->buildSummary($this->periodA, null, $this->lineA))->toThrow(ValidationException::class);
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// ---------------------------------------------------------------------
// Case 4 — businessUnitOptions returns the list for Admin
// ---------------------------------------------------------------------
it('case 4 — businessUnitOptions memulangkan daftar opsi untuk Admin', function () {
    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    // The whole master, ordered by name. Compared against the table rather
    // than a hardcoded count: several factories in beforeEach() create a
    // Business Unit of their own as a side effect (PeriodFactory's
    // `created_by` user brings one along), and pinning a literal 2 here would
    // make this case fail for a reason that has nothing to do with the picker.
    $expected = BusinessUnit::query()->orderBy('name')->pluck('name')->all();

    expect($options)->toHaveCount(count($expected));
    expect(array_column($options, 'name'))->toBe($expected);
    expect(array_column($options, 'name'))->toContain('Mill Alpha');
    expect(array_column($options, 'name'))->toContain('Mill Beta');

    foreach ($options as $option) {
        expect(array_keys($option))->toBe(['id', 'name']);
    }
});

// ---------------------------------------------------------------------
// Case 5 — businessUnitOptions refuses a mill-bound role, and Operator
// ---------------------------------------------------------------------
it('case 5 — businessUnitOptions menolak peran terikat mill walau middleware sudah meloloskannya, dan tidak menyentuh repo', function () {
    $spy = new WeighbridgeReportAllBusinessUnitsSpy;

    foreach ([$this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    }

    // OPERATOR TOO — and since the screen-144 widening (2026-10-05) this is
    // the sharpest assertion in the file about what did NOT change: Operator
    // is now admitted by guardAccess() and reaches every other entry point,
    // yet this one still refuses it. A role bound to one mill has no picker,
    // and handing it the list of every mill is exactly the leak the widening
    // had to avoid. The refusal lives in businessUnitOptions() itself, not in
    // guardAccess(), which is why widening the gate could not open it.
    $this->actingAs($this->operatorA);
    expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);

    // The refusal happens BEFORE the master is read: a filtered list of one
    // would still be a list, and a bound role has no picker to put it in.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// ---------------------------------------------------------------------
// Case 6 — resolveProductionLine accepts a line of the effective mill
// ---------------------------------------------------------------------
it('case 6 — resolveProductionLine menerima line milik mill yang berlaku', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->resolveProductionLine((string) $this->businessUnitA->id, $this->lineA))
        ->toBe($this->lineA);
});

// ---------------------------------------------------------------------
// Case 7 — resolveProductionLine refuses another mill's line with 403
// ---------------------------------------------------------------------
it('case 7 — resolveProductionLine menolak line milik mill lain: 403 FORBIDDEN dan nol kueri summary', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationB, '2026-09-04 08:00', 9999.0);

    expect(fn () => $this->service->resolveProductionLine((string) $this->businessUnitA->id, $this->lineB))
        ->toThrow(AuthorizationException::class);

    // The whole-summary path refuses identically, and no trip row is read on
    // the way out: there is a concrete handle on another mill's data here, so
    // it is refused outright rather than answered with an empty report.
    $queries = weighbridgeReportQueriesDuring(function () {
        try {
            $this->service->buildSummary($this->periodA, null, $this->lineB);
        } catch (AuthorizationException) {
            // expected
        }
    });

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('weighbridge_records');
    }
});

// ---------------------------------------------------------------------
// Case 8 — a missing production_line_id is 422, never an all-lines total
// ---------------------------------------------------------------------
it('case 8 — production_line_id tidak dikirim: 422 VALIDATION_ERROR dan TIDAK ADA fallback seluruh line', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    foreach ([null, ''] as $missing) {
        $thrown = null;
        $payload = null;

        try {
            $payload = $this->service->buildSummary($this->periodA, null, $missing);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->errors())->toHaveKey('production_line_id');
        expect($payload)->toBeNull();
    }

    // ZERO weighbridge_records queries run. A total mixing a dozen lines is
    // not a number anyone can act on, so silently widening the scope because a
    // parameter was forgotten is the worst of both worlds.
    $queries = weighbridgeReportQueriesDuring(function () {
        try {
            $this->service->buildSummary($this->periodA, null, null);
        } catch (ValidationException) {
            // expected
        }
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('weighbridge_records');
    }
});

// ---------------------------------------------------------------------
// Case 9 — listPeriods lists ONLY periods that register weighbridge
//
// CORRECTED IN SPEC v2. The old wording spoke of "a period that names no
// station type", a third state that has had no representation in the schema
// since migration 2026_09_26_000040 dropped periods.station_type. Coverage is
// now expressed by the EXISTENCE of a period_stations row per station type, so
// a period either registers weighbridge or it does not. There is no P2.
// ---------------------------------------------------------------------
it('case 9 — listPeriods hanya memulangkan periode yang MENDAFTARKAN weighbridge sebagai salah satu stasiunnya', function () {
    $this->actingAs($this->supervisorA);

    $p1 = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')->range('2026-10-01', '2026-10-31')
        ->open()->named('P1 Mendaftarkan Weighbridge')->create();

    $p3 = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')->range('2026-11-01', '2026-11-30')
        ->open()->named('P3 Hanya Boiler Room')->create();

    $ids = array_column($this->service->listPeriods(), 'id');

    expect($ids)->toContain((string) $p1->id);
    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $p3->id);
});

// ---------------------------------------------------------------------
// Case 10 — no period registers weighbridge: an EMPTY LIST, not an error
// ---------------------------------------------------------------------
it('case 10 — tidak ada periode yang mendaftarkan weighbridge: array data kosong, bukan galat dan bukan seluruh periode mill', function () {
    $this->actingAs($this->supervisorA);

    // Drop the only weighbridge coverage this mill has, leaving P3's
    // boiler-room-only period behind.
    PeriodStation::query()->where('period_id', $this->periodA->id)->delete();
    $this->periodA->delete();

    $p3 = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')->range('2026-11-01', '2026-11-30')
        ->open()->named('P3 Hanya Boiler Room')->create();

    $periods = $this->service->listPeriods();

    expect($periods)->toBe([]);
    // NOT every period of the mill — the one that exists is simply not offered.
    expect(Period::query()->where('business_unit_id', $this->businessUnitA->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------
// Case 11 — each option's status is its OWN weighbridge period_stations row
// ---------------------------------------------------------------------
it('case 11 — status tiap opsi diambil dari baris period_stations weighbridge-nya sendiri, dan status tidak pernah menyaring daftar', function () {
    $this->actingAs($this->supervisorA);

    // P1: weighbridge OPEN while another station type in the SAME period is
    // CLOSED — so a lookup that grabs "the period's status" picks the wrong row.
    $p1 = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-10-01', '2026-10-31')->named('P1 Weighbridge Terbuka')->create();
    PeriodStation::factory()->forPeriod($p1)->stationType('weighbridge')->open()->create();
    PeriodStation::factory()->forPeriod($p1)->stationType('boiler-room')->closed()->create();

    // P2: weighbridge CLOSED — still listed. The period lock governs WRITING
    // data, never READING a report.
    $p2 = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-11-01', '2026-11-30')->named('P2 Weighbridge Tertutup')->create();
    PeriodStation::factory()->forPeriod($p2)->stationType('weighbridge')->closed()->create();
    PeriodStation::factory()->forPeriod($p2)->stationType('boiler-room')->open()->create();

    $byId = collect($this->service->listPeriods())->keyBy('id');

    expect($byId)->toHaveKey((string) $p1->id);
    expect($byId)->toHaveKey((string) $p2->id);

    expect($byId[(string) $p1->id]['status'])->toBe('open');
    expect($byId[(string) $p2->id]['status'])->toBe('closed');

    // Never Period::$status — which does not exist any more and THROWS.
    expect(fn () => $p1->status)->toThrow(LogicException::class);

    // And the option carries the station type it is reporting, so the screen
    // can never attribute another station's status to this one.
    expect($byId[(string) $p1->id]['station_type'])->toBe('weighbridge');
    expect(array_keys($byId[(string) $p1->id]))->toBe([
        'id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label',
    ]);
});

// ---------------------------------------------------------------------
// Case 12 — authorizePeriod answers 404 for an id that does not exist
// ---------------------------------------------------------------------
it('case 12 — authorizePeriod memulangkan 404 NOT_FOUND ketika id periode tidak ada', function () {
    $this->actingAs($this->supervisorA);

    $thrown = null;

    try {
        $this->service->authorizePeriod((string) Str::uuid());
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ModelNotFoundException::class);
    expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 13 — another mill's period is 403 for a mill-bound caller
// ---------------------------------------------------------------------
it('case 13 — authorizePeriod memulangkan 403 FORBIDDEN untuk periode mill lain', function () {
    $this->actingAs($this->supervisorA);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->stationType('weighbridge')->range('2026-09-01', '2026-09-30')
        ->open()->named('Periode Beta')->create();

    weighbridgeReportReceive($this->stationB, '2026-09-04 08:00', 9999.0);

    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);

    // 403, NEVER 404: answering 404 for another mill's real id would make the
    // refusal double as an existence oracle.
    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->export((string) $periodB->id, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 14 — the ROLE GUARD runs BEFORE the period lookup
// ---------------------------------------------------------------------
it('case 14 — authorizePeriod menjalankan gate sesi SEBELUM lookup, sehingga keberadaan id tidak bocor', function () {
    // TAMU, bukan Operator. Sampai 2026-10-04 kasus ini memakai Operator
    // sebagai "pemanggil yang ditolak"; sejak perluasan screen-144 keempat
    // peran aplikasi diterima guardAccess(), sehingga tidak ada lagi peran
    // yang dapat memerankan peran itu. Yang tersisa — dan yang justru lebih
    // tajam, karena berlaku untuk siapa pun — adalah sesi yang tidak ada:
    // gate yang sama, cabang yang satu lapis lebih awal.
    //
    // Yang diasersi tetap sama: gate berjalan SEBELUM lookup periode, jadi
    // pemanggil yang ditolak tidak pernah belajar id mana yang ada.
    $missingId = (string) Str::uuid();

    $thrown = null;

    $queries = weighbridgeReportQueriesDuring(function () use ($missingId, &$thrown) {
        try {
            $this->service->authorizePeriod($missingId);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }
    });

    // UNAUTHENTICATED, not NOT_FOUND — the refused caller learns nothing about
    // which period ids exist.
    expect($thrown)->toBeInstanceOf(AuthenticationException::class);
    expect($thrown)->not->toBeInstanceOf(ModelNotFoundException::class);

    // And the lookup provably never ran.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
    }

    // The SAME id answers 404 for an ADMITTED caller — which is what makes the
    // 401 above an ordering assertion rather than a coincidence.
    $this->actingAs($this->supervisorA);
    expect(fn () => $this->service->authorizePeriod($missingId))->toThrow(ModelNotFoundException::class);
});

// =====================================================================
// GROUP B — WHICH ROWS BELONG TO THE PERIOD AND THE LINE (cases 15-20)
// =====================================================================

// ---------------------------------------------------------------------
// Case 15 — the line filter is the RECORD'S OWN column, never a join
// ---------------------------------------------------------------------
it('case 15 — penyaringan line memakai weighbridge_records.production_line_id, tanpa join ke stations', function () {
    $this->actingAs($this->supervisorA);

    $lineTwo = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);

    // R1 is written on PL-1 ...
    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    // ... and the station is LATER reassigned to PL-2. The trip must not move
    // with it: a report that joins through `stations` rewrites history every
    // time a station is relocated.
    $this->stationA->update(['production_line_id' => $lineTwo->id]);

    $queries = weighbridgeReportQueriesDuring(function () {
        $onOldLine = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($onOldLine['receive']['trip_count'])->toBe(1);
        expect($onOldLine['receive']['net_weight_total'])->toBe(1000.0);
    });

    $onNewLine = $this->service->buildSummary($this->periodA, null, (string) $lineTwo->id);
    expect($onNewLine['receive']['trip_count'])->toBe(0);
    expect($onNewLine['receive']['net_weight_total'])->toBeNull();

    $tripQueries = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'weighbridge_records')));

    expect($tripQueries)->not->toBeEmpty();

    foreach ($tripQueries as $sql) {
        expect($sql)->toContain('"weighbridge_records"."production_line_id"');
        // No join at all, and in particular none to `stations`.
        expect($sql)->not->toContain('join');
        expect($sql)->not->toContain('stations');
    }
});

// ---------------------------------------------------------------------
// Case 16 — the period range is INCLUSIVE at BOTH ends
// ---------------------------------------------------------------------
it('case 16 — rentang periode inklusif di KEDUA ujung pada record_datetime', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-01 00:05', 1000.0, 'Hari Pertama');
    weighbridgeReportReceive($this->stationA, '2026-09-30 23:50', 2000.0, 'Hari Terakhir');
    weighbridgeReportReceive($this->stationA, '2026-08-31 23:50', 9000.0, 'Sehari Sebelum');
    weighbridgeReportReceive($this->stationA, '2026-10-01 00:10', 8000.0, 'Sehari Sesudah');

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['trip_count'])->toBe(2);
    expect($summary['receive']['net_weight_total'])->toBe(3000.0);

    expect(array_column($summary['receive']['by_origin'], 'estate_supplier'))
        ->toBe(['Hari Terakhir', 'Hari Pertama']);

    // The outside days are excluded from EVERY figure, not merely from the total.
    expect(weighbridgeReportOrigin($summary, 'Sehari Sebelum'))->toBeNull();
    expect(weighbridgeReportOrigin($summary, 'Sehari Sesudah'))->toBeNull();
    expect(weighbridgeReportDaily($summary, '2026-09-01'))->not->toBeNull();
    expect(weighbridgeReportDaily($summary, '2026-09-30'))->not->toBeNull();
    expect(weighbridgeReportDaily($summary, '2026-08-31'))->toBeNull();
    expect(weighbridgeReportDaily($summary, '2026-10-01'))->toBeNull();
    expect($summary['completeness']['days_with_trip'])->toBe(2);
});

// ---------------------------------------------------------------------
// Case 17 — membership comes from record_datetime, never created_at
// ---------------------------------------------------------------------
it('case 17 — keanggotaan periode dari record_datetime, bukan created_at maupun waktu sinkronisasi', function () {
    $this->actingAs($this->supervisorA);

    // R1: weighed INSIDE the period, row written long AFTER it closed — the
    // late-synced trip from a phone that was offline.
    $r1 = weighbridgeReportReceive($this->stationA, '2026-09-10 08:00', 1000.0, 'Tersinkron Terlambat');
    $r1->forceFill(['created_at' => '2026-12-01 03:00:00', 'updated_at' => '2026-12-01 03:00:00'])->saveQuietly();

    // R2: weighed OUTSIDE the period, row written INSIDE the range.
    $r2 = weighbridgeReportReceive($this->stationA, '2026-07-10 08:00', 7000.0, 'Ditimbang Di Luar');
    $r2->forceFill(['created_at' => '2026-09-15 03:00:00', 'updated_at' => '2026-09-15 03:00:00'])->saveQuietly();

    $queries = weighbridgeReportQueriesDuring(function () {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($summary['receive']['trip_count'])->toBe(1);
        expect($summary['receive']['net_weight_total'])->toBe(1000.0);
        expect(weighbridgeReportOrigin($summary, 'Tersinkron Terlambat'))->not->toBeNull();
        expect(weighbridgeReportOrigin($summary, 'Ditimbang Di Luar'))->toBeNull();
    });

    foreach (array_filter($queries, fn ($sql) => str_contains($sql, 'weighbridge_records')) as $sql) {
        expect($sql)->toContain('record_datetime');
        expect($sql)->not->toContain('created_at');
        expect($sql)->not->toContain('synced_at');
    }
});

// ---------------------------------------------------------------------
// Case 18 — NULL record_datetime: out of every figure, counted on its own
// ---------------------------------------------------------------------
it('case 18 — baris record_datetime NULL dikecualikan dari SELURUH angka dan dihitung di undated_trip_count', function () {
    $this->actingAs($this->supervisorA);

    foreach (['2026-09-02 08:00', '2026-09-03 09:00', '2026-09-04 10:00', '2026-09-05 11:00'] as $dt) {
        weighbridgeReportReceive($this->stationA, $dt, 1000.0);
    }

    weighbridgeReportReceive($this->stationA, null, 5000.0, 'Tanpa Penanda Waktu');
    weighbridgeReportDispatch($this->stationA, null, 6000.0, 'Tujuan Tanpa Waktu');

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['trip_count'])->toBe(4);
    expect($summary['receive']['net_weight_total'])->toBe(4000.0);
    expect($summary['dispatch']['trip_count'])->toBe(0);
    expect($summary['dispatch']['net_weight_total'])->toBeNull();

    // Nowhere in a breakdown, nowhere in the hourly buckets, nowhere in daily.
    expect(weighbridgeReportOrigin($summary, 'Tanpa Penanda Waktu'))->toBeNull();
    expect(weighbridgeReportDestination($summary, 'Tujuan Tanpa Waktu'))->toBeNull();
    expect($summary['dispatch']['by_destination'])->toBe([]);
    expect(array_sum(array_column($summary['receive']['hourly'], 'trip_count')))->toBe(4);
    expect($summary['daily'])->toHaveCount(4);
    expect($summary['completeness']['days_with_trip'])->toBe(4);

    // Counted in exactly ONE place, so the difference against the Data Browser
    // can be EXPLAINED rather than merely noticed.
    expect($summary['undated_trip_count'])->toBe(2);

    // AND IT IS DELIBERATELY NOT PERIOD-SCOPED: without a timestamp a row
    // cannot be placed in any period, so there is no period to bound it by.
    // Every period of this line therefore reports the same number — correct
    // behaviour, not a bug.
    $otherPeriod = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')->range('2026-11-01', '2026-11-30')
        ->open()->named('Periode November')->create();

    expect($this->service->buildSummary($otherPeriod, null, $this->lineA)['undated_trip_count'])->toBe(2);
});

// ---------------------------------------------------------------------
// Case 19 — the split into receive / dispatch
// ---------------------------------------------------------------------
it('case 19 — summary memisah record menurut weighbridge_type menjadi kelompok receive dan dispatch', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 5) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0);
    }

    foreach (range(1, 3) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 2000.0);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['trip_count'])->toBe(5);
    expect($summary['dispatch']['trip_count'])->toBe(3);

    // Each with its OWN, independent metric set — and neither carrying the
    // other's breakdown key.
    expect($summary['receive']['net_weight_total'])->toBe(5000.0);
    expect($summary['dispatch']['net_weight_total'])->toBe(6000.0);
    expect($summary['receive'])->toHaveKey('by_origin');
    expect($summary['receive'])->not->toHaveKey('by_destination');
    expect($summary['dispatch'])->toHaveKey('by_destination');
    expect($summary['dispatch'])->not->toHaveKey('by_origin');
});

// ---------------------------------------------------------------------
// Case 20 — NOT ONE KEY TOTALS THE TWO FLOWS
//
// Asserted STRUCTURALLY, because "there is no such number" cannot be
// established by looking for a number: an exact top-level key set leaves no
// room for a combined key to hide, and the vocabulary scan catches one added
// under a different name.
// ---------------------------------------------------------------------
it('case 20 — summary tidak menghasilkan SATU PUN angka gabungan kedua arus', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-05 08:00', 2000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-04 14:00', 4000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // The complete top-level key set. draft_trip_count and undated_trip_count
    // are the only two counters spanning both flows, and they are COMPLETENESS
    // disclosures rather than metrics — they are deliberately NOT a sum of two
    // metric figures.
    expect(array_keys($summary))->toBe([
        'business_unit', 'production_line', 'period',
        'receive', 'dispatch',
        'draft_trip_count', 'undated_trip_count',
        'daily', 'daily_total', 'completeness',
    ]);

    foreach ([WeighbridgeReportService::FLOW_RECEIVE, WeighbridgeReportService::FLOW_DISPATCH] as $flow) {
        expect(array_keys($summary[$flow]))->toBe([
            'trip_count', 'net_weight_total', 'net_weight_avg', 'net_weight_trip_count',
            'missing_net_weight_trip_count', 'hourly', 'busiest_hour',
            'busiest_hour_trip_count', 'empty_hour_count',
            $flow === WeighbridgeReportService::FLOW_RECEIVE ? 'by_origin' : 'by_destination',
        ]);
    }

    // daily and daily_total keep the two flows in SEPARATE COLUMNS — there is
    // no fifth key adding them.
    expect(array_keys($summary['daily_total']))->toBe([
        'receive_trip_count', 'receive_net_weight_total',
        'dispatch_trip_count', 'dispatch_net_weight_total',
    ]);

    foreach ($summary['daily'] as $row) {
        expect(array_keys($row))->toBe([
            'date', 'receive_trip_count', 'receive_net_weight_total',
            'dispatch_trip_count', 'dispatch_net_weight_total',
        ]);
    }

    // No key anywhere in the payload reads like a cross-flow total.
    $keys = array_map('strtolower', weighbridgeReportAllKeys($summary));

    foreach ($keys as $key) {
        foreach (['combined', 'gabungan', 'grand', 'overall', 'both_flows', 'all_flows', 'total_trip', 'trip_total', 'net_weight_grand'] as $forbidden) {
            expect($key)->not->toContain($forbidden);
        }
    }

    // And the sum itself appears nowhere as a value: 3 trips / 7000 kg across
    // the flows are numbers this payload must not contain at the top level.
    $topLevelNumbers = array_filter($summary, fn ($value) => is_int($value) || is_float($value));

    expect(array_values($topLevelNumbers))->not->toContain(7000.0);
});

// =====================================================================
// GROUP C — WEIGHT FIGURES AND THEIR OWN DENOMINATORS (cases 21-24)
// =====================================================================

// ---------------------------------------------------------------------
// Case 21 — total / count / average come from the FILLED rows only
// ---------------------------------------------------------------------
it('case 21 — net_weight_total, net_weight_trip_count dan net_weight_avg hanya dari baris yang net_weight-nya terisi', function () {
    $this->actingAs($this->supervisorA);

    foreach ([1000.0, 2000.0, 3000.0] as $i => $net) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 2), $net);
    }

    // Two unfinished weighings: gross taken, tare not yet.
    weighbridgeReportReceive($this->stationA, '2026-09-06 08:00', null);
    weighbridgeReportReceive($this->stationA, '2026-09-07 08:00', null);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['net_weight_total'])->toBe(6000.0);
    expect($summary['receive']['net_weight_trip_count'])->toBe(3);
    // 6000 / 3, NOT 6000 / 5 — an unfinished weighing must not drag the
    // average down, it disappears from it.
    expect($summary['receive']['net_weight_avg'])->toBe(2000.0);
    expect($summary['receive']['net_weight_avg'])->not->toBe(1200.0);
});

// ---------------------------------------------------------------------
// Case 22 — average null (never 0) when nothing was weighed to the end
// ---------------------------------------------------------------------
it('case 22 — net_weight_avg null ketika net_weight_trip_count 0, dan total null bukan nol', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(2, 5) as $day) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $day), null);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['net_weight_avg'])->toBeNull();
    expect($summary['receive']['net_weight_total'])->toBeNull();
    expect($summary['receive']['net_weight_trip_count'])->toBe(0);

    // 0,0 would claim a measured total of nothing — a claim nobody made.
    expect($summary['receive']['net_weight_total'])->not->toBe(0.0);
    expect($summary['receive']['net_weight_avg'])->not->toBe(0.0);
});

// ---------------------------------------------------------------------
// Case 23 — trip_count counts the flow's rows INCLUDING the unweighed
// ---------------------------------------------------------------------
it('case 23 — trip_count menghitung seluruh baris arus itu termasuk yang net_weight NULL', function () {
    $this->actingAs($this->supervisorA);

    foreach ([1000.0, 2000.0, 3000.0] as $i => $net) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 2), $net);
    }

    weighbridgeReportReceive($this->stationA, '2026-09-06 08:00', null);
    weighbridgeReportReceive($this->stationA, '2026-09-07 08:00', null);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['trip_count'])->toBe(5);
    expect($summary['receive']['net_weight_trip_count'])->toBe(3);
    // The two numbers are deliberately different, and both are published.
    expect($summary['receive']['trip_count'])->not->toBe($summary['receive']['net_weight_trip_count']);
});

// ---------------------------------------------------------------------
// Case 24 — missing_net_weight_trip_count is PER FLOW, never merged
// ---------------------------------------------------------------------
it('case 24 — missing_net_weight_trip_count dihitung PER ARUS dan tidak pernah digabung jadi satu angka', function () {
    $this->actingAs($this->supervisorA);

    // receive: 5 rows, 2 unweighed.
    foreach ([1000.0, 2000.0, 3000.0, null, null] as $i => $net) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 2), $net);
    }

    // dispatch: 3 rows, 1 unweighed.
    foreach ([4000.0, 5000.0, null] as $i => $net) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i + 2), $net);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['missing_net_weight_trip_count'])->toBe(2);
    expect($summary['dispatch']['missing_net_weight_trip_count'])->toBe(1);

    // NOT merged: 3 appears nowhere as a single missing-weight figure.
    expect($summary)->not->toHaveKey('missing_net_weight_trip_count');
});

// =====================================================================
// GROUP D — THE HOUR-OF-DAY DISTRIBUTION (cases 25-28)
// =====================================================================

// ---------------------------------------------------------------------
// Case 25 — 24 buckets, always, even at zero
// ---------------------------------------------------------------------
it('case 25 — hourly selalu 24 ember 0..23, termasuk jam bernilai nol', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 07:10', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-04 07:50', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-04 13:05', 1000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $hourly = $summary['receive']['hourly'];

    expect($hourly)->toHaveCount(24);
    expect(array_column($hourly, 'hour'))->toBe(range(0, 23));

    expect(weighbridgeReportHour($summary, 'receive', 7))->toBe(2);
    expect(weighbridgeReportHour($summary, 'receive', 13))->toBe(1);

    // Two hours carry trips (7 and 13), so the remaining 22 are rendered at
    // zero rather than dropped — an hour missing from the list would read as
    // an hour that does not exist.
    $zeroHours = array_values(array_filter($hourly, fn ($row) => $row['trip_count'] === 0));
    expect($zeroHours)->toHaveCount(22);
    expect($summary['receive']['empty_hour_count'])->toBe(22);

    // The 24 counts sum EXACTLY to the flow's trip_count.
    expect(array_sum(array_column($hourly, 'trip_count')))->toBe($summary['receive']['trip_count']);
});

// ---------------------------------------------------------------------
// Case 26 — each trip lands in the hour of ITS OWN timestamp
// ---------------------------------------------------------------------
it('case 26 — tiap record masuk ke ember jam dari record_datetime-nya sendiri dan tidak ke ember lain', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 23:59', 1000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(weighbridgeReportHour($summary, 'receive', 23))->toBe(1);

    foreach (range(0, 22) as $hour) {
        expect(weighbridgeReportHour($summary, 'receive', $hour))->toBe(0);
    }
});

// ---------------------------------------------------------------------
// Case 27 — busiest_hour and empty_hour_count
// ---------------------------------------------------------------------
it('case 27 — busiest_hour adalah jam dengan trip terbanyak, empty_hour_count jumlah jam bernilai nol, dan seri resolve ke jam PALING AWAL', function () {
    $this->actingAs($this->supervisorA);

    foreach (['07:05', '07:25', '07:45'] as $time) {
        weighbridgeReportReceive($this->stationA, '2026-09-04 '.$time, 1000.0);
    }

    weighbridgeReportReceive($this->stationA, '2026-09-04 13:05', 1000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['busiest_hour'])->toBe(7);
    expect($summary['receive']['busiest_hour_trip_count'])->toBe(3);
    expect($summary['receive']['empty_hour_count'])->toBe(22);

    // A TIE resolves to the EARLIEST hour, so the answer is deterministic
    // rather than dependent on iteration order.
    weighbridgeReportReceive($this->stationA, '2026-09-05 13:15', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-05 13:35', 1000.0);

    $tied = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(weighbridgeReportHour($tied, 'receive', 7))->toBe(3);
    expect(weighbridgeReportHour($tied, 'receive', 13))->toBe(3);
    expect($tied['receive']['busiest_hour'])->toBe(7);
    expect($tied['receive']['busiest_hour_trip_count'])->toBe(3);
});

// ---------------------------------------------------------------------
// Case 28 — busiest_hour null (not 0) when the flow has no dated trip
// ---------------------------------------------------------------------
it('case 28 — busiest_hour null ketika arus itu tidak punya satu pun trip bertanggal', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 07:10', 1000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // null, NOT 0 — hour 0 is a real hour, and naming it would claim a peak
    // nobody measured.
    expect($summary['dispatch']['busiest_hour'])->toBeNull();
    expect($summary['dispatch']['busiest_hour_trip_count'])->toBe(0);
    expect($summary['dispatch']['empty_hour_count'])->toBe(24);
    expect($summary['dispatch']['hourly'])->toHaveCount(24);
});

// =====================================================================
// GROUP E — THE TWO BREAKDOWNS (cases 29-32)
// =====================================================================

// ---------------------------------------------------------------------
// Case 29 — by_origin ordered by net_weight_total DESCENDING
// ---------------------------------------------------------------------
it('case 29 — by_origin dikelompokkan per estate_supplier dan diurutkan net_weight_total menurun', function () {
    $this->actingAs($this->supervisorA);

    // Estate A 9000, Supplier B 15000, Estate C 3000 — deliberately NOT in
    // alphabetical order and NOT in insertion order, so neither can produce
    // this result by accident.
    weighbridgeReportReceive($this->stationA, '2026-09-02 08:00', 4000.0, 'Estate A');
    weighbridgeReportReceive($this->stationA, '2026-09-03 08:00', 5000.0, 'Estate A');
    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 15000.0, 'Supplier B');
    weighbridgeReportReceive($this->stationA, '2026-09-05 08:00', 3000.0, 'Estate C');

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(array_column($summary['receive']['by_origin'], 'estate_supplier'))
        ->toBe(['Supplier B', 'Estate A', 'Estate C']);

    // Each entry with its own trip_count and net_weight_total — plus the
    // per-entry denominator net_weight_trip_count, which is a FOURTH key the
    // response schema's prose does not spell out.
    expect(weighbridgeReportOrigin($summary, 'Estate A'))->toBe([
        'estate_supplier' => 'Estate A',
        'trip_count' => 2,
        'net_weight_total' => 9000.0,
        'net_weight_trip_count' => 2,
    ]);
    expect(weighbridgeReportOrigin($summary, 'Supplier B')['net_weight_total'])->toBe(15000.0);
    expect(weighbridgeReportOrigin($summary, 'Estate C')['trip_count'])->toBe(1);
});

// ---------------------------------------------------------------------
// Case 30 — by_origin is NEVER truncated, and has no 'others' bucket
// ---------------------------------------------------------------------
it('case 30 — by_origin tidak pernah dipangkas dan tidak pernah punya ember lain-lain', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-02 08:00', 50000.0, 'Estate Dominan');

    foreach (range(1, 11) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 09:00', $i + 2), 100.0, 'Supplier Kecil '.$i);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['by_origin'])->toHaveCount(12);
    expect($summary['receive']['by_origin'][0]['estate_supplier'])->toBe('Estate Dominan');

    // Trimming the list makes the smallest supplier vanish from the report,
    // and the counts then stop summing to the headline figure.
    expect(array_sum(array_column($summary['receive']['by_origin'], 'trip_count')))
        ->toBe($summary['receive']['trip_count']);

    foreach (array_column($summary['receive']['by_origin'], 'estate_supplier') as $label) {
        expect(strtolower($label))->not->toContain('lain-lain');
        expect(strtolower($label))->not->toContain('others');
    }

    foreach (range(1, 11) as $i) {
        expect(weighbridgeReportOrigin($summary, 'Supplier Kecil '.$i))->not->toBeNull();
    }
});

// ---------------------------------------------------------------------
// Case 31 — by_destination ordered descending, NULL as a group of its own
// ---------------------------------------------------------------------
it('case 31 — by_destination diurutkan menurun dan destination NULL membentuk SATU kelompok tersendiri yang tidak dibuang', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportDispatch($this->stationA, '2026-09-02 14:00', 20000.0, 'Refinery X');
    weighbridgeReportDispatch($this->stationA, '2026-09-03 14:00', 8000.0, 'Port Y');

    foreach ([2000.0, 2000.0, 1000.0] as $i => $net) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 15:00', $i + 4), $net, null);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(array_column($summary['dispatch']['by_destination'], 'destination'))
        ->toBe(['Refinery X', 'Port Y', null]);

    $nullGroup = weighbridgeReportDestination($summary, null);

    expect($nullGroup)->not->toBeNull();
    expect($nullGroup['trip_count'])->toBe(3);
    expect($nullGroup['net_weight_total'])->toBe(5000.0);
    // The key is literal null — not an empty string, and not a made-up label.
    // The screen labels it "Belum diisi"; the service invents no destination
    // that was never recorded.
    expect($nullGroup['destination'])->toBeNull();
    expect($nullGroup['destination'])->not->toBe('');
});

// ---------------------------------------------------------------------
// Case 32 — the by_destination trip counts sum EXACTLY to dispatch.trip_count
// ---------------------------------------------------------------------
it('case 32 — jumlah trip_count seluruh entri by_destination sama persis dengan dispatch.trip_count', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 6) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 3000.0, 'Refinery X');
    }

    foreach (range(1, 4) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 15:00', $i + 10), 1000.0, null);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['dispatch']['trip_count'])->toBe(10);
    expect(array_sum(array_column($summary['dispatch']['by_destination'], 'trip_count')))->toBe(10);
    expect(weighbridgeReportDestination($summary, null)['trip_count'])->toBe(4);
});

// =====================================================================
// GROUP F — DRAFT ROWS, DAILY RECAP AND COMPLETENESS (cases 33-38)
// =====================================================================

// ---------------------------------------------------------------------
// Case 33 — draft_trip_count over draft_ongoing + draft_paused
// ---------------------------------------------------------------------
it('case 33 — draft_trip_count menghitung baris berstatus draft_ongoing atau draft_paused', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-02 08:00', 1000.0, 'Estate A', ['status' => RecordStatus::DraftOngoing]);
    weighbridgeReportReceive($this->stationA, '2026-09-03 08:00', 1000.0, 'Estate A', ['status' => RecordStatus::DraftOngoing]);
    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate A', ['status' => RecordStatus::DraftPaused]);

    foreach (range(5, 9) as $day) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $day), 1000.0, 'Estate A', ['status' => RecordStatus::Saved]);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['draft_trip_count'])->toBe(3);
    // A single number spanning BOTH flows, exactly as the response schema
    // declares it — and a completeness disclosure rather than a metric.
    expect($summary['draft_trip_count'])->toBeInt();
});

// ---------------------------------------------------------------------
// Case 34 — draft rows still count in EVERY other figure
// ---------------------------------------------------------------------
it('case 34 — baris draft TETAP ikut di seluruh angka lain: nol filter status pada kueri metrik', function () {
    $this->actingAs($this->supervisorA);

    foreach ([RecordStatus::DraftOngoing, RecordStatus::DraftOngoing, RecordStatus::DraftPaused, RecordStatus::Saved] as $i => $status) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 2), 1000.0, 'Estate A', ['status' => $status]);
    }

    $queries = weighbridgeReportQueriesDuring(function () {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($summary['receive']['trip_count'])->toBe(4);
        expect($summary['receive']['net_weight_total'])->toBe(4000.0);
        expect($summary['receive']['net_weight_trip_count'])->toBe(4);
        expect($summary['draft_trip_count'])->toBe(3);
        expect(weighbridgeReportOrigin($summary, 'Estate A')['trip_count'])->toBe(4);
    });

    // No status filter is applied to the metric queries at all.
    foreach (array_filter($queries, fn ($sql) => str_contains($sql, 'weighbridge_records')) as $sql) {
        expect($sql)->not->toContain('"status"');
    }
});

// ---------------------------------------------------------------------
// Case 35 — daily grouped by the DATE PART, four numbers per date
// ---------------------------------------------------------------------
it('case 35 — daily dikelompokkan per bagian tanggal record_datetime dengan keempat angka per tanggal', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-01 08:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-01 09:00', 2000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-01 14:00', 5000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-02 08:00', 3000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['daily'])->toHaveCount(2);

    expect(weighbridgeReportDaily($summary, '2026-09-01'))->toBe([
        'date' => '2026-09-01',
        'receive_trip_count' => 2,
        'receive_net_weight_total' => 3000.0,
        'dispatch_trip_count' => 1,
        'dispatch_net_weight_total' => 5000.0,
    ]);

    // A date whose other flow has no trip gets 0 trips and a NULL weight —
    // never 0.0, which would claim a measured total of nothing.
    expect(weighbridgeReportDaily($summary, '2026-09-02'))->toBe([
        'date' => '2026-09-02',
        'receive_trip_count' => 1,
        'receive_net_weight_total' => 3000.0,
        'dispatch_trip_count' => 0,
        'dispatch_net_weight_total' => null,
    ]);
});

// ---------------------------------------------------------------------
// Case 36 — daily_total sums every daily row, flows still separate
// ---------------------------------------------------------------------
it('case 36 — daily_total menjumlah seluruh baris daily dan kedua arus tetap terpisah', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-01 08:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-01 09:00', 2000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-01 14:00', 5000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-02 08:00', 3000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['daily_total'])->toBe([
        'receive_trip_count' => 3,
        'receive_net_weight_total' => 6000.0,
        'dispatch_trip_count' => 1,
        'dispatch_net_weight_total' => 5000.0,
    ]);

    // By construction the footer agrees with the headline cards, which is what
    // lets a reader check the table against them.
    expect($summary['daily_total']['receive_trip_count'])->toBe($summary['receive']['trip_count']);
    expect($summary['daily_total']['receive_net_weight_total'])->toBe($summary['receive']['net_weight_total']);
    expect($summary['daily_total']['dispatch_trip_count'])->toBe($summary['dispatch']['trip_count']);
    expect($summary['daily_total']['dispatch_net_weight_total'])->toBe($summary['dispatch']['net_weight_total']);

    // And no fifth key adds them together.
    expect($summary['daily_total'])->not->toHaveKey('trip_count');
    expect($summary['daily_total'])->not->toHaveKey('net_weight_total');
});

// ---------------------------------------------------------------------
// Case 37 — days_in_period is the INCLUSIVE calendar day count
// ---------------------------------------------------------------------
it('case 37 — completeness.days_in_period adalah jumlah hari kalender periode secara inklusif', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['completeness']['days_in_period'])
        ->toBe(30);

    // Both end days counted — a one-day period is 1, not 0.
    $oneDay = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')->range('2026-11-05', '2026-11-05')
        ->open()->named('Periode Satu Hari')->create();

    expect($this->service->buildSummary($oneDay, null, $this->lineA)['completeness']['days_in_period'])
        ->toBe(1);
});

// ---------------------------------------------------------------------
// Case 38 — days_with_trip is COUNT DISTINCT dates having a trip
// ---------------------------------------------------------------------
it('case 38 — completeness.days_with_trip adalah COUNT DISTINCT tanggal yang punya minimal satu trip', function () {
    $this->actingAs($this->supervisorA);

    // Three distinct dates, two of them carrying several trips.
    weighbridgeReportReceive($this->stationA, '2026-09-03 08:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-03 09:00', 1000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-03 14:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-11 08:00', 1000.0);
    weighbridgeReportReceive($this->stationA, '2026-09-11 10:00', 1000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-25 14:00', 1000.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['completeness']['days_with_trip'])->toBe(3);
    expect($summary['completeness']['days_in_period'])->toBe(30);
    // `daily` holds exactly one row per such date, so counting it IS the
    // distinct count — one definition, one answer.
    expect($summary['completeness']['days_with_trip'])->toBe(count($summary['daily']));
});

// =====================================================================
// GROUP G — THE EXPORT (cases 39-41)
// =====================================================================

// ---------------------------------------------------------------------
// Case 39 — one row per trip, context repeated on every row
// ---------------------------------------------------------------------
it('case 39 — ekspor memancarkan satu baris per trip dengan kolom konteks diulang di tiap baris', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 4) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0);
    }

    foreach (range(1, 3) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 2000.0);
    }

    $lineName = (string) ProductionLine::findOrFail($this->lineA)->name;

    $lines = weighbridgeReportCsvLinesOf(weighbridgeReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA)
    ));

    // Header + 7 data rows.
    expect($lines)->toHaveCount(8);
    expect(weighbridgeReportCsvRow($lines[0]))->toBe(WeighbridgeReportService::EXPORT_HEADER);

    $flows = [];

    foreach (array_slice($lines, 1) as $line) {
        $row = weighbridgeReportCsvRow($line);

        expect($row)->toHaveCount(count(WeighbridgeReportService::EXPORT_HEADER));
        // The four context columns, repeated verbatim so the file can be
        // pivoted directly in a spreadsheet.
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->toBe($lineName);
        expect($row[3])->toBeIn(array_values(WeighbridgeReportService::FLOW_LABELS));

        $flows[] = $row[3];
    }

    expect(WeighbridgeReportService::EXPORT_CONTEXT_COLUMN_COUNT)->toBe(4);
    // Receive and dispatch rows stay distinguishable through the flow column
    // and are never summed.
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Masuk')))->toBe(4);
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Keluar')))->toBe(3);

    // The format gate, asserted here rather than in a case of its own: csv and
    // excel are the two this report understands (no XLSX writer is installed,
    // so excel serves a CSV body under the xlsx mimetype), and anything else
    // is 422 VALIDATION_ERROR on `format` — incomplete input, not a refusal.
    expect(WeighbridgeReportService::SUPPORTED_FORMATS)->toBe(['csv', 'excel']);

    $thrown = null;

    try {
        $this->service->export($this->periodA, 'pdf', null, $this->lineA);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->errors())->toHaveKey('format');

    $excel = $this->service->export($this->periodA, 'excel', null, $this->lineA);
    expect($excel->headers->get('Content-Disposition'))->toContain('.xlsx');
});

// ---------------------------------------------------------------------
// Case 40 — an unweighed trip STAYS a row, with an EMPTY cell
// ---------------------------------------------------------------------
it('case 40 — ekspor mempertahankan baris yang net_weight-nya NULL dengan sel kosong, bukan dibuang dan bukan ditulis 0', function () {
    $this->actingAs($this->supervisorA);

    foreach ([1000.0, 2000.0, 3000.0, null, null] as $i => $net) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 2), $net);
    }

    $lines = weighbridgeReportCsvLinesOf(weighbridgeReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA)
    ));

    expect($lines)->toHaveCount(6);

    $netIndex = array_search('Berat Bersih (kg)', WeighbridgeReportService::EXPORT_HEADER, true);
    expect($netIndex)->not->toBeFalse();

    $netCells = [];

    foreach (array_slice($lines, 1) as $line) {
        $netCells[] = weighbridgeReportCsvRow($line)[$netIndex];
    }

    $empty = array_values(array_filter($netCells, fn ($cell) => $cell === ''));

    expect($empty)->toHaveCount(2);
    // Writing 0 would claim a measurement nobody took; dropping the row would
    // make the file disagree with the trip_count on screen.
    expect($netCells)->not->toContain('0');
    expect($netCells)->not->toContain('0.0');
});

// ---------------------------------------------------------------------
// Case 41 — NO DURATION COLUMN, and EXACTLY ONE timestamp column
//
// Asserted STRUCTURALLY over the header, never as a text search for "durasi":
// the screen deliberately EXPLAINS the absence in prose, so a naive grep
// would fail on the explanation itself.
// ---------------------------------------------------------------------
it('case 41 — header ekspor tidak memuat kolom durasi dan membawa TEPAT SATU kolom penanda waktu', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    weighbridgeReportDispatch($this->stationA, '2026-09-04 14:00', 2000.0);

    $header = WeighbridgeReportService::EXPORT_HEADER;

    foreach ($header as $column) {
        $lower = strtolower($column);

        foreach (['durasi', 'duration', 'lama', 'turnaround', 'in-plant', 'di pabrik', 'selisih waktu'] as $forbidden) {
            expect($lower)->not->toContain($forbidden);
        }
    }

    // EXACTLY ONE timestamp column, sourced from record_datetime. A second
    // time column would invite exactly the subtraction this report must not
    // offer.
    $timeColumns = array_values(array_filter(
        $header,
        fn ($column) => preg_match('/waktu|time|jam|tanggal|date/i', $column) === 1,
    ));

    expect($timeColumns)->toBe(['Waktu Penimbangan']);

    $lines = weighbridgeReportCsvLinesOf(weighbridgeReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA)
    ));

    expect(weighbridgeReportCsvRow($lines[0]))->toBe($header);

    $timeIndex = array_search('Waktu Penimbangan', $header, true);

    foreach (array_slice($lines, 1) as $line) {
        // One timestamp per row, in minute precision, from the trip's own
        // single record_datetime.
        expect(weighbridgeReportCsvRow($line)[$timeIndex])->toMatch('/^2026-09-04 \d{2}:\d{2}$/');
    }
});

// =====================================================================
// GROUP H — EDGE CASES (cases 42-47)
// =====================================================================

// ---------------------------------------------------------------------
// Case 42 — zero trips: nulls and zeroes, nothing invented
// ---------------------------------------------------------------------
it('case 42 — nol trip pada periode+line: total dan rata-rata null, rekap kosong, nol angka dikarang', function () {
    $this->actingAs($this->supervisorA);

    // Rows that must NOT be picked up: another line, another period, no date.
    $lineTwo = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);
    $stationTwo = Station::factory()->forProductionLine($lineTwo)->weighbridge()->create();
    weighbridgeReportReceive($stationTwo, '2026-09-04 08:00', 9999.0);
    weighbridgeReportReceive($this->stationA, '2026-07-04 08:00', 8888.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach ([WeighbridgeReportService::FLOW_RECEIVE, WeighbridgeReportService::FLOW_DISPATCH] as $flow) {
        expect($summary[$flow]['trip_count'])->toBe(0);
        expect($summary[$flow]['net_weight_total'])->toBeNull();
        expect($summary[$flow]['net_weight_avg'])->toBeNull();
        expect($summary[$flow]['net_weight_trip_count'])->toBe(0);
        expect($summary[$flow]['missing_net_weight_trip_count'])->toBe(0);
    }

    expect($summary['receive']['by_origin'])->toBe([]);
    expect($summary['dispatch']['by_destination'])->toBe([]);
    expect($summary['daily'])->toBe([]);
    expect($summary['completeness']['days_with_trip'])->toBe(0);
    expect($summary['daily_total'])->toBe([
        'receive_trip_count' => 0,
        'receive_net_weight_total' => null,
        'dispatch_trip_count' => 0,
        'dispatch_net_weight_total' => null,
    ]);
});

// ---------------------------------------------------------------------
// Case 43 — a flow with zero trips still returns its FULL group
// ---------------------------------------------------------------------
it('case 43 — arus tanpa trip tetap memulangkan kelompok utuh dan tidak pernah dihilangkan dari payload', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 6) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // An absent section reads as "this does not exist"; a present section full
    // of nulls reads as "there were none", and only the second is true.
    expect($summary)->toHaveKey('dispatch');

    expect($summary['dispatch']['trip_count'])->toBe(0);
    expect($summary['dispatch']['net_weight_total'])->toBeNull();
    expect($summary['dispatch']['net_weight_avg'])->toBeNull();
    expect($summary['dispatch']['net_weight_trip_count'])->toBe(0);
    expect($summary['dispatch']['hourly'])->toHaveCount(24);
    expect(array_sum(array_column($summary['dispatch']['hourly'], 'trip_count')))->toBe(0);
    expect($summary['dispatch']['busiest_hour'])->toBeNull();
    expect($summary['dispatch']['empty_hour_count'])->toBe(24);
    expect($summary['dispatch']['by_destination'])->toBe([]);

    // The populated flow is unaffected.
    expect($summary['receive']['trip_count'])->toBe(6);
});

// ---------------------------------------------------------------------
// Case 44 — every net_weight NULL: trip_count kept, total/avg null
// ---------------------------------------------------------------------
it('case 44 — seluruh net_weight NULL: trip_count apa adanya, total dan rata-rata null, missing sama dengan trip_count', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 6) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), null);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['receive']['trip_count'])->toBe(6);
    expect($summary['receive']['net_weight_total'])->toBeNull();
    expect($summary['receive']['net_weight_avg'])->toBeNull();
    expect($summary['receive']['net_weight_trip_count'])->toBe(0);
    expect($summary['receive']['missing_net_weight_trip_count'])->toBe(6);

    // The breakdown row carries a NULL weight too — "never weighed" is not
    // "weighed nothing".
    expect(weighbridgeReportOrigin($summary, 'Estate A')['net_weight_total'])->toBeNull();
    expect(weighbridgeReportOrigin($summary, 'Estate A')['trip_count'])->toBe(6);
    expect(weighbridgeReportDaily($summary, '2026-09-01')['receive_net_weight_total'])->toBeNull();
    expect($summary['daily_total']['receive_net_weight_total'])->toBeNull();
});

// ---------------------------------------------------------------------
// Case 45 — all trips in ONE hour
// ---------------------------------------------------------------------
it('case 45 — seluruh trip pada satu jam yang sama: satu ember berisi semuanya dan empty_hour_count 23', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 9) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 06:%02d', $i, $i * 5), 1000.0);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(weighbridgeReportHour($summary, 'receive', 6))->toBe(9);

    foreach (array_diff(range(0, 23), [6]) as $hour) {
        expect(weighbridgeReportHour($summary, 'receive', $hour))->toBe(0);
    }

    expect($summary['receive']['busiest_hour'])->toBe(6);
    expect($summary['receive']['busiest_hour_trip_count'])->toBe(9);
    expect($summary['receive']['empty_hour_count'])->toBe(23);
    // The distribution is still produced, however uninformative it looks.
    expect($summary['receive']['hourly'])->toHaveCount(24);
});

// ---------------------------------------------------------------------
// Case 46 — every destination NULL: one group equal to dispatch.trip_count
// ---------------------------------------------------------------------
it('case 46 — seluruh destination NULL: by_destination tepat satu kelompok null dengan trip_count sama dengan dispatch.trip_count', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 5) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 1000.0, null);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['dispatch']['by_destination'])->toHaveCount(1);
    expect($summary['dispatch']['by_destination'][0]['destination'])->toBeNull();
    expect($summary['dispatch']['by_destination'][0]['trip_count'])->toBe(5);
    expect($summary['dispatch']['by_destination'][0]['trip_count'])->toBe($summary['dispatch']['trip_count']);
});

// ---------------------------------------------------------------------
// Case 47 — every trip draft: every figure still produced
// ---------------------------------------------------------------------
it('case 47 — seluruh trip draft: draft_trip_count sama dengan jumlah trip dan seluruh angka tetap dihasilkan', function () {
    $this->actingAs($this->supervisorA);

    foreach (range(1, 5) as $i) {
        weighbridgeReportReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0, 'Estate A', [
            'status' => $i % 2 === 0 ? RecordStatus::DraftPaused : RecordStatus::DraftOngoing,
        ]);
    }

    foreach (range(1, 3) as $i) {
        weighbridgeReportDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 2000.0, 'Refinery X', [
            'status' => RecordStatus::DraftOngoing,
        ]);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['draft_trip_count'])->toBe(8);
    expect($summary['receive']['trip_count'] + $summary['dispatch']['trip_count'])->toBe(8);

    // All figures produced normally — the draft count is a disclosure, not a
    // filter.
    expect($summary['receive']['net_weight_total'])->toBe(5000.0);
    expect($summary['dispatch']['net_weight_total'])->toBe(6000.0);
    expect($summary['receive']['by_origin'])->toHaveCount(1);
    expect($summary['dispatch']['by_destination'])->toHaveCount(1);
    expect($summary['receive']['busiest_hour'])->toBe(8);
    expect($summary['dispatch']['busiest_hour'])->toBe(14);
    expect($summary['daily'])->toHaveCount(5);
    expect($summary['completeness']['days_with_trip'])->toBe(5);
});

// =====================================================================
// GROUP I — THE WHOLE ANSWER (cases 48-49)
// =====================================================================

// ---------------------------------------------------------------------
// Case 48 — a bound role's client-sent mill is ignored by summary too
// ---------------------------------------------------------------------
it('case 48 — summary mengabaikan business_unit_id kiriman klien untuk peran terikat dan melaporkan mill akun', function () {
    $this->actingAs($this->supervisorA);

    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Alpha');
    // Mill Beta's figure is unmistakable if it ever surfaces.
    weighbridgeReportReceive($this->stationB, '2026-09-04 08:00', 99999.0, 'Estate Beta');

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['production_line']['id'])->toBe($this->lineA);

    // Every figure is scoped to BU-A — a 200 with the caller's own data,
    // deliberately NOT a 403, which would confirm Mill Beta exists.
    expect($summary['receive']['trip_count'])->toBe(1);
    expect($summary['receive']['net_weight_total'])->toBe(1000.0);
    expect(array_column($summary['receive']['by_origin'], 'estate_supplier'))->toBe(['Estate Alpha']);

    // And BU-B's id never reaches a query binding.
    $bindings = [];

    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            $bindings[] = (string) $binding;
        }
    });

    $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($bindings)->not->toBeEmpty();
    expect($bindings)->not->toContain((string) $this->businessUnitB->id);
});

// ---------------------------------------------------------------------
// Case 49 — happy path: the whole payload, key by key and figure by figure
// ---------------------------------------------------------------------
it('case 49 — happy path: seluruh kondisi terpenuhi, payload lengkap dan tanpa satu pun medan turunan durasi', function () {
    $this->actingAs($this->supervisorA);

    // receive: mixed weights, mixed origins, mixed statuses, a spread of hours.
    weighbridgeReportReceive($this->stationA, '2026-09-01 06:30', 10000.0, 'Estate A');
    weighbridgeReportReceive($this->stationA, '2026-09-01 13:30', 5000.0, 'Supplier B', ['status' => RecordStatus::DraftOngoing]);
    weighbridgeReportReceive($this->stationA, '2026-09-02 06:45', null, 'Estate A');
    weighbridgeReportReceive($this->stationA, '2026-09-30 23:10', 3000.0, 'Estate A');

    // dispatch: mixed destinations including a NULL one, mixed weights.
    weighbridgeReportDispatch($this->stationA, '2026-09-01 20:00', 20000.0, 'Refinery X');
    weighbridgeReportDispatch($this->stationA, '2026-09-02 20:15', null, 'Refinery X');
    weighbridgeReportDispatch($this->stationA, '2026-09-03 21:00', 4000.0, null, ['status' => RecordStatus::DraftPaused]);

    // Out of scope, every one of them: undated, outside the period, other line.
    weighbridgeReportReceive($this->stationA, null, 7777.0);
    weighbridgeReportReceive($this->stationA, '2026-10-02 08:00', 6666.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // ---- shape ----
    expect(array_keys($summary))->toBe([
        'business_unit', 'production_line', 'period',
        'receive', 'dispatch', 'draft_trip_count', 'undated_trip_count',
        'daily', 'daily_total', 'completeness',
    ]);
    expect(array_keys($summary['business_unit']))->toBe(['id', 'name']);
    expect(array_keys($summary['production_line']))->toBe(['id', 'name']);
    expect(array_keys($summary['period']))->toBe(['id', 'name', 'start_date', 'end_date', 'status']);
    expect(array_keys($summary['completeness']))->toBe(['days_in_period', 'days_with_trip', 'days_counted', 'period_running']);

    // ---- receive ----
    expect($summary['receive']['trip_count'])->toBe(4);
    expect($summary['receive']['net_weight_total'])->toBe(18000.0);
    expect($summary['receive']['net_weight_trip_count'])->toBe(3);
    expect($summary['receive']['net_weight_avg'])->toBe(6000.0);
    expect($summary['receive']['missing_net_weight_trip_count'])->toBe(1);
    expect($summary['receive']['hourly'])->toHaveCount(24);
    expect($summary['receive']['busiest_hour'])->toBe(6);
    expect($summary['receive']['busiest_hour_trip_count'])->toBe(2);
    expect($summary['receive']['empty_hour_count'])->toBe(21);
    expect(array_column($summary['receive']['by_origin'], 'estate_supplier'))->toBe(['Estate A', 'Supplier B']);

    // ---- dispatch ----
    expect($summary['dispatch']['trip_count'])->toBe(3);
    expect($summary['dispatch']['net_weight_total'])->toBe(24000.0);
    expect($summary['dispatch']['net_weight_trip_count'])->toBe(2);
    expect($summary['dispatch']['net_weight_avg'])->toBe(12000.0);
    expect($summary['dispatch']['missing_net_weight_trip_count'])->toBe(1);
    expect($summary['dispatch']['busiest_hour'])->toBe(20);
    expect($summary['dispatch']['empty_hour_count'])->toBe(22);
    expect(array_column($summary['dispatch']['by_destination'], 'destination'))->toBe(['Refinery X', null]);

    // ---- completeness counters ----
    expect($summary['draft_trip_count'])->toBe(2);
    expect($summary['undated_trip_count'])->toBe(1);
    expect($summary['daily'])->toHaveCount(4);
    expect($summary['daily_total']['receive_trip_count'])->toBe(4);
    expect($summary['daily_total']['dispatch_trip_count'])->toBe(3);
    expect($summary['completeness']['days_in_period'])->toBe(30);
    expect($summary['completeness']['days_with_trip'])->toBe(4);

    // ---- period and names ----
    expect($summary['period']['name'])->toBe('Periode September Alpha');
    expect($summary['period']['status'])->toBe('open');
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');

    // ---- no duration-derived field anywhere, by key name ----
    $keys = array_map('strtolower', weighbridgeReportAllKeys($summary));

    foreach ($keys as $key) {
        foreach (['duration', 'durasi', 'lama', 'turnaround', 'dwell', 'elapsed', 'arrival', 'dispatch_datetime'] as $forbidden) {
            expect($key)->not->toContain($forbidden);
        }
    }

    // summary() is an ALIAS of buildSummary(), never a second implementation.
    expect($this->service->summary($this->periodA, null, $this->lineA))->toEqual($summary);
});

// =====================================================================
// GROUP J — PERLUASAN AKSES OPERATOR UNTUK screen-144 (cases 50-55)
//
// APA YANG DIJAGA GRUP INI. Pada 2026-10-05 keempat rute
// /api/weighbridge-reports/* dilebarkan agar menerima Operator, karena
// screen-144 (laporan Weighbridge versi mobile) memakai endpoint yang sama
// dengan laporan versi web alih-alih punya endpoint sendiri. Perluasannya
// TEPAT TIGA PERUBAHAN: routes/api.php, guardAccess(), dan cabang TERIKAT
// MILL pada resolveBusinessUnit().
//
// YANG KETIGA ITULAH YANG MEMBUAT DUA PERTAMA AMAN, dan karena itu grup ini
// TIDAK berhenti pada "peran diterima". resolveBusinessUnit() bercabang
// `if (Supervisor || MillManagement) { pakai mill akun } else { perlakukan
// sebagai Admin }`. Melebarkan guardAccess() SAJA akan membuat Operator
// jatuh ke cabang `else`, tempat business_unit_id kiriman klien DIHORMATI —
// dan Operator dapat membaca laporan mill mana pun dengan menyebut id-nya.
// Itu kebocoran lintas mill, bukan cacat tampilan.
//
// Karena itu case 51 mengasersi HASILNYA, bukan penerimaannya: Operator yang
// mengirim id mill lain tetap menerima data mill SENDIRI. Asersi itu gagal
// bila perubahan ketiga terlewat, sementara asersi "peran diterima" justru
// akan tetap hijau. Itu perbedaan antara test yang menjaga dan test yang
// menemani.
// =====================================================================

// ---------------------------------------------------------------------
// Case 50 — guardAccess menerima Operator
// ---------------------------------------------------------------------
it('case 50 — guardAccess menerima Operator dan memulangkan perannya', function () {
    $this->actingAs($this->operatorA);

    // Dibuktikan lewat pintu masuk publik: resolveBusinessUnit() memanggil
    // guardAccess() sebagai langkah pertamanya, jadi tidak melempar =
    // diterima. Tidak ada refleksi atas method protected di sini.
    expect($this->service->resolveBusinessUnit(null))->toBe((string) $this->businessUnitA->id);
});

// ---------------------------------------------------------------------
// Case 51 — Operator berada di cabang TERIKAT MILL, bukan cabang Admin
// ---------------------------------------------------------------------
it('case 51 — Operator: business_unit_id kiriman klien DIBUANG, bukan dihormati', function () {
    $this->actingAs($this->operatorA);

    // Inilah asersi yang gagal bila perubahan ketiga terlewat. Di cabang
    // Admin nilai ini akan dipulangkan apa adanya; di cabang terikat mill ia
    // tidak divalidasi, tidak dibandingkan, dan tidak dipakai.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    // Dan 200-nya nyata, bukan sekadar nilai kembalian: ringkasan yang keluar
    // adalah ringkasan mill sendiri. Sengaja BUKAN 403 — 403 akan
    // membenarkan bahwa mill lain itu ada.
    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Alpha');
    weighbridgeReportReceive($this->stationB, '2026-09-05 08:00', 9999.0, 'Estate Beta');

    $summary = $this->service->buildSummary(
        $this->periodA,
        (string) $this->businessUnitB->id,
        $this->lineA,
    );

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['receive']['trip_count'])->toBe(1);
    expect($summary['receive']['net_weight_total'])->toBe(1000.0);
    expect(array_column($summary['receive']['by_origin'], 'estate_supplier'))->toBe(['Estate Alpha']);
});

// ---------------------------------------------------------------------
// Case 52 — Operator tanpa mill GAGAL TERTUTUP
// ---------------------------------------------------------------------
it('case 52 — Operator tanpa business_unit_id: 422 menghubungi Admin, dan daftar seluruh mill tidak dibaca', function () {
    $operatorWithoutMill = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $this->actingAs($operatorWithoutMill);

    $spy = new WeighbridgeReportAllBusinessUnitsSpy;

    expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);

    // Jatuh ke "seluruh mill" akan mengubah satu baris master data yang rusak
    // menjadi kebocoran lintas mill. Spy membuktikan jalan itu tidak diambil.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// ---------------------------------------------------------------------
// Case 53 — Operator ditolak 403 untuk line milik mill lain
// ---------------------------------------------------------------------
it('case 53 — Operator: production_line_id milik mill lain ditolak 403, tanpa satu kueri record', function () {
    $this->actingAs($this->operatorA);

    weighbridgeReportReceive($this->stationB, '2026-09-05 08:00', 9999.0, 'Estate Beta');

    $thrown = null;

    $queries = weighbridgeReportQueriesDuring(function () use (&$thrown) {
        try {
            $this->service->buildSummary($this->periodA, null, $this->lineB);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }
    });

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);

    // Penolakannya TEGAS di sini — berbeda dari business_unit_id yang
    // diabaikan — karena sebuah line id adalah pegangan nyata atas data mill
    // lain, bukan parameter yang tidak dipakai.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "weighbridge_records"');
    }
});

// ---------------------------------------------------------------------
// Case 54 — Operator ditolak 403 untuk periode milik mill lain
// ---------------------------------------------------------------------
it('case 54 — Operator: period_id milik mill lain ditolak 403, bukan 404', function () {
    $this->actingAs($this->operatorA);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Beta')
        ->create();

    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $this->service->export((string) $periodB->id, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 55 — Operator membaca laporan mill sendiri, termasuk ekspornya
// ---------------------------------------------------------------------
it('case 55 — Operator: laporan dan ekspor mill sendiri terbaca penuh, sama seperti Supervisor', function () {
    weighbridgeReportReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Alpha');
    weighbridgeReportDispatch($this->stationA, '2026-09-05 14:00', 2000.0, 'Refinery X');

    $this->actingAs($this->supervisorA);
    $asSupervisor = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $this->actingAs($this->operatorA);
    $asOperator = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // SATU SUMBER ANGKA. Kalau keduanya pernah berbeda, laporan mobile dan
    // laporan web akan menyimpang tanpa ketahuan — persis hal yang dicegah
    // dengan memakai ulang endpoint yang sama alih-alih membuat yang baru.
    expect($asOperator)->toEqual($asSupervisor);

    // Ekspor pun terbuka bagi Operator: ia menjalankan guard yang sama secara
    // eager sebelum streaming dimulai.
    $response = $this->service->export($this->periodA, 'csv', null, $this->lineA);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    $body = weighbridgeReportStreamed($response);

    expect($body)->toContain('Estate Alpha');
    expect($body)->toContain('Refinery X');
});
