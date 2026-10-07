<?php

/**
 * ProductionProcessActivityTest (Feature) — screen-035--production-process-
 * activity-web / usecase-035--production-process-activity-web.
 *
 * This screen has no controller/service/API (pure static Blade view per
 * tech-spec v1) — tests exercise the real route -> 'auth' + 'role'
 * middleware -> view chain directly.
 *
 * Threshing/Pressing/Depricarping/Kernel Plant (promoted to active
 * 2026-08-23) were temporarily hidden from this grid by product decision,
 * then re-enabled in stages: Threshing/Pressing on 2026-08-25, Depricarping/
 * Kernel Plant on 2026-08-28. All 7 active station tiles now render.
 *
 * 2026-08-31 — 6 more former placeholders promoted to active (Clarification,
 * Boiler Room, Effluent Plant, Engine Room, Process Water, Storage Tank),
 * plus 4 brand-new active tiles with no Data Browser screen yet at the time
 * (Solid Waste Disposal, Kernel Dispatch, CPO Dispatch, Process Quality
 * Control) — 17 active tiles total. 9 of these initially had no registered
 * route, so they rendered as active-styled tiles pointing to
 * `javascript:void(0)` rather than a real `route()` href. Solid Waste
 * Disposal's Data Browser screen (screen-091), Process Water's Data Browser
 * screen (screen-092), Kernel Dispatch's Data Browser screen (screen-093),
 * CPO Dispatch's Data Browser screen (screen-094), and Effluent Plant's Data
 * Browser screen (screen-095) have since been implemented and are now
 * routed like the original 7. Storage Tank's Data Browser screen
 * (screen-096) has since also been implemented and routed. Engine Room's
 * Data Browser screen (screen-097) has since also been implemented and
 * routed. Boiler Room's Data Browser screen (screen-098) has since also
 * been implemented and routed. Clarification's Data Browser screen
 * (screen-099) has since also been implemented and routed. Process Quality
 * Control's Data Browser screen (screen-100) has since also been
 * implemented and routed — all 17 active tiles are now routed to a real
 * Data Browser screen; the "still-unrouted" scenario this test used to
 * cover no longer applies to any tile.
 *
 * 2026-09-01 — 'Loading Ramp' placeholder removed entirely: it turned out
 * to be a duplicate name for the already-active Cages Track station, not
 * a distinct station. Only Sterilizer remained placeholder at that point.
 *
 * 2026-09-01 (final promotion) — Sterilizer promoted to a fully active
 * tile, routed to its own Data Browser screen (screen-124). This was the
 * LAST remaining placeholder — 18 active tiles total, 0 placeholders. The
 * "Klik Stasiun Disabled" scenario this test used to cover no longer
 * applies to any tile — repurposed below into an assertion that no
 * placeholder/disabled tile renders at all.
 *
 * 2026-09-01 → 2026-09-16 (hide, then fully reverted) — 8 tiles were
 * temporarily hidden by product decision and re-enabled in four batches;
 * the hide mechanism was then removed outright. The old "does not render
 * the hidden tiles" scenario is inverted below into "every one of the 18
 * IS reachable", so the grid's completeness stays covered by a test even
 * though nothing can hide a tile any more.
 */

use App\Enums\UserRole;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\ProductionProcessActivityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();

    // Snapshot hitungan di-cache 60 detik dengan kunci yang memuat mill +
    // tanggal. Tanpa membersihkannya, uji yang menyemai record akan membaca
    // snapshot uji sebelumnya — dan karena mill-nya BERBEDA per uji kuncinya
    // juga berbeda, kegagalannya akan terlihat acak alih-alih konsisten.
    Cache::flush();
});

/**
 * Potongan markup satu tile, dari data-testid-nya sampai penutup elemennya.
 *
 * Asersi dilakukan atas POTONGAN ini, bukan atas seluruh halaman: 18 tile
 * memakai kelas keadaan yang sama, jadi `toContain('is-done')` pada halaman
 * penuh akan lolos karena tile LAIN — kelas kesalahan yang sama dengan
 * asersi frasa selebar halaman.
 */
function ppaTileMarkup(string $html, string $code): string
{
    $start = strpos($html, 'data-testid="station-tile-'.$code.'"');

    if ($start === false) {
        return '';
    }

    // Mundur ke awal tag pembukanya supaya `<a ` / `aria-disabled` ikut
    // terbawa.
    $open = (int) strrpos(substr($html, 0, $start), '<');

    // Dan maju sampai TAG PEMBUKA tile berikutnya, bukan sampai testid-nya.
    // Versi pertama helper ini berhenti di testid, sehingga tag pembuka tile
    // berikutnya ikut terbawa — dan dua asersi ketiadaan gagal karena kelas
    // milik tile SEBELAHNYA, bukan karena produknya. Potongan yang terlalu
    // lebar membuat asersi per-tile berbohong ke dua arah sekaligus.
    $nextTestId = strpos($html, 'data-testid="station-tile-', $start + 10);

    if ($nextTestId === false) {
        return substr($html, $open);
    }

    $nextOpen = (int) strrpos(substr($html, 0, $nextTestId), '<');

    return substr($html, $open, $nextOpen - $open);
}

/**
 * Satu mill baru + satu stasiun + satu record, dan aktor yang terikat mill
 * itu. Stasiunnya Boiler Room karena tabelnya memakai kolom `date` seperti 17
 * stasiun lainnya — Weighbridge diuji terpisah justru karena ia tidak.
 *
 * @return array{0: Station, 1: User}
 */
function ppaSeedStationWithRecordToday(?string $recordDate = null, ?Carbon $createdAt = null): array
{
    $mill = BusinessUnit::factory()->create();
    $actor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => $mill->id]);
    $station = Station::factory()->forBusinessUnit($mill)->boilerRoom()->create();

    $record = BoilerRoomRecord::factory()->create([
        'station_id' => $station->id,
        'production_line_id' => $station->production_line_id,
        'date' => $recordDate ?? now()->toDateString(),
        'created_by' => $actor->id,
    ]);

    if ($createdAt !== null) {
        // created_at diisi lewat update karena factory/model timestamps
        // menimpanya saat insert.
        BoilerRoomRecord::query()->whereKey($record->id)->update(['created_at' => $createdAt]);
    }

    return [$station, $actor];
}

// Scenario: "Pilih Stasiun (Web) — berhasil"
it('berhasil: renders all 18 active station tiles linking to their Data Browser routes, all routed', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSee(route('data.weighbridge'), false);
    $response->assertSee(route('data.grading'), false);
    $response->assertSee(route('data.cages-track'), false);
    $response->assertSee(route('data.threshing'), false);
    $response->assertSee(route('data.pressing'), false);
    $response->assertSee(route('data.depricarping'), false);
    $response->assertSee(route('data.kernel-plant'), false);
    $response->assertSee(route('data.boiler-room'), false);
    $response->assertSee(route('data.clarification'), false);
    $response->assertSee(route('data.sterilizer'), false);
    $response->assertSee(route('data.storage-tank'), false);
    $response->assertSee(route('data.engine-room'), false);
    $response->assertSee(route('data.effluent-plant'), false);
    $response->assertSee(route('data.cpo-dispatch'), false);
    $response->assertSee(route('data.kernel-dispatch'), false);
    $response->assertSee(route('data.process-water'), false);
    $response->assertSee(route('data.solid-waste-disposal'), false);
    $response->assertSee(route('data.process-quality-control'), false);
    $response->assertSee('Weighbridge');
    $response->assertSee('Grading');
    $response->assertSee('Cages Track');
    $response->assertSee('Threshing');
    $response->assertSee('Pressing');
    $response->assertSee('Depricarping');
    $response->assertSee('Kernel Plant');
    $response->assertSee('Boiler Room');
    $response->assertSee('Clarification');
    $response->assertSee('Sterilizer');
    $response->assertSee('Storage Tank');
    $response->assertSee('Engine Room');
    $response->assertSee('Effluent Plant');
    $response->assertSee('CPO Dispatch');
    $response->assertSee('Kernel Dispatch');
    $response->assertSee('Process Water');
    $response->assertSee('Solid Waste Disposal');
    $response->assertSee('Process Quality Control');
    // No tile links to the javascript:void(0) placeholder any more — every
    // visible tile has a real route() href (see the assertions above).
    $response->assertDontSee('javascript:void(0)', false);
});

// Regression: a 2026-09-01 edit embedded literal '{{-- --}}' characters
// inside a Blade comment's own prose ("Remove the surrounding {{-- --}}
// comment markers..."), which terminated that comment early — Blade
// comments don't nest — and leaked the rest of the comment body as literal
// page text ("comment markers to re-enable a given tile. --}}"). Fixed by
// rewording the prose to not contain literal comment-delimiter characters.
it('never leaks raw Blade comment delimiters or comment prose into the rendered page', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee('{{--', false);
    $response->assertDontSee('--}}', false);
    $response->assertDontSee('comment markers');
});

// Inverted from the old "these must NOT render" assertion once the hide
// mechanism was removed (2026-09-16): every one of the 18 canonical stations
// must be reachable from this grid. Guards against a station silently
// dropping out of the tile list again.
it('renders every one of the 18 canonical stations — none hidden', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();

    foreach ([
        'weighbridge', 'grading', 'cages-track', 'threshing', 'pressing',
        'depricarping', 'kernel-plant', 'sterilizer', 'boiler-room',
        'engine-room', 'clarification', 'storage-tank', 'effluent-plant',
        'process-water', 'process-quality-control', 'solid-waste-disposal',
        'cpo-dispatch', 'kernel-dispatch',
    ] as $station) {
        $response->assertSee(route("data.{$station}"), false);
    }
});

// Scenario: "Pilih Stasiun (Web) — Klik Stasiun Disabled" — repurposed
// 2026-09-01: 0 placeholders remain (Sterilizer was the last one promoted),
// so there is nothing left to click-disabled. This now asserts the
// negative: no placeholder/disabled tile renders at all.
it('renders 0 placeholder tiles — dan asersinya menyebut kelas yang BENAR-BENAR dipakai markup', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertDontSee('Belum tersedia');
    $response->assertDontSee('Loading Ramp');

    // SAMPAI 2026-10-07 BARIS INI MENYEBUT `class="station-tile disabled"`,
    // dan sejak grid digerakkan data markup itu tidak pernah diproduksi lagi
    // — kelas keadaannya `is-inactive`. Asersinya karenanya menjadi HAMPA:
    // ia lolos bahkan bila setiap tile dirender nonaktif. Diperbaiki ke kelas
    // yang nyata, dan uji di bawahnya ("tile nonaktif...") membuktikan kelas
    // itu memang dapat diproduksi — tanpa pasangan itu, asersi ketiadaan di
    // sini tidak dapat dibedakan dari kelas yang salah tulis.
    // DAN ASERSINYA ATAS KELAS YANG DITERAPKAN, bukan atas nama kelas di
    // mana pun pada halaman: `.station-tile.is-inactive` TERDEFINISI di blok
    // <style> halaman ini, jadi namanya selalu muncul di sumber. Versi
    // pertama perbaikan ini memakai toContain('is-inactive') dan gagal
    // karena sebab itu — kelas kesalahan yang sama dengan .md-threshold pada
    // laporan stasiun.
    $response->assertDontSeeHtml('class="station-tile is-inactive"');
    $response->assertDontSee('Tidak dioperasikan');
});

// ─────────────────────────────────────────────────────────────────────────
// PAPAN STATUS (2026-10-07) — halaman ini berhenti menjadi peluncur statis
// ─────────────────────────────────────────────────────────────────────────

it('kepala halaman menghitung stasiun yang sudah ada input hari ini, dari 18 yang aktif', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    // Mill aktor uji ini baru dibuat, jadi nol stasiun punya input hari ini.
    // Penyebutnya tetap 18 — yang dihitung adalah stasiun AKTIF, bukan
    // stasiun yang punya data.
    $response->assertSeeText('0 dari 18 stasiun sudah ada input hari ini');
});

it('tile tanpa input hari ini MENYATAKANNYA, bukan mencetak angka 0', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();

    // Aturan yang sudah berlaku di seluruh laporan proyek ini: 0 yang
    // dicetak sebagai angka terbaca sebagai nol YANG TERUKUR. Di sini yang
    // benar adalah "belum ada yang mengisinya".
    $response->assertSeeText('Belum ada input hari ini');
    expect($response->getContent())->not->toContain('0 record hari ini');

    // Dan keadaannya berwarna — merah adalah warna keluarga input-data, dan
    // sejak hari ini ia menandai KEADAAN alih-alih menjadi latar bawaan.
    expect($response->getContent())->toContain('station-tile is-pending');
});

it('tile dengan input hari ini mencetak jumlahnya dan TIDAK berwarna merah', function () {
    [$station, $actor] = ppaSeedStationWithRecordToday();

    $response = $this->actingAs($actor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSeeText('1 record hari ini');
    $response->assertSeeText('1 dari 18 stasiun sudah ada input hari ini');

    // Tile stasiun itu berpindah keadaan: tenang, bukan merah.
    $html = $response->getContent();
    $tile = ppaTileMarkup($html, $station->type->value);
    expect($tile)->toContain('is-done');
    expect($tile)->not->toContain('is-pending');
});

it('DUA JENDELA WAKTU dibedakan: nol hari ini dapat berdampingan dengan input terakhir yang baru', function () {
    // Record bertanggal KEMARIN tetapi DIBUAT beberapa menit lalu. Inilah
    // keadaan yang membuat kedua jendela itu harus dilabeli: "0 hari ini"
    // benar, dan "input terakhir beberapa menit lalu" juga benar.
    [$station, $actor] = ppaSeedStationWithRecordToday(
        recordDate: now()->subDay()->toDateString(),
        createdAt: now()->subMinutes(3),
    );

    $response = $this->actingAs($actor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $tile = ppaTileMarkup($response->getContent(), $station->type->value);

    expect($tile)->toContain('Belum ada input hari ini');
    expect($tile)->toContain('Input terakhir');
    expect($tile)->not->toContain('Belum pernah ada input');

    // Dan keterangan yang menjelaskan mengapa keduanya bisa berdampingan
    // WAJIB ada — tanpa itu pasangan angka di atas terbaca seperti bug.
    $response->assertSeeText('tanpa dibatasi tanggal');
});

it('stasiun yang belum pernah menerima input menyatakannya, bukan menampilkan sel kosong', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSeeText('Belum pernah ada input');
});

it('nama tile dibaca dari master station_types, bukan dari literal di Blade', function () {
    StationType::query()->where('code', 'weighbridge')->update(['name' => 'Jembatan Timbang Diubah']);

    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    // Inilah dua-sumber-kebenaran yang dihapus: sebelum 2026-10-07 nama di
    // halaman ini literal, jadi rename di master mengubah /reports dan TIDAK
    // mengubah halaman ini.
    $response->assertSeeText('Jembatan Timbang Diubah');
    $response->assertDontSee('>Weighbridge<', false);
});

it('tile nonaktif di master dirender kelabu TANPA tautan — dan itu yang memberi gigi pada asersi ketiadaannya', function () {
    StationType::query()->where('code', 'sterilizer')->update(['is_active' => false]);

    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $tile = ppaTileMarkup($response->getContent(), 'sterilizer');

    expect($tile)->toContain('is-inactive');
    expect($tile)->toContain('Tidak dioperasikan');
    expect($tile)->toContain('aria-disabled="true"');
    // Tidak ditautkan: Data Browser-nya masih ada, tetapi master menyatakan
    // stasiun ini tidak dioperasikan dan meluncurkannya akan membantahnya.
    expect($tile)->not->toContain('<a ');

    // Penyebut kepala halaman ikut turun — yang dihitung stasiun AKTIF.
    $response->assertSeeText('0 dari 17 stasiun sudah ada input hari ini');
});

it('urutan tile mengikuti TILE_ORDER (urutan mobile), BUKAN station_types.sort_order', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    preg_match_all('/data-testid="station-tile-([a-z0-9-]+)"/', $response->getContent(), $matches);

    // Urutan kustom permintaan produk, dicerminkan ORDER BY berbasis CASE
    // pada mobile stationRepo.ts. station_types.sort_order adalah urutan
    // /reports dan DIMULAI weighbridge, grading, cages-track — berbeda sejak
    // posisi kedua, jadi uji ini menangkap pengurutan ulang yang tidak
    // disengaja.
    expect($matches[1])->toBe(ProductionProcessActivityService::TILE_ORDER);
    expect($matches[1][1])->toBe('pressing');
});

it('hitungan ter-scope mill aktor: record mill lain tidak pernah ikut', function () {
    [$stationOther] = ppaSeedStationWithRecordToday();

    // Supervisor dari mill LAIN membuka halaman: hitungannya tetap nol.
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSeeText('0 dari 18 stasiun sudah ada input hari ini');
    expect(ppaTileMarkup($response->getContent(), $stationOther->type->value))->toContain('Belum ada input hari ini');
});

it('Admin melihat SELURUH mill dan layar menyatakannya', function () {
    [$station] = ppaSeedStationWithRecordToday();

    $response = $this->actingAs($this->admin, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSeeText('Seluruh mill');
    // Admin tidak terikat mill, jadi record mill mana pun ikut terhitung.
    $response->assertSeeText('1 dari 18 stasiun sudah ada input hari ini');
});

it('peran terikat mill melihat keterangan mill akunnya, bukan seluruh mill', function () {
    $response = $this->actingAs($this->supervisor, 'web')->get('/production-process-activity');

    $response->assertOk();
    $response->assertSeeText('Mill akun Anda');
    $response->assertDontSee('Seluruh mill');
});

it('Weighbridge dihitung lewat record_datetime, kolom tanggal yang BERBEDA dari 17 stasiun lain', function () {
    // SATU-SATUNYA tabel record yang kolom tanggalnya bukan `date`.
    // Loop yang menganggap semuanya `date` melempar galat kolom-tidak-ada
    // tepat untuk stasiun dengan record terbanyak di basis data.
    $mill = BusinessUnit::factory()->create();
    $actor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => $mill->id]);
    $station = Station::factory()->forBusinessUnit($mill)->weighbridge()->create();

    WeighbridgeRecord::factory()->create([
        'station_id' => $station->id,
        'production_line_id' => $station->production_line_id,
        'record_datetime' => now(),
        'created_by' => $actor->id,
    ]);

    $response = $this->actingAs($actor, 'web')->get('/production-process-activity');

    $response->assertOk();
    expect(ppaTileMarkup($response->getContent(), 'weighbridge'))->toContain('1 record hari ini');
});

it('is reachable by Mill Management and Admin, same as Supervisor', function () {
    $this->actingAs($this->millManagement, 'web')->get('/production-process-activity')->assertOk();
    $this->actingAs($this->admin, 'web')->get('/production-process-activity')->assertOk();
});

it('returns 403 for Operator (mobile-only role, no web access per actor_permissions)', function () {
    $this->actingAs($this->operator, 'web')->get('/production-process-activity')->assertForbidden();
});

it('redirects an unauthenticated request to login', function () {
    $this->get('/production-process-activity')->assertRedirect('/login');
});
