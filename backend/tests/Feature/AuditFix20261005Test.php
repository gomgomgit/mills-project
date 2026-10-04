<?php

/**
 * AuditFix20261005Test — regresi temuan audit 2026-10-05 (web & API).
 *
 * JAM DIBEKUKAN pada 2026-10-20 10:00 WIB (sama dengan AuditFix20261004Test)
 * supaya tanggal Oktober di bawah tidak "di masa depan".
 */

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Data\DataBrowserBoilerRoom;
use App\Livewire\Data\DataBrowserCagesTrack;
use App\Livewire\Data\DataBrowserClarification;
use App\Livewire\Data\DataBrowserCpoDispatch;
use App\Livewire\Data\DataBrowserDepricarping;
use App\Livewire\Data\DataBrowserEffluentPlant;
use App\Livewire\Data\DataBrowserEngineRoom;
use App\Livewire\Data\DataBrowserGrading;
use App\Livewire\Data\DataBrowserKernelDispatch;
use App\Livewire\Data\DataBrowserKernelPlant;
use App\Livewire\Data\DataBrowserPressing;
use App\Livewire\Data\DataBrowserProcessQualityControl;
use App\Livewire\Data\DataBrowserProcessWater;
use App\Livewire\Data\DataBrowserSolidWasteDisposal;
use App\Livewire\Data\DataBrowserSterilizer;
use App\Livewire\Data\DataBrowserStorageTank;
use App\Livewire\Data\DataBrowserThreshing;
use App\Livewire\Data\DataBrowserWeighbridge;
use App\Livewire\Data\DetailBoilerRoom;
use App\Livewire\Data\DetailEffluentPlant;
use App\Livewire\Data\DetailEngineRoom;
use App\Livewire\Data\FormSterilizer;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', 'Asia/Jakarta'));

    $this->bu = BusinessUnit::factory()->create(['name' => 'Mill Audit B']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();
    $this->mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->bu)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// #4 Sterilizer checked_by_spv — non-Supervisor tidak boleh mengubah nilai
// tersimpan, di jalur mana pun.
// ---------------------------------------------------------------------------

function spvRecordBySupervisor($test): array
{
    $station = Station::factory()->forBusinessUnit($test->bu)->sterilizer()->create();
    openPeriodFor($test->bu->id, 'sterilizer');

    $created = $test->actingAs($test->supervisor, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $station->production_line_id,
        'sterilizer_id' => 'STR-SPV-05',
        'date' => '2026-10-03',
        'details' => [
            ['sterilizer_no' => '1', 'close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => true],
            ['sterilizer_no' => '2', 'close_door_time' => '09:00', 'open_door_time' => '10:00', 'checked_by_spv' => false],
        ],
    ])->assertCreated();

    return [$created->json('id'), $station];
}

it('[spv] API: Operator PATCH tanpa id detail (ganti semua baris) tidak menghapus centang SPV', function () {
    [$recordId] = spvRecordBySupervisor($this);

    $this->actingAs($this->operator, 'web')->patchJson("/api/sterilizer-records/{$recordId}", [
        'sterilizer_id' => 'STR-SPV-05',
        'date' => '2026-10-03',
        'details' => [
            ['sterilizer_no' => '1', 'close_door_time' => '07:00', 'open_door_time' => '08:00'],
            ['sterilizer_no' => '2', 'close_door_time' => '09:00', 'open_door_time' => '10:30', 'checked_by_spv' => true],
        ],
    ])->assertOk();

    $rows = SterilizerDetail::where('sterilizer_record_id', $recordId)->orderBy('sterilizer_no')->get();
    expect($rows->pluck('checked_by_spv')->map(fn ($v) => (bool) $v)->all())->toBe([true, false]);
});

it('[spv] API: Operator PATCH dengan id detail mengirim false tetap mempertahankan true', function () {
    [$recordId] = spvRecordBySupervisor($this);
    $rows = SterilizerDetail::where('sterilizer_record_id', $recordId)->orderBy('sterilizer_no')->get();

    $this->actingAs($this->operator, 'web')->patchJson("/api/sterilizer-records/{$recordId}", [
        'sterilizer_id' => 'STR-SPV-05',
        'date' => '2026-10-03',
        'details' => $rows->map(fn ($r) => [
            'id' => $r->id, 'sterilizer_no' => $r->sterilizer_no, 'close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => false,
        ])->all(),
    ])->assertOk();

    expect((bool) SterilizerDetail::whereKey($rows[0]->id)->value('checked_by_spv'))->toBeTrue();
});

it('[spv] Web: Mill Management menyimpan Form Sterilizer mode edit tidak mengubah centang SPV', function () {
    [$recordId] = spvRecordBySupervisor($this);

    Livewire::actingAs($this->mm)->test(FormSterilizer::class, ['id' => $recordId])
        ->set('detailRows.0.checked_by_spv', false)
        ->set('detailRows.1.checked_by_spv', true)
        ->call('save')
        ->assertHasNoErrors();

    $rows = SterilizerDetail::where('sterilizer_record_id', $recordId)->orderBy('sterilizer_no')->get();
    expect($rows->pluck('checked_by_spv')->map(fn ($v) => (bool) $v)->all())->toBe([true, false]);
});

it('[spv] Web: Mill Management menghapus lalu menambah baris yang sama tidak bisa menyetel SPV', function () {
    [$recordId] = spvRecordBySupervisor($this);

    Livewire::actingAs($this->mm)->test(FormSterilizer::class, ['id' => $recordId])
        ->call('addDetailRow')
        ->set('detailRows.2.close_door_time', '11:00')
        ->set('detailRows.2.checked_by_spv', true)
        ->call('save');

    expect(SterilizerDetail::where('sterilizer_record_id', $recordId)->where('checked_by_spv', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// #5 Format ekspor Data Browser seragam lewat ExportValue
// ---------------------------------------------------------------------------

function audit05CsvRows(string $body): array
{
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    $lines = array_values(array_filter(explode("\n", trim($body)), fn ($line) => $line !== ''));

    return array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines);
}

function audit05Export($test, string $endpoint): array
{
    $response = $test->actingAs($test->supervisor, 'web')->get("/api/{$endpoint}/export?".http_build_query([
        'date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'format' => 'csv',
    ]));
    $response->assertOk();

    $rows = audit05CsvRows($response->streamedContent());

    return [array_flip($rows[0]), $rows[1]];
}

it('#5 ekspor CPO Dispatch: Time In / Time Out HH:MM tanpa detik', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->cpoDispatch()->create();
    $record = CpoDispatchRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
    CpoDispatchDetail::factory()->forRecord($record)->create(['event_date' => '2026-09-02', 'time_in' => '07:05:00', 'time_out' => '08:40:00']);

    [$col, $row] = audit05Export($this, 'cpo-dispatch-records');

    expect($row[$col['Time In']])->toBe('07:05');
    expect($row[$col['Time Out']])->toBe('08:40');
});

it('#5 ekspor Boiler Room: Blowdown/Sootblowing Executed Ya/Tidak, bukan y/n', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->boilerRoom()->create();
    $record = BoilerRoomRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
    BoilerRoomDetail::factory()->forRecord($record)->create(['time_slot' => '07:00:00', 'blowdown_executed' => 'y', 'sootblowing_executed' => 'n']);

    [$col, $row] = audit05Export($this, 'boiler-room-records');

    expect($row[$col['Blowdown Executed']])->toBe('Ya');
    expect($row[$col['Sootblowing Executed']])->toBe('Tidak');
});

it('#5 ekspor Effluent Plant: status berlabel seperti layar Detail', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->effluentPlant()->create();
    $record = EffluentPlantRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
    EffluentPlantDetail::factory()->forRecord($record)->create([
        'time_slot' => '07:00:00', 'biogas_flare_status' => 'fault', 'dosing_pump_1_status' => 'run', 'sludge_dewatering_status' => 'stop',
    ]);

    [$col, $row] = audit05Export($this, 'effluent-plant-records');

    expect($row[$col['Biogas Flare Status']])->toBe('Fault');
    expect($row[$col['Dosing Pump 1 Status']])->toBe('Run');
    expect($row[$col['Sludge Dewatering Status']])->toBe('Stop');
});

it('#5 ekspor Engine Room: status diesel gen berlabel seperti layar Detail', function () {
    $station = Station::factory()->forBusinessUnit($this->bu)->engineRoom()->create();
    $record = EngineRoomRecord::factory()->forStation($station)->onDate('2026-09-02')->create();
    EngineRoomDetail::factory()->forRecord($record)->create(['time_slot' => '07:00:00', 'diesel_gen_1_status' => 'standby', 'diesel_gen_2_status' => 'off']);

    [$col, $row] = audit05Export($this, 'engine-room-records');

    expect($row[$col['Diesel Gen 1 Status']])->toBe('Standby');
    expect($row[$col['Diesel Gen 2 Status']])->toBe('Off');
});

// Layar Detail web memakai label yang sama dengan ekspor (konvensi ekspor:
// label ikut layar Detail).
it('#5 layar Detail Boiler Room / Effluent Plant / Engine Room menampilkan label yang sama dengan ekspor', function () {
    $boiler = BoilerRoomRecord::factory()->forStation(Station::factory()->forBusinessUnit($this->bu)->boilerRoom()->create())->onDate('2026-09-02')->create();
    BoilerRoomDetail::factory()->forRecord($boiler)->create(['time_slot' => '07:00:00', 'blowdown_executed' => 'y', 'sootblowing_executed' => 'n']);
    $effluent = EffluentPlantRecord::factory()->forStation(Station::factory()->forBusinessUnit($this->bu)->effluentPlant()->create())->onDate('2026-09-02')->create();
    EffluentPlantDetail::factory()->forRecord($effluent)->create(['time_slot' => '07:00:00', 'biogas_flare_status' => 'fault', 'dosing_pump_1_status' => 'run', 'sludge_dewatering_status' => 'stop']);
    $engine = EngineRoomRecord::factory()->forStation(Station::factory()->forBusinessUnit($this->bu)->engineRoom()->create())->onDate('2026-09-02')->create();
    EngineRoomDetail::factory()->forRecord($engine)->create(['time_slot' => '07:00:00', 'diesel_gen_1_status' => 'standby', 'diesel_gen_2_status' => 'off']);

    $cells = fn (string $html) => array_map(fn ($c) => trim(html_entity_decode(strip_tags($c))), (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $html, $m) ? $m[1] : []));

    $boilerCells = $cells(Livewire::actingAs($this->supervisor)->test(DetailBoilerRoom::class, ['id' => $boiler->id])->html());
    expect($boilerCells)->toContain('Ya')->toContain('Tidak')->not->toContain('y')->not->toContain('n');

    $effluentCells = $cells(Livewire::actingAs($this->supervisor)->test(DetailEffluentPlant::class, ['id' => $effluent->id])->html());
    expect($effluentCells)->toContain('Fault')->toContain('Run')->toContain('Stop')->not->toContain('fault');

    $engineCells = $cells(Livewire::actingAs($this->supervisor)->test(DetailEngineRoom::class, ['id' => $engine->id])->html());
    expect($engineCells)->toContain('Standby')->toContain('Off')->not->toContain('standby');
});

// Ke-18 ekspor Data Browser memformat sel lewat ExportValue, bukan Display
// (Display memberi "-" untuk kosong dan pemisah ribuan — bukan untuk file).
it('#5 tidak ada ekspor Data Browser yang memakai Display:: di dalam export()', function () {
    foreach (glob(app_path('Services/*RecordService.php')) as $file) {
        $source = file_get_contents($file);
        if (! preg_match('/function export\(.*?function fileMetaFor/s', $source, $m)) {
            continue;
        }
        expect(str_contains($m[0], 'Display::'))->toBeFalse(basename($file).' memakai Display:: di export()');
    }
});

// ---------------------------------------------------------------------------
// #6 Filter production_line_id Data Browser bukan UUID — diabaikan (= semua
// line), tidak pernah sampai ke query. Di PostgreSQL nilai seperti itu pada
// kolom uuid memicu SQLSTATE 22P02: jalur Livewire → 500, jalur API → 422.
// SQLite menerimanya diam-diam, jadi uji ini memeriksa BINDING query-nya.
// ---------------------------------------------------------------------------

dataset('data_browser_stations', [
    'weighbridge' => [DataBrowserWeighbridge::class, 'weighbridge-records'],
    'grading' => [DataBrowserGrading::class, 'grading-records'],
    'cages-track' => [DataBrowserCagesTrack::class, 'cages-track-records'],
    'threshing' => [DataBrowserThreshing::class, 'threshing-records'],
    'pressing' => [DataBrowserPressing::class, 'pressing-records'],
    'depricarping' => [DataBrowserDepricarping::class, 'depricarping-records'],
    'kernel-plant' => [DataBrowserKernelPlant::class, 'kernel-plant-records'],
    'solid-waste-disposal' => [DataBrowserSolidWasteDisposal::class, 'solid-waste-disposal-records'],
    'process-water' => [DataBrowserProcessWater::class, 'process-water-records'],
    'kernel-dispatch' => [DataBrowserKernelDispatch::class, 'kernel-dispatch-records'],
    'cpo-dispatch' => [DataBrowserCpoDispatch::class, 'cpo-dispatch-records'],
    'effluent-plant' => [DataBrowserEffluentPlant::class, 'effluent-plant-records'],
    'storage-tank' => [DataBrowserStorageTank::class, 'storage-tank-records'],
    'engine-room' => [DataBrowserEngineRoom::class, 'engine-room-records'],
    'boiler-room' => [DataBrowserBoilerRoom::class, 'boiler-room-records'],
    'clarification' => [DataBrowserClarification::class, 'clarification-records'],
    'process-quality-control' => [DataBrowserProcessQualityControl::class, 'process-quality-control-records'],
    'sterilizer' => [DataBrowserSterilizer::class, 'sterilizer-records'],
]);

function audit06BindingsSeen(callable $run): array
{
    $bindings = [];
    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            $bindings[] = $binding;
        }
    });
    $run();

    return $bindings;
}

it('#6 Livewire Data Browser: production_line_id bukan UUID diabaikan, tidak sampai ke query', function (string $component, string $endpoint) {
    foreach ([$this->supervisor, $this->admin] as $actor) {
        $bindings = audit06BindingsSeen(function () use ($actor, $component) {
            Livewire::actingAs($actor)->test($component)
                ->set('production_line_id', 'bukan-uuid')
                ->assertOk()
                ->assertSet('production_line_id', '');
        });

        expect($bindings)->not->toContain('bukan-uuid');
    }
})->with('data_browser_stations');

it('#6 API list & export: production_line_id bukan UUID diabaikan (200), tidak sampai ke query', function (string $component, string $endpoint) {
    $bindings = audit06BindingsSeen(function () use ($endpoint) {
        $this->actingAs($this->supervisor, 'web')
            ->getJson("/api/{$endpoint}?production_line_id=bukan-uuid")
            ->assertOk();
        $this->actingAs($this->admin, 'web')
            ->get("/api/{$endpoint}/export?production_line_id=bukan-uuid&business_unit_id=bukan-uuid-juga&format=csv")
            ->assertOk();
    });

    expect($bindings)->not->toContain('bukan-uuid')->not->toContain('bukan-uuid-juga');
})->with('data_browser_stations');

// ---------------------------------------------------------------------------
// #8b Reset Password oleh Admin mencabut SEMUA token Sanctum dan sesi web
// akun itu (keputusan 2026-10-05), di jalur Livewire DAN API. Pengecualian:
// Admin yang mereset password-nya SENDIRI tetap memegang sesi web saat ini.
// Sesi "lama" disimulasikan dengan stempel login sesi yang lebih tua dari
// reset-nya. Pemeriksaan sesi memakai rute WEB (atau API dengan Referer
// stateful): request API tanpa Referer di uji tidak punya sesi sama sekali,
// jadi EnsureUserIsActive tidak punya sesi untuk diperiksa.
// ---------------------------------------------------------------------------

function audit08OldSession(): array
{
    return [EnsureUserIsActive::SESSION_AUTH_AT => now()->subHour()->getTimestampMs()];
}

function audit08Payload(User $user, array $extra = []): array
{
    return array_merge([
        'username' => $user->username,
        'name' => $user->name,
        'role' => $user->role->value,
        'business_unit_id' => $user->business_unit_id ?? '',
    ], $extra);
}

it('#8b Livewire: reset password user lain mencabut token Sanctum dan sesi web lamanya', function () {
    $this->supervisor->createToken('hp-1');
    $this->supervisor->createToken('hp-2');

    // Sesi lama supervisor masih jalan sebelum reset.
    $this->actingAs($this->supervisor, 'web')->withSession(audit08OldSession())
        ->get('/data/weighbridge')->assertOk();

    Livewire::actingAs($this->admin)->test(KelolaUserRole::class)
        ->call('openEditForm', $this->supervisor->id)
        ->set('form.password', 'BaruSekali1!')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->supervisor->tokens()->count())->toBe(0);

    // fresh(): di aplikasi nyata user dimuat ulang dari DB setiap request;
    // actingAs() menyimpan instance lama.
    $this->actingAs($this->supervisor->fresh(), 'web')->withSession(audit08OldSession())
        ->get('/data/weighbridge')->assertRedirect(route('login'));
});

it('#8b API: PATCH /api/users/{id} dengan password mencabut token dan sesi web lama user itu', function () {
    $this->supervisor->createToken('hp-1');

    $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->supervisor->id}", audit08Payload($this->supervisor, ['password' => 'BaruSekali1!']))
        ->assertOk();

    expect($this->supervisor->tokens()->count())->toBe(0);

    $this->actingAs($this->supervisor->fresh(), 'web')->withSession(audit08OldSession())
        ->get('/data/weighbridge')->assertRedirect(route('login'));
});

it('#8b sesi yang dibuat SESUDAH reset (login ulang dengan password baru) tetap berlaku', function () {
    $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->supervisor->id}", audit08Payload($this->supervisor, ['password' => 'BaruSekali1!']))
        ->assertOk();

    $this->travel(1)->seconds();
    $this->actingAs($this->supervisor->fresh(), 'web')->withSession([EnsureUserIsActive::SESSION_AUTH_AT => now()->getTimestampMs()])
        ->get('/data/weighbridge')->assertOk();
});

it('#8b tanpa password (edit biasa) tidak mencabut apa pun', function () {
    $this->supervisor->createToken('hp-1');

    $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->supervisor->id}", audit08Payload($this->supervisor, ['name' => 'Nama Baru']))
        ->assertOk();

    expect($this->supervisor->tokens()->count())->toBe(1);
    $this->actingAs($this->supervisor, 'web')->withSession(audit08OldSession())
        ->get('/data/weighbridge')->assertOk();
});

it('#8b Admin mereset password SENDIRI lewat API: token dicabut, sesi web saat ini tetap', function () {
    $this->admin->createToken('lain');

    $this->actingAs($this->admin, 'web')->withSession(audit08OldSession())->withHeader('Referer', 'http://localhost/')
        ->patchJson("/api/users/{$this->admin->id}", audit08Payload($this->admin, ['password' => 'AdminBaru1!']))
        ->assertOk();

    expect($this->admin->tokens()->count())->toBe(0);

    // Request berikutnya di sesi yang SAMA (stempel sudah diperbarui).
    $this->actingAs($this->admin->fresh(), 'web')->get('/users')->assertOk();
});

it('#8b Admin mereset password SENDIRI lewat Livewire: sesi web saat ini tetap', function () {
    $this->actingAs($this->admin, 'web')->withSession(audit08OldSession())->get('/users')->assertOk();

    // Harness Livewire::test() tidak melewati StartSession, sedangkan
    // /livewire/update yang sungguhan lewat grup 'web' (sesi termuat).
    // Mulai store sesi yang sama supaya kondisinya setara; jalur browser
    // nyatanya dibuktikan di e2e-web/tests/audit-fix-20261005.spec.ts.
    app('session')->driver()->start();

    Livewire::actingAs($this->admin)->test(KelolaUserRole::class)
        ->call('openEditForm', $this->admin->id)
        ->set('form.password', 'AdminBaru1!')
        ->call('save')
        ->assertHasNoErrors();

    $this->actingAs($this->admin->fresh(), 'web')->get('/users')->assertOk();
});

it('#8b sesi lama Admin di perangkat LAIN tetap dicabut saat ia mereset password sendiri', function () {
    $this->actingAs($this->admin, 'web')->patchJson("/api/users/{$this->admin->id}", audit08Payload($this->admin, ['password' => 'AdminBaru1!']))
        ->assertOk();

    // Sesi lain = stempel lama yang TIDAK diperbarui reset tadi.
    $this->actingAs($this->admin->fresh(), 'web')->withSession(audit08OldSession())
        ->get('/users')->assertRedirect(route('login'));
});
