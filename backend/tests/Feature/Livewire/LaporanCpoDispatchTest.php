<?php

/**
 * LaporanCpoDispatchTest (Livewire/komponen) — screen-166--laporan-cpo-dispatch-web, terhadap
 * App\Livewire\Dashboard\LaporanCpoDispatch dan markup ter-render-nya.
 *
 * BERKAS INI MENUTUP CELAH YANG TERUKUR: pada run coverage 2026-10-28 komponen
 * ini hanya 73-77% dan tidak punya berkas Livewire sama sekali, padahal
 * ketujuh belas laporan saudaranya punya (Laporan<Stasiun>Test). Fokusnya
 * APA YANG DIPUBLIKASIKAN KOMPONEN dan APA YANG ADA DI MARKUP: akses, pemilih
 * mill/line/periode, penolakan periode asing, dan aksi ekspor. Aturan
 * agregasinya ada di tests/Unit/Services dan kontrak HTTP-nya di
 * tests/Feature/Api/LaporanCpoDispatchTest.php.
 *
 * Tiga aturan yang membentuk setiap asersi di bawah:
 *  - klaim tentang SATU ELEMEN disasarkan ke elemen itu (testid), tidak atas
 *    seluruh HTML;
 *  - ketiadaan sebuah blok diasersikan atas ketiadaan testid-nya, bukan atas
 *    prosa;
 *  - tanggal fixture selalu dipaku dan relatif, supaya `$this->periodA` selalu
 *    sudah selesai dan tak ada uji yang bergantung pada kebetulan tanggal.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanCpoDispatch;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Period;
use App\Models\Station;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Satu record induk; TANGGALNYA argumen posisional. */
function cdLwRecord(Station $station, string $date): CpoDispatchRecord
{
    return CpoDispatchRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(['status' => RecordStatus::Saved, 'note' => null]);
}

/** Satu baris detail. */
function cdLwDetail(CpoDispatchRecord $record, array $values = []): CpoDispatchDetail
{
    return CpoDispatchDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $record->date->toDateString(),
            'net_weight_mt' => 1.0,
        ], $values));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->cpoDispatch()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->cpoDispatch()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::CpoDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Alpha')
        ->create();
});

it('Operator ditolak pada RUTE WEB dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya.
    $this->actingAs($this->operator, 'web')->get('/reports/cpo-dispatch')->assertStatus(403);

    // Peran yang memang dibuka benar-benar sampai ke halamannya — tanpa ini,
    // 403 di atas tetap hijau pada rute yang menolak semua orang.
    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $this->actingAs($user, 'web')
            ->get('/reports/cpo-dispatch')
            ->assertOk()
            ->assertSee('data-testid="laporan-cpo-dispatch"', false);
    }

    $html = Livewire::actingAs($this->operator)->test(LaporanCpoDispatch::class)->html();

    expect($html)->toContain('Forbidden');
    // NOL DATA TERPUBLIKASI: diasersikan atas ketiadaan akar komponennya.
    expect($html)->not->toContain('data-testid="laporan-cpo-dispatch"');
});

it('Supervisor: tanpa pemilih mill, line dan periode yang dipilih menerbitkan summary mill sendiri', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanCpoDispatch::class);

    expect($component->viewData('needsMillSelection'))->toBeFalse();
    expect($component->viewData('hasNoMillForAccount'))->toBeFalse();
    expect($component->viewData('businessUnitName'))->toBe('Mill Alpha');
    expect(array_column($component->viewData('productionLineOptions'), 'id'))->toBe([$this->lineA]);
    expect(array_column($component->viewData('periods'), 'id'))->toBe([(string) $this->periodA->id]);

    $component->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    $this->assertNotNull($summary);
    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['production_line']['id'])->toBe($this->lineA);
    expect($summary['period']['id'])->toBe((string) $this->periodA->id);
    expect($component->viewData('forbidden'))->toBeFalse();
    expect($component->viewData('selectedPeriod')['id'])->toBe((string) $this->periodA->id);
    expect($component->viewData('selectedProductionLine')['id'])->toBe($this->lineA);

    $html = $component->html();

    $this->assertStringContainsString('data-testid="laporan-cpo-dispatch"', $html);
    $this->assertStringNotContainsString('data-testid="forbidden-notice"', $html);
    $this->assertStringNotContainsString('data-testid="select-production-line-hint"', $html);
    $this->assertStringNotContainsString('data-testid="mill-select-hint"', $html);
});

it('Production Line wajib dipilih: tanpa line tidak ada summary dan petunjuknya dirender', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('periodId', (string) $this->periodA->id);

    expect($component->viewData('needsProductionLineSelection'))->toBeTrue();
    $this->assertNull($component->viewData('summary'));
    $this->assertStringContainsString('data-testid="select-production-line-hint"', $component->html());

    // Line milik mill LAIN yang dipaksa masuk disaring, bukan dipakai.
    $component->set('productionLineId', $this->lineB);

    expect($component->viewData('needsProductionLineSelection'))->toBeTrue();
    $this->assertNull($component->viewData('summary'));
});

it('Admin memilih mill lebih dulu, dan mengganti mill mengosongkan periode DAN production line', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanCpoDispatch::class);

    // SEBELUM mill dipilih: pemilih mill ada, daftar line dan periode kosong.
    expect($component->viewData('needsMillSelection'))->toBeTrue();
    expect($component->viewData('productionLineOptions'))->toBe([]);
    expect($component->viewData('periods'))->toBe([]);
    $this->assertNull($component->viewData('summary'));
    $this->assertStringContainsString('data-testid="mill-select-hint"', $component->html());
    expect(array_column($component->viewData('businessUnitOptions'), 'id'))
        ->toContain((string) $this->businessUnitA->id, (string) $this->businessUnitB->id);

    $component->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $this->assertNotNull($component->viewData('summary'));

    $component->set('businessUnitId', (string) $this->businessUnitB->id);

    // Keduanya kembali kosong — diasersikan atas STATE, bukan atas HTML.
    $component->assertSet('productionLineId', '');
    $component->assertSet('periodId', '');
    $this->assertNull($component->viewData('summary'));
    expect($component->viewData('periods'))->toBe([]);
    expect(array_column($component->viewData('productionLineOptions'), 'id'))->toBe([$this->lineB]);

    // Tidak ada penolakan atas sesuatu yang tidak dilakukan pengguna.
    expect($component->viewData('forbidden'))->toBeFalse();
    expect($component->html())->not->toContain('data-testid="forbidden-notice"');
});

it('mill kiriman dari peran terikat mill DIABAIKAN: summary tetap milik mill akun', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    $summary = $component->viewData('summary');

    $this->assertNotNull($summary);
    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($component->viewData('needsMillSelection'))->toBeFalse();
});

it('akun belum terhubung ke mill: petunjuknya dirender, tanpa pemilih mill dan tanpa summary', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $component = Livewire::actingAs($orphan)->test(LaporanCpoDispatch::class);

    expect($component->viewData('hasNoMillForAccount'))->toBeTrue();
    expect($component->viewData('businessUnitOptions'))->toBe([]);
    $this->assertNull($component->viewData('summary'));
    $this->assertStringContainsString('data-testid="no-mill-hint"', $component->html());
    $this->assertStringNotContainsString('data-testid="mill-select-hint"', $component->html());
});

it('periode milik mill lain ditolak SECARA TERLIHAT, tanpa satu angka pun dirender', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::CpoDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Beta')
        ->create();

    cdLwDetail(cdLwRecord($this->stationB, $this->day1), ['net_weight_mt' => 77.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('productionLineId', $this->lineA)
        // Di-set PAKSA seolah dari klien.
        ->set('periodId', (string) $periodeB->id);

    expect($component->viewData('forbidden'))->toBeTrue();
    $this->assertNull($component->viewData('summary'));
    $this->assertStringContainsString('data-testid="forbidden-notice"', $component->html());
});

it('periode tanpa satu pun data: kerangka tetap terbit, bukan galat dan bukan forbidden', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    expect($component->viewData('forbidden'))->toBeFalse();
    $this->assertNotNull($component->viewData('summary'));
    expect($component->viewData('summary')['period']['id'])->toBe((string) $this->periodA->id);
    $this->assertStringContainsString('data-testid="laporan-cpo-dispatch"', $component->html());
});

it('mill belum punya periode yang mencakup stasiun ini: daftar periode kosong dan tidak ada summary', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('productionLineId', $this->lineA);

    expect($component->viewData('periods'))->toBe([]);
    $this->assertNull($component->viewData('summary'));
    expect($component->viewData('forbidden'))->toBeFalse();
});

it('exportCsv: null tanpa line atau tanpa periode; aliran berkas bila keduanya terpilih', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanCpoDispatch::class);

    // Belum ada line dan periode: tidak ada yang dapat diekspor, dan itu bukan galat.
    $component->call('exportCsv')->assertOk();
    $component->assertNoRedirect();

    // Tanpa periode (line saja): tetap tidak mengekspor.
    $component->set('productionLineId', $this->lineA)->call('exportCsv')->assertOk();

    $component->set('periodId', (string) $this->periodA->id);

    // Keduanya terpilih: komponen memulangkan unduhan.
    $component->call('exportCsv')->assertFileDownloaded();
    $component->call('exportCsv', 'excel')->assertFileDownloaded();
});

it('exportCsv dipanggil LANGSUNG: format tak dikenal ditolak 422, dan Admin tanpa mill tidak mengekspor', function () {
    $supervisor = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    // Format di luar daftar tertutup diteruskan apa adanya, sehingga SERVICE
    // yang menolaknya — tidak ditelan komponen.
    expect(fn () => $supervisor->instance()->exportCsv('pdf'))->toThrow(ValidationException::class);

    $result = Livewire::actingAs($this->admin)
        ->test(LaporanCpoDispatch::class)
        ->instance()
        ->exportCsv();

    expect($result)->toBeNull();

    // Dan dengan mill + line + periode terpilih, Admin menerima aliran berkas
    // dari komponen langsung (bukan lewat toolbar).
    $admin = Livewire::actingAs($this->admin)
        ->test(LaporanCpoDispatch::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    expect($admin->instance()->exportCsv())->toBeInstanceOf(StreamedResponse::class);
});

it('layar hanya membaca: nol kontrol tulis dan nol metode publik yang menulis', function () {
    cdLwDetail(cdLwRecord($this->stationA, $this->day1), ['net_weight_mt' => 2.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCpoDispatch::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    $this->assertStringNotContainsString('<form', $html);

    foreach (['wire:submit', 'wire:click="save', 'wire:click="delete', 'wire:click="store'] as $tulis) {
        $this->assertStringNotContainsString($tulis, $html, "kontrol tulis {$tulis} dirender");
    }

    $publik = array_values(array_filter(
        array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(LaporanCpoDispatch::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        ),
        fn (string $name) => (new ReflectionMethod(LaporanCpoDispatch::class, $name))->getDeclaringClass()->getName()
            === LaporanCpoDispatch::class,
    ));

    sort($publik);

    expect($publik)->toBe([
        'exportCsv', 'mount', 'render', 'updatedBusinessUnitId',
        'updatedPeriodId', 'updatingBusinessUnitId',
    ]);
});
