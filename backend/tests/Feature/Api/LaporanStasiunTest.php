<?php

/**
 * LaporanStasiunTest (Feature/Api) — screen-140--laporan-stasiun-web /
 * usecase-142--laporan-stasiun-web (Pilih Stasiun untuk Laporan).
 *
 * Integration tests for the two GET endpoints under /api/station-reports
 * (App\Http\Controllers\Api\StationReportController): one test per
 * test_scenarios entry, running that scenario's api_test steps IN ORDER
 * and feeding the real response of step N into step N+1 exactly as the
 * `{{stepN.field}}` references prescribe. Exercises the real route ->
 * 'auth:web,sanctum' + 'role:supervisor,mill_management,admin' ->
 * controller -> StationReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanSterilizerTest.php's conventions.
 *
 * THE THREE ANSWERS THAT ARE EASY TO GET WRONG, each asserted on its own:
 *   - Supervisor / Mill Management sending ANOTHER mill's business_unit_id
 *     -> 200 with their OWN mill. Deliberately NOT 403: the parameter is
 *     discarded, never validated, so there is nothing to refuse — and a
 *     403 would confirm the other mill exists. An id that exists nowhere
 *     is likewise a 200, not a 404, for the same reason.
 *   - Admin with no business_unit_id -> 422 VALIDATION_ERROR, not 403.
 *   - A bound account whose users.business_unit_id is NULL -> 422, and the
 *     response carries no mill list at all (fail closed).
 *
 * ON ASSERTING `code` FOR 403: a refusal raised by EnsureRole (the
 * Operator, at the route layer) carries only { message } — that middleware
 * builds its own JSON without going through ApiExceptionHandler — so those
 * tests assert the STATUS ALONE. A refusal raised by the service
 * (businessUnitOptions() for a non-Admin) does carry code = 'FORBIDDEN'
 * and is asserted in full.
 *
 * THE STATION MASTER IS THE MIGRATION SEED (19 types, 18 of them real)
 * unless a test rebuilds it. Tests about the master itself rebuild it, so
 * they prove the endpoint reads the table rather than the seed.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\StationType;
use App\Models\User;
use App\Services\StationReportService;
use Illuminate\Support\Str;

/**
 * Replaces the whole station_types master with exactly $rows, each
 * [code, name, sort_order]. Safe here because no test in this file creates
 * a station or a period, so nothing holds an FK to the removed rows.
 */
function laporanStasiunApiMaster(array $rows): void
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

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();

    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Broken master data: a role that IS bound to a mill, without one.
    $this->supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
});

// =====================================================================
// Scenario: "success as Supervisor / Mill Management"
// =====================================================================
it('berhasil: Supervisor mendapat grid stasiun mill sendiri, urut sort_order, tanpa other', function () {
    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $stations->assertStatus(200);
    $stations->assertJsonPath('data.business_unit.id', (string) $this->businessUnitA->id);
    $stations->assertJsonPath('data.business_unit.name', 'Mill Alpha');

    $items = $stations->json('data.stations');

    expect($items)->not->toBeEmpty();
    expect(array_column($items, 'code'))->not->toContain('other');

    // Ordered by sort_order ASC, not by name and not by a fixed list.
    $sortOrders = array_column($items, 'sort_order');
    $ascending = $sortOrders;
    sort($ascending);
    expect($sortOrders)->toBe($ascending);

    $byCode = collect($items)->keyBy('code');

    expect($byCode['sterilizer']['report_available'])->toBeTrue();
    expect($byCode['sterilizer']['report_path'])->toContain('/reports/sterilizer');
    expect($byCode['sterilizer']['report_path'])
        ->toContain('business_unit_id='.$this->businessUnitA->id);

    // Mill Management is bound the same way and must answer identically.
    $millManagement = $this->actingAs($this->millManagement, 'web')->getJson('/api/station-reports/stations');

    $millManagement->assertStatus(200);
    $millManagement->assertJsonPath('data.business_unit.id', (string) $this->businessUnitA->id);
});

// =====================================================================
// Scenario: "success as Admin"
// =====================================================================
it('berhasil: Admin memilih mill dari options lalu mendapat grid stasiun mill itu', function () {
    // Step 1 — GET /api/station-reports/business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/station-reports/business-units/options');

    $options->assertStatus(200);
    expect($options->json('data'))->not->toBeEmpty();
    expect(array_keys($options->json('data.0')))->toBe(['id', 'name']);

    // Step 2 — GET /api/station-reports/stations?business_unit_id={{step1.data[0].id}}
    $millId = $options->json('data.0.id');

    $stations = $this->actingAs($this->admin, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query(['business_unit_id' => $millId]));

    $stations->assertStatus(200);
    $stations->assertJsonPath('data.business_unit.id', $millId);

    $items = $stations->json('data.stations');
    $sortOrders = array_column($items, 'sort_order');
    $ascending = $sortOrders;
    sort($ascending);
    expect($sortOrders)->toBe($ascending);

    $sterilizer = collect($items)->firstWhere('code', 'sterilizer');

    expect($sterilizer['report_available'])->toBeTrue();
    expect($sterilizer['report_path'])->toContain('business_unit_id='.$millId);
});

// =====================================================================
// Scenario: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: 422 VALIDATION_ERROR dengan arahan memilih mill, bukan 403', function () {
    // Step 1 — GET /api/station-reports/stations, no query param at all.
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/station-reports/stations');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonPath('errors.business_unit_id.0', 'Pilih mill terlebih dahulu untuk menampilkan stasiun.');

    // Nothing was refused — the input is merely incomplete.
    expect($response->status())->not->toBe(403);
    expect($response->json('data'))->toBeNull();
});

// =====================================================================
// Scenario: "belum ada mill di sistem"
// =====================================================================
it('belum ada mill: options mengembalikan 200 dengan data kosong, bukan 404 maupun 500', function () {
    // Bound accounts hold an FK to the mills, so they go first.
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    // Step 1 — GET /api/station-reports/business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/station-reports/business-units/options');

    $options->assertStatus(200);
    $options->assertJsonPath('data', []);
});

// =====================================================================
// Scenario: "akun terikat mill tetapi mill-nya kosong"
// =====================================================================
it('akun tanpa mill: 422 VALIDATION_ERROR dan respons tidak memuat daftar mill (gagal tertutup)', function () {
    // Step 1 — GET /api/station-reports/stations as a Supervisor whose
    // users.business_unit_id is NULL.
    $response = $this->actingAs($this->supervisorNoMill, 'web')->getJson('/api/station-reports/stations');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonPath('errors.business_unit_id.0', 'Akun Anda belum terhubung ke mill. Hubungi Admin.');

    // FAIL CLOSED — not merely "no grid": the whole-mill list must not
    // appear anywhere in the body either.
    $response->assertDontSee('Mill Alpha');
    $response->assertDontSee('Mill Beta');
    $response->assertDontSee((string) $this->businessUnitA->id);
    expect($response->json('data'))->toBeNull();
    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(403);
});

// =====================================================================
// Scenario: "menekan stasiun yang belum tersedia"
// =====================================================================
it('stasiun belum tersedia: threshing dikembalikan dengan report_available false dan report_path null', function () {
    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $stations->assertStatus(200);

    $threshing = collect($stations->json('data.stations'))->firstWhere('code', 'threshing');

    // Present, so the screen can render it — but with no destination at
    // all, which is why the tile has no href and nothing to click.
    expect($threshing)->not->toBeNull();
    expect($threshing['report_available'])->toBeFalse();
    expect($threshing['report_path'])->toBeNull();
});

// =====================================================================
// Scenario: "master Jenis Stasiun kosong"
// =====================================================================
it('master kosong: 200 dengan business_unit terisi dan stations kosong, bukan 404 atau 500', function () {
    // Only the historical catch-all remains, and that never gets a tile.
    laporanStasiunApiMaster([['other', 'Other', 190]]);

    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $stations->assertStatus(200);
    $stations->assertJsonPath('data.business_unit.id', (string) $this->businessUnitA->id);
    $stations->assertJsonPath('data.stations', []);
});

// =====================================================================
// Scenario: "Operator mencoba membuka layar ini"
// =====================================================================
it('operator: kedua endpoint menolak dengan 403 dan tidak membocorkan mill maupun stasiun', function () {
    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->operator, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query([
            'business_unit_id' => (string) $this->businessUnitA->id,
        ]));

    // EnsureRole builds its own JSON ({ message } only, no `code`), so the
    // status is the whole assertion here — see the file docblock.
    $stations->assertStatus(403);
    expect($stations->json('data'))->toBeNull();
    $stations->assertDontSee('Mill Alpha');
    $stations->assertDontSee('sterilizer');

    // Step 2 — GET /api/station-reports/business-units/options
    $options = $this->actingAs($this->operator, 'web')->getJson('/api/station-reports/business-units/options');

    $options->assertStatus(403);
    expect($options->json('data'))->toBeNull();
    $options->assertDontSee('Mill Alpha');
    $options->assertDontSee('Mill Beta');
});

// =====================================================================
// Scenario: "pengguna terikat mill tidak dapat mengganti mill"
// =====================================================================
it('supervisor mengirim mill lain: tetap 200 berisi mill sendiri, bukan 403 dan bukan 404', function () {
    // Step 1 — another mill's id. The two-step shape is load-bearing and
    // must not be collapsed: the first proves an EXISTING other mill is
    // ignored, the second proves an id that exists nowhere is ignored too.
    $other = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query([
            'business_unit_id' => (string) $this->businessUnitB->id,
        ]));

    $other->assertStatus(200);
    $other->assertJsonPath('data.business_unit.id', (string) $this->businessUnitA->id);
    $other->assertJsonPath('data.business_unit.name', 'Mill Alpha');
    // Nothing of Mill Beta leaks — not its name, not its id.
    $other->assertDontSee('Mill Beta');
    $other->assertDontSee((string) $this->businessUnitB->id);

    $sterilizer = collect($other->json('data.stations'))->firstWhere('code', 'sterilizer');
    expect($sterilizer['report_path'])->toContain('business_unit_id='.$this->businessUnitA->id);

    // Step 2 — an id that belongs to no mill at all. Still 200, because
    // the parameter is never validated for this role.
    $unknown = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query([
            'business_unit_id' => (string) Str::uuid(),
        ]));

    $unknown->assertStatus(200);
    $unknown->assertJsonPath('data.business_unit.id', (string) $this->businessUnitA->id);
});

// =====================================================================
// Scenario: "daftar stasiun mengikuti master Jenis Stasiun dan urutan proses produksi"
// =====================================================================
it('master berubah: jumlah dan urutan stasiun mengikuti master terbaru tanpa perubahan kode', function () {
    // Step 1 — the master as it stands.
    $before = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $before->assertStatus(200);

    $baseline = $before->json('data.stations');
    $baselineCount = count($baseline);

    expect($baselineCount)->toBe(StationType::where('code', '<>', 'other')->count());

    // The master changes: one type added, and one existing type moved to
    // the front of the process order.
    StationType::create([
        'code' => 'stasiun-baru',
        'name' => 'Stasiun Baru',
        'sort_order' => 45,
        'is_active' => true,
    ]);
    StationType::where('code', 'threshing')->update(['sort_order' => 1]);

    // Step 2 — the same endpoint, no deploy in between.
    $after = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $after->assertStatus(200);

    $items = $after->json('data.stations');
    $codes = array_column($items, 'code');

    expect($items)->toHaveCount($baselineCount + 1);
    expect($codes)->toContain('stasiun-baru');
    // Moved to sort_order 1, so it now leads the grid.
    expect($codes[0])->toBe('threshing');

    $sortOrders = array_column($items, 'sort_order');
    $ascending = $sortOrders;
    sort($ascending);
    expect($sortOrders)->toBe($ascending);

    // A type with no report is still returned, never dropped for being
    // unknown to REPORT_ROUTES.
    $new = collect($items)->firstWhere('code', 'stasiun-baru');
    expect($new['report_available'])->toBeFalse();
    expect($new['report_path'])->toBeNull();
});

// =====================================================================
// Scenario: "jenis stasiun historis 'other' dikecualikan"
// =====================================================================
it("other dikecualikan: tidak ada item berkode other, jenis lain tetap hadir", function () {
    laporanStasiunApiMaster([
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
        ['other', 'Other', 190],
    ]);

    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $stations->assertStatus(200);

    $codes = array_column($stations->json('data.stations'), 'code');

    expect($codes)->not->toContain('other');
    expect($codes)->toBe(['sterilizer', 'threshing']);
});

// =====================================================================
// Scenario: "stasiun yang belum dibangun tampil nonaktif, bukan disembunyikan"
// =====================================================================
it('belum dibangun tidak dibuang: seluruh jenis master hadir, hanya sterilizer yang tersedia', function () {
    // Step 1 — GET /api/station-reports/stations
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');

    $stations->assertStatus(200);

    $items = $stations->json('data.stations');

    // Every real type in the master is present — nothing is hidden for
    // being unbuilt.
    expect($items)->toHaveCount(StationType::where('code', '<>', 'other')->count());

    $available = collect($items)->where('report_available', true)->pluck('code')->values()->all();

    // REPORT_ROUTES is the single source of truth, and today it holds one
    // entry. This assertion follows the map rather than hardcoding it.
    expect($available)->toBe(array_keys(StationReportService::REPORT_ROUTES));

    foreach ($items as $item) {
        if ($item['report_available']) {
            expect($item['report_path'])->not->toBeNull();

            continue;
        }

        expect($item['report_path'])->toBeNull();
    }
});

// =====================================================================
// Scenario: "mill yang ditetapkan terbawa ke layar laporan"
// =====================================================================
it('mill terbawa: report_path mengikuti mill terakhir yang ditetapkan Admin', function () {
    // Step 1 — GET /api/station-reports/business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/station-reports/business-units/options');

    $options->assertStatus(200);
    expect($options->json('data'))->toHaveCount(2);

    $firstMillId = $options->json('data.0.id');
    $secondMillId = $options->json('data.1.id');

    // Step 2 — the first mill.
    $first = $this->actingAs($this->admin, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query(['business_unit_id' => $firstMillId]));

    $first->assertStatus(200);
    $first->assertJsonPath('data.business_unit.id', $firstMillId);

    $firstSterilizer = collect($first->json('data.stations'))->firstWhere('code', 'sterilizer');
    expect($firstSterilizer['report_path'])->toContain('business_unit_id='.$firstMillId);

    // Step 3 — switched to the second mill: the link must follow, not
    // keep pointing at the mill chosen a moment ago.
    $second = $this->actingAs($this->admin, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query(['business_unit_id' => $secondMillId]));

    $second->assertStatus(200);
    $second->assertJsonPath('data.business_unit.id', $secondMillId);

    $secondSterilizer = collect($second->json('data.stations'))->firstWhere('code', 'sterilizer');
    expect($secondSterilizer['report_path'])->toContain('business_unit_id='.$secondMillId);
    expect($secondSterilizer['report_path'])->not->toContain('business_unit_id='.$firstMillId);
});

// =====================================================================
// Error codes with no BDD scenario of their own — 401 on both endpoints,
// and the Admin 404. The tech spec records the 404 as deliberately
// unpaired (implementation_notes #13); both are still part of the
// endpoints' contract, so they are asserted here rather than left to the
// unit tests alone.
// =====================================================================
it('tanpa sesi: kedua endpoint menjawab 401 UNAUTHENTICATED', function () {
    $stations = $this->getJson('/api/station-reports/stations');
    $stations->assertStatus(401);
    $stations->assertJsonPath('code', 'UNAUTHENTICATED');

    $options = $this->getJson('/api/station-reports/business-units/options');
    $options->assertStatus(401);
    $options->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('admin mengirim business_unit_id yang tidak ada: 404 NOT_FOUND', function () {
    $response = $this->actingAs($this->admin, 'web')
        ->getJson('/api/station-reports/stations?'.http_build_query([
            'business_unit_id' => (string) Str::uuid(),
        ]));

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
});

it('options: Supervisor dan Mill Management ditolak service dengan 403 FORBIDDEN berkode', function () {
    // Unlike the Operator's route-layer refusal, this one comes from
    // StationReportService (AuthorizationException) and therefore DOES
    // carry `code`.
    $supervisor = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/business-units/options');
    $supervisor->assertStatus(403);
    $supervisor->assertJsonPath('code', 'FORBIDDEN');

    $millManagement = $this->actingAs($this->millManagement, 'web')->getJson('/api/station-reports/business-units/options');
    $millManagement->assertStatus(403);
    $millManagement->assertJsonPath('code', 'FORBIDDEN');
});
