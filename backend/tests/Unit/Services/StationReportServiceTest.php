<?php

/**
 * StationReportServiceTest — screen-140--laporan-stasiun-web /
 * usecase-142--laporan-stasiun-web (Pilih Stasiun untuk Laporan).
 *
 * One test per unit_test_case in the screen's tech spec (18 cases, in the
 * spec's own order), against App\Services\StationReportService. Follows
 * SterilizerReportServiceTest's pragmatic RefreshDatabase + factory
 * approach rather than hand-rolled repository doubles: the service talks
 * to Eloquent directly, so a real (in-memory) database IS the unit under
 * test's collaborator.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this
 * line the Unit suite has no application container — auth()->user(),
 * Eloquent, route() and the faker formats the factories rely on would all
 * blow up ("Unknown format numerify").
 *
 * THREE BEHAVIOURS THAT ARE EASY TO GET BACKWARDS, asserted separately on
 * purpose — collapsing any two would hide whichever one broke:
 *
 *   1. A Supervisor / Mill Management sending ANOTHER mill's
 *      business_unit_id gets 200 with their OWN mill. NOT a 403: the
 *      parameter is discarded, never validated, so there is nothing to
 *      refuse (and a 403 would confirm the other mill exists).
 *   2. A Supervisor / Mill Management whose users.business_unit_id is NULL
 *      FAILS CLOSED with 422 — and the whole-mill list is never queried.
 *      That second half is asserted with a query spy, because a fallback
 *      to "every mill" would still return 200 and would otherwise look
 *      like a pass.
 *   3. An Admin who has not picked a mill gets 422 VALIDATION_ERROR, NOT
 *      403 — nothing was refused, the input is merely incomplete.
 *
 * STATION MASTER IS REBUILT PER TEST where the case is about the master
 * itself (exclusion of 'other', ordering, empty master). The migration
 * seeds 19 canonical types, so asserting against that seed would prove
 * only that the seed exists; building a tiny, known master proves the
 * service reads the TABLE rather than a hardcoded list.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\StationType;
use App\Models\User;
use App\Services\StationReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Replaces the whole station_types master with exactly $rows, each
 * [code, name, sort_order]. Safe because no test here creates a station or
 * a period, so nothing holds an FK to the rows being removed.
 */
function stationReportMaster(array $rows): void
{
    StationType::query()->delete();

    foreach ($rows as [$code, $name, $sortOrder]) {
        StationType::create([
            'code' => $code,
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }
}

/**
 * Runs $callback while recording every SQL statement the connection
 * executes.
 *
 * @return array{0: ?Throwable, 1: list<string>} the exception it threw (or
 *                                               null) and the SQL it ran
 */
function stationReportCapture(Closure $callback): array
{
    $sql = [];

    DB::listen(function ($query) use (&$sql) {
        $sql[] = $query->sql;
    });

    $thrown = null;

    try {
        $callback();
    } catch (Throwable $e) {
        $thrown = $e;
    }

    return [$thrown, $sql];
}

/** True when any recorded statement touched $table. */
function stationReportTouched(array $sql, string $table): bool
{
    foreach ($sql as $statement) {
        if (str_contains($statement, $table)) {
            return true;
        }
    }

    return false;
}

beforeEach(function () {
    $this->service = new StationReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();

    // Admin is the one role bound to no mill at all — users.business_unit_id
    // is NULL, which is precisely why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // A bound account whose mill is missing: broken master data, and the
    // fail-closed case.
    $this->supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $this->millManagementNoMill = User::factory()->role(UserRole::MillManagement)->create(['business_unit_id' => null]);
});

// =====================================================================
// businessUnitOptions() — cases 1-4
// =====================================================================

// Case 1
it('options: melempar 401 UNAUTHENTICATED ketika tidak ada sesi terautentikasi', function () {
    // No actingAs at all — auth()->user() is null.
    [$thrown, $sql] = stationReportCapture(fn () => $this->service->businessUnitOptions());

    expect($thrown)->toBeInstanceOf(AuthenticationException::class);
    // The list must not be built before the caller is known.
    expect(stationReportTouched($sql, 'business_units'))->toBeFalse();
});

// Case 2
it('options: melempar 403 FORBIDDEN ketika peran bukan Admin, dan daftar mill tidak pernah dibaca', function () {
    foreach ([$this->supervisorA, $this->millManagementA, $this->operatorA] as $user) {
        $this->actingAs($user);

        [$thrown, $sql] = stationReportCapture(fn () => $this->service->businessUnitOptions());

        // A role bound to one mill has no picker at all, so this is a
        // refusal rather than a list filtered down to one.
        expect($thrown)->toBeInstanceOf(AuthorizationException::class);
        expect(stationReportTouched($sql, 'business_units'))->toBeFalse();
    }
});

// Case 3
it('options: mengembalikan daftar mill berisi id dan name ketika pengguna Admin', function () {
    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    expect($options)->toHaveCount(2);
    expect(array_keys($options[0]))->toBe(['id', 'name']);
    expect(array_keys($options[1]))->toBe(['id', 'name']);
    // Ordered by name, per businessUnitOptions()'s orderBy('name').
    expect(array_column($options, 'name'))->toBe(['Mill Alpha', 'Mill Beta']);
    expect($options[0]['id'])->toBe((string) $this->businessUnitA->id);
});

// Case 4
it('options: mengembalikan data kosong (bukan error) ketika belum ada mill di sistem', function () {
    // Bound accounts hold an FK to the mills, so they go first.
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    $this->actingAs($this->admin);

    // An empty master is a valid answer, not a 404 and not an exception —
    // the screen turns it into "master Business Unit masih kosong".
    expect($this->service->businessUnitOptions())->toBe([]);
});

// =====================================================================
// stations() — cases 5-18
// =====================================================================

// Case 5
it('stations: melempar 401 UNAUTHENTICATED ketika tidak ada sesi terautentikasi', function () {
    expect(fn () => $this->service->stations(null))->toThrow(AuthenticationException::class);
});

// Case 6
it('stations: melempar 403 FORBIDDEN untuk Operator, tanpa membaca business unit maupun station_type', function () {
    $this->actingAs($this->operatorA);

    [$thrown, $sql] = stationReportCapture(
        fn () => $this->service->stations((string) $this->businessUnitA->id),
    );

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);
    expect(stationReportTouched($sql, 'business_units'))->toBeFalse();
    expect(stationReportTouched($sql, 'station_types'))->toBeFalse();
});

// Case 7
it('stations: Supervisor memakai users.business_unit_id dan mengabaikan business_unit_id kiriman klien', function () {
    $this->actingAs($this->supervisorA);

    // Mill Beta is a real, existing mill — and still never reaches the
    // query, because the parameter is discarded rather than checked.
    $result = $this->service->stations((string) $this->businessUnitB->id);

    expect($result['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($result['business_unit']['name'])->toBe('Mill Alpha');
});

// Case 8
it('stations: Mill Management memakai users.business_unit_id dan mengabaikan business_unit_id kiriman klien', function () {
    $this->actingAs($this->millManagementA);

    $result = $this->service->stations((string) $this->businessUnitB->id);

    expect($result['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($result['business_unit']['name'])->toBe('Mill Alpha');

    // An id that exists nowhere is equally ignored: it is never validated
    // for this role, so it cannot produce a 404 either.
    $unknown = $this->service->stations((string) Str::uuid());

    expect($unknown['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
});

// Case 9
it('stations: melempar 422 VALIDATION_ERROR ketika Supervisor/Mill Management tanpa mill (gagal tertutup)', function () {
    foreach ([$this->supervisorNoMill, $this->millManagementNoMill] as $user) {
        $this->actingAs($user);

        [$thrown, $sql] = stationReportCapture(fn () => $this->service->stations(null));

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors())->toHaveKey('business_unit_id');
        expect($thrown->errors()['business_unit_id'])
            ->toBe(['Akun Anda belum terhubung ke mill. Hubungi Admin.']);

        // THE POINT OF THIS CASE: no fallback to "every mill". A broken
        // master-data row must not hand one mill's user every other
        // mill's data, so the list is not merely hidden — it is never
        // built.
        expect(stationReportTouched($sql, 'business_units'))->toBeFalse();
    }
});

// Case 10
it('stations: melempar 422 VALIDATION_ERROR (bukan 403) ketika Admin tidak mengirim business_unit_id', function () {
    $this->actingAs($this->admin);

    foreach ([null, ''] as $missing) {
        [$thrown] = stationReportCapture(fn () => $this->service->stations($missing));

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        // Incomplete input, not a refused access.
        expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors()['business_unit_id'])
            ->toBe(['Pilih mill terlebih dahulu untuk menampilkan stasiun.']);
    }
});

// Case 11
it('stations: Admin memakai business_unit_id kiriman klien', function () {
    $this->actingAs($this->admin);

    $result = $this->service->stations((string) $this->businessUnitA->id);

    expect($result['business_unit'])->toBe([
        'id' => (string) $this->businessUnitA->id,
        'name' => 'Mill Alpha',
    ]);
});

// Case 12
it('stations: melempar 404 NOT_FOUND ketika business_unit_id Admin tidak ditemukan, tanpa menyentuh station_types', function () {
    $this->actingAs($this->admin);

    [$thrown, $sql] = stationReportCapture(
        fn () => $this->service->stations((string) Str::uuid()),
    );

    expect($thrown)->toBeInstanceOf(ModelNotFoundException::class);
    // The mill is proven to exist BEFORE the station query, so a bad id
    // never costs a second round trip.
    expect(stationReportTouched($sql, 'station_types'))->toBeFalse();
});

// Case 13
it("stations: mengecualikan jenis stasiun 'other' dari hasil", function () {
    stationReportMaster([
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
        ['other', 'Other', 190],
    ]);

    $this->actingAs($this->supervisorA);

    $codes = array_column($this->service->stations(null)['stations'], 'code');

    expect($codes)->not->toContain('other');
    expect($codes)->toBe(['sterilizer', 'threshing']);
});

// Case 14
it('stations: mengurutkan jenis stasiun berdasarkan sort_order ASC, bukan alfabet atau daftar tetap', function () {
    // Inserted deliberately out of order, and NOT in alphabetical order
    // either, so neither insertion order nor name can produce this result
    // by accident.
    stationReportMaster([
        ['threshing', 'Threshing', 50],
        ['weighbridge', 'Weighbridge', 10],
        ['sterilizer', 'Sterilizer', 40],
    ]);

    $this->actingAs($this->supervisorA);

    $stations = $this->service->stations(null)['stations'];

    expect(array_column($stations, 'code'))->toBe(['weighbridge', 'sterilizer', 'threshing']);
    expect(array_column($stations, 'sort_order'))->toBe([10, 40, 50]);
    // The count follows the master, not the canonical 18.
    expect($stations)->toHaveCount(3);
});

// Case 15
it('stations: menandai report_available=true dan mengisi report_path hanya untuk stasiun di REPORT_ROUTES', function () {
    stationReportMaster([
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
    ]);

    $this->actingAs($this->admin);

    $stations = collect($this->service->stations((string) $this->businessUnitA->id)['stations'])
        ->keyBy('code');

    expect(StationReportService::REPORT_ROUTES)->toHaveKey('sterilizer');
    expect($stations['sterilizer']['report_available'])->toBeTrue();
    expect($stations['sterilizer']['report_path'])->toContain('/reports/sterilizer');
    // The mill travels WITH the link, so the report screen never has to
    // ask for a mill a second time in one flow.
    expect($stations['sterilizer']['report_path'])
        ->toContain('business_unit_id='.$this->businessUnitA->id);
});

// Case 16
it('stations: stasiun yang laporannya belum dibangun tetap ada, dengan report_available=false dan report_path null', function () {
    stationReportMaster([
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
    ]);

    $this->actingAs($this->supervisorA);

    $stations = collect($this->service->stations(null)['stations'])->keyBy('code');

    // Not dropped for being unbuilt — the screen greys it out rather than
    // hiding it, so the feature's coverage reads as it really is.
    expect($stations)->toHaveKey('threshing');
    expect($stations['threshing']['report_available'])->toBeFalse();
    expect($stations['threshing']['report_path'])->toBeNull();
    expect($stations['threshing']['name'])->toBe('Threshing');
});

// Case 17
it('stations: mengembalikan stations kosong (bukan error) ketika master Jenis Stasiun kosong', function () {
    // Only the historical catch-all is left, and that one never gets a
    // tile — so the grid is empty without the master being empty.
    stationReportMaster([['other', 'Other', 190]]);

    $this->actingAs($this->supervisorA);

    $result = $this->service->stations(null);

    expect($result['stations'])->toBe([]);
    expect($result['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($result['business_unit']['name'])->toBe('Mill Alpha');
});

// Case 18
it('stations: mengembalikan hasil sukses lengkap ketika semua prasyarat terpenuhi', function () {
    stationReportMaster([
        ['weighbridge', 'Weighbridge', 10],
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
        ['other', 'Other', 190],
    ]);

    $this->actingAs($this->supervisorA);

    $result = $this->service->stations(null);

    expect(array_keys($result))->toBe(['business_unit', 'production_line', 'stations']);
    expect($result['business_unit'])->toBe([
        'id' => (string) $this->businessUnitA->id,
        'name' => 'Mill Alpha',
    ]);

    expect($result['stations'])->toHaveCount(3);
    expect(array_keys($result['stations'][0]))
        ->toBe(['code', 'name', 'sort_order', 'report_available', 'report_path']);
    expect(array_column($result['stations'], 'code'))
        ->toBe(['weighbridge', 'sterilizer', 'threshing']);

    $byCode = collect($result['stations'])->keyBy('code');

    expect($byCode['sterilizer']['report_available'])->toBeTrue();
    expect($byCode['sterilizer']['report_path'])
        ->toContain('business_unit_id='.$this->businessUnitA->id);

    // UPDATED 2026-10-01 (screen-143--laporan-weighbridge-web). This case used
    // to assert weighbridge report_available = false / report_path null, from
    // the days when no Weighbridge report existed. REPORT_ROUTES now maps
    // 'weighbridge' => 'reports.weighbridge' — FIRST in the map, because
    // station_types.sort_order puts weighbridge (10) ahead of every other
    // mapped code. The old assertion is the one that became wrong; the new
    // entry is correct, and the tile is now live.
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('weighbridge');
    expect($byCode['weighbridge']['report_available'])->toBeTrue();
    expect($byCode['weighbridge']['report_path'])->toContain('/reports/weighbridge');
    expect($byCode['weighbridge']['report_path'])
        ->toContain('business_unit_id='.$this->businessUnitA->id);

    // The unbuilt-report half of this case is now carried by threshing, which
    // has no REPORT_ROUTES entry — so "greyed out rather than hidden" is still
    // asserted here and not merely in case 16.
    expect(StationReportService::REPORT_ROUTES)->not->toHaveKey('threshing');
    expect($byCode['threshing']['report_available'])->toBeFalse();
    expect($byCode['threshing']['report_path'])->toBeNull();
});
