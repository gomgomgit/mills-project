<?php

/**
 * FilterBarTest — filter bersama x-filter.bar (components/filter/*,
 * components/filter-assets.blade.php), dipasang 2026-10-05 di 18 Data
 * Browser, master data, Kelola User, Mills Setting, dan Laporan Manajemen.
 *
 * Yang dikunci di sini:
 *  - Reset filter mengembalikan SEMUA filter ke bawaan deklarasinya dan
 *    halaman ke 1 — tanpa menyentuh state lain (mode tampilan Kelola Mesin).
 *  - Hitungan "filter aktif" tidak menghitung mill yang dipaku ke akun
 *    Supervisor (bukan pilihan user di layar itu).
 *  - Akun terikat mill melihat mill-nya sebagai KETERANGAN, bukan pemilih
 *    dan bukan input disabled/readonly (konvensi web).
 *  - Setiap kelas fb-* yang dipakai markup punya definisi CSS (standar
 *    kerapian: kelas tanpa definisi = cacat tampilan yang lolos test
 *    perilaku). Diperiksa terhadap SUMBER CSS bersama, dan halaman yang
 *    dirender memuat aset itu.
 */

use App\Enums\UserRole;
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
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Livewire\MasterData\KelolaCompany;
use App\Livewire\MasterData\KelolaMachinery;
use App\Livewire\MasterData\KelolaPeriodePelaporan;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Livewire\MasterData\KelolaStation;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

$dataBrowsers = [
    DataBrowserBoilerRoom::class, DataBrowserCagesTrack::class, DataBrowserClarification::class,
    DataBrowserCpoDispatch::class, DataBrowserDepricarping::class, DataBrowserEffluentPlant::class,
    DataBrowserEngineRoom::class, DataBrowserGrading::class, DataBrowserKernelDispatch::class,
    DataBrowserKernelPlant::class, DataBrowserPressing::class, DataBrowserProcessQualityControl::class,
    DataBrowserProcessWater::class, DataBrowserSolidWasteDisposal::class, DataBrowserSterilizer::class,
    DataBrowserStorageTank::class, DataBrowserThreshing::class, DataBrowserWeighbridge::class,
];

beforeEach(function () {
    $this->mill = BusinessUnit::factory()->create(['name' => 'Mill Filter A']);
    $this->line = ProductionLine::factory()->forBusinessUnit($this->mill)->create(['name' => 'Line Filter A']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->mill)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->mill)->create();
});

it('Data Browser: Reset filter mengembalikan semua filter ke bawaan dan halaman ke 1', function (string $component) {
    $test = Livewire::actingAs($this->admin)
        ->test($component)
        ->set('date_from', '2026-08-01')
        ->set('date_to', '2026-08-31')
        ->set('business_unit_id', $this->mill->id)
        ->set('production_line_id', $this->line->id)
        ->set('page', 3);

    if ($component === DataBrowserWeighbridge::class) {
        $test->set('weighbridge_type', 'receive');
    }

    expect($test->instance()->activeFilterCount())->toBe($component === DataBrowserWeighbridge::class ? 5 : 4);
    expect($test->html())->toContain('data-testid="filter-reset"');

    $test->call('resetFilters')
        ->assertSet('date_from', '')
        ->assertSet('date_to', '')
        ->assertSet('business_unit_id', '')
        ->assertSet('production_line_id', '')
        ->assertSet('page', 1);

    if ($component === DataBrowserWeighbridge::class) {
        $test->assertSet('weighbridge_type', '');
    }

    expect($test->instance()->activeFilterCount())->toBe(0);
    expect($test->html())
        ->toContain('data-testid="filter-bar"')
        ->not->toContain('data-testid="filter-reset"')
        ->not->toContain('data-testid="filter-active-count"');
})->with($dataBrowsers);

it('Data Browser: Supervisor melihat mill-nya sebagai keterangan, tidak dihitung filter aktif, dan reset tidak melebarkan cakupan', function (string $component) {
    $test = Livewire::actingAs($this->supervisor)->test($component);
    $html = $test->html();

    // Keterangan statis, bukan combobox Business Unit, bukan input terkunci.
    expect($html)
        ->toContain('data-testid="business-unit-current"')
        ->toContain('Mill Filter A')
        ->not->toContain('id="business_unit_id"');

    $bar = substr($html, strpos($html, 'data-testid="filter-bar"'));
    $bar = substr($bar, 0, strpos($bar, '</section>'));
    expect($bar)->not->toMatch('/\s(disabled|readonly)[\s>=]/');

    // render() memaku business_unit_id ke mill akun: menyimpang dari ''
    // tapi BUKAN pilihan user, jadi tidak dihitung.
    $test->assertSet('business_unit_id', $this->mill->id);
    expect($test->instance()->activeFilterCount(['business_unit_id']))->toBe(0);
    expect($html)->not->toContain('data-testid="filter-active-count"');

    $test->set('date_from', '2026-08-01');
    expect($test->html())->toContain('1 filter aktif');

    $test->call('resetFilters')
        ->assertSet('date_from', '')
        ->assertSet('business_unit_id', $this->mill->id);
})->with($dataBrowsers);

it('Data Browser: Admin tetap mendapat pemilih Business Unit dengan opsi semua mill', function () {
    $html = Livewire::actingAs($this->admin)->test(DataBrowserBoilerRoom::class)->html();

    expect($html)
        ->toContain('id="business_unit_id"')
        ->toContain('Semua Business Unit')
        ->not->toContain('data-testid="business-unit-current"');
});

it('master data & Kelola User: Reset filter mengembalikan filter ke bawaan', function (string $component, array $filters) {
    $test = Livewire::actingAs($this->admin)->test($component);

    foreach ($filters as $property => $value) {
        $test->set($property, is_callable($value) ? $value($this) : $value);
    }
    $test->set('page', 2);

    expect($test->instance()->activeFilterCount())->toBe(count($filters));

    $test->call('resetFilters')->assertSet('page', 1);

    foreach (array_keys($filters) as $property) {
        $test->assertSet($property, '');
    }

    expect($test->instance()->activeFilterCount())->toBe(0);
})->with([
    'Business Unit' => [KelolaBusinessUnit::class, ['filterCompanyId' => fn ($t) => $t->mill->company_id]],
    'Company' => [KelolaCompany::class, ['filterCorporateId' => fn ($t) => $t->mill->company->corporate_id]],
    'Production Line' => [KelolaProductionLine::class, ['filterBusinessUnitId' => fn ($t) => $t->mill->id]],
    'Station' => [KelolaStation::class, ['filterBusinessUnitId' => fn ($t) => $t->mill->id, 'filterProductionLineId' => fn ($t) => $t->line->id]],
    'Periode' => [KelolaPeriodePelaporan::class, ['filterBusinessUnitId' => fn ($t) => $t->mill->id, 'filterStatus' => 'open']],
    'User' => [KelolaUserRole::class, ['filterRole' => 'supervisor', 'filterBusinessUnitId' => fn ($t) => $t->mill->id]],
]);

it('Kelola Mesin: Reset filter mengosongkan pencarian, filter, dan grup terbuka tetapi mempertahankan mode tampilan', function () {
    $test = Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('setViewMode', 'rata')
        ->set('search', 'MG-')
        ->set('filterMachineryGroupId', 'apa-saja');

    // Mode Rata: filter Station tersembunyi, tidak dihitung.
    expect($test->html())->toContain('2 filter aktif')->toContain('data-testid="search-clear"');

    $test->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterMachineryGroupId', '')
        ->assertSet('filterStationId', '')
        ->assertSet('expandedGroupIds', [])
        ->assertSet('viewMode', 'rata');

    expect($test->html())->not->toContain('data-testid="search-clear"');
});

it('Kelola Mesin: jumlah mesin tanpa grup tampil di ringkasan bar pada mode Grup saja', function () {
    $station = Station::factory()->forProductionLine($this->line)->create();
    Machinery::factory()->create(['machinery_group_id' => null, 'station_id' => $station->id, 'production_line_id' => $this->line->id, 'equipment_code' => 'EQ-FB-LEPAS', 'name' => 'Lepas']);

    $test = Livewire::actingAs($this->admin)->test(KelolaMachinery::class);
    $html = $test->html();
    $bar = substr($html, strpos($html, 'data-testid="view-toolbar"'));
    $bar = substr($bar, 0, strpos($bar, '</section>'));

    expect($bar)->toContain('data-testid="ungrouped-count"')->toContain('1 mesin tanpa grup');

    $test->call('setViewMode', 'rata');
    expect($test->html())->not->toContain('data-testid="ungrouped-count"');
});

it('setiap kelas fb-* yang dipakai markup terdefinisi di aset CSS bersama', function () {
    $css = file_get_contents(resource_path('views/components/filter-assets.blade.php'));

    $sources = array_merge(
        glob(resource_path('views/components/filter/*.blade.php')),
        glob(resource_path('views/livewire/*/*.blade.php')),
    );

    $used = [];
    foreach ($sources as $file) {
        preg_match_all('/(?<![\w.-])fb-[a-z0-9_]+(?:__[a-z0-9-]+)?(?:--[a-z0-9-]+)?(?![\w-])/', file_get_contents($file), $matches);
        foreach ($matches[0] as $class) {
            $used[$class][] = basename($file);
        }
    }

    // Ukuran field dibentuk dinamis ('fb-field--'.$size) — pastikan setiap
    // ukuran yang dipakai layar juga terdefinisi.
    foreach ($sources as $file) {
        preg_match_all('/<x-filter\.field[^>]*\ssize="([a-z]+)"/s', file_get_contents($file), $sizes);
        foreach ($sizes[1] as $size) {
            $used['fb-field--'.$size][] = basename($file);
        }
    }
    $used['fb-field--md'][] = 'field.blade.php (bawaan)';

    expect($used)->not->toBeEmpty();

    $undefined = array_keys(array_filter(
        $used,
        fn ($files, $class) => ! preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $css),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($undefined)->toBe([]);
});

it('shell memuat aset filter sehingga kelas fb-* terdefinisi di halaman yang dirender', function () {
    $this->actingAs($this->admin)
        ->get('/data/boiler-room')
        ->assertOk()
        ->assertSee('.fb-bar {', false)
        ->assertSee('data-testid="filter-bar"', false);
});
