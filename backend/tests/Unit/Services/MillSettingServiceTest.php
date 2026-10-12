<?php

/**
 * MillSettingServiceTest — screen-034--mills-setting /
 * usecase-034--mills-setting.
 *
 * Unit tests for App\Services\MillSettingService::getOrCreate() /
 * ::update() / ::listStations() / ::setStationIcon(), covering the
 * unit_test_cases derived from this screen's business_logic. Calls the
 * service directly (no HTTP layer), mirroring tests/Unit/Services/
 * StationServiceTest.php's approach — RefreshDatabase (sqlite in-memory)
 * + model factories, no mocking (this service has no injectable
 * repository abstraction, same as every other service in this codebase).
 *
 * No GD extension installed in this environment, so
 * UploadedFile::fake()->image(...) throws — file-upload tests use
 * UploadedFile::fake()->create($name, $sizeInKb, $mime) instead, mirroring
 * tests/Unit/Services/BusinessUnitServiceTest.php's file-upload tests.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\MillSettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MillSettingService;
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
});

// --- getOrCreate() ----------------------------------------------------------

it('creates a default mill-setting row on first GET when none exists for the business unit', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $result = $this->service->getOrCreate($this->admin, $this->businessUnit->id);

    // Tidak ada nilai yang disemai sejak app_name dicabut (2026-10-12); yang
    // dijanjikan getOrCreate() hanya KEBERADAAN barisnya, dan bentuk barisnya.
    expect($result['business_unit_id'])->toBe($this->businessUnit->id);
    expect($result)->toHaveKeys(['id', 'business_unit_id', 'home_page_image', 'immediate_sync_enabled']);
    expect($result)->not->toHaveKey('app_name');
    expect($result)->not->toHaveKey('logo');
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('returns the existing mill-setting row as-is on GET when one already exists', function () {
    $existing = MillSetting::factory()->forBusinessUnit($this->businessUnit)
        ->create(['immediate_sync_enabled' => true]);

    $result = $this->service->getOrCreate($this->admin, $this->businessUnit->id);

    expect($result['id'])->toBe($existing->id);
    expect($result['immediate_sync_enabled'])->toBeTrue();
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('throws a ModelNotFoundException when business_unit_id does not exist', function () {
    expect(fn () => $this->service->getOrCreate($this->admin, '00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

it('throws an AuthorizationException when a Mill Management user accesses a business_unit_id other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    expect(fn () => $this->service->getOrCreate($this->millManagement, $otherBusinessUnit->id))
        ->toThrow(AuthorizationException::class);
});

it('allows an Admin user to access any business_unit_id', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Lain']);

    $result = $this->service->getOrCreate($this->admin, $otherBusinessUnit->id);

    expect($result['business_unit_id'])->toBe($otherBusinessUnit->id);
});

// --- update() -----------------------------------------------------------

// Uji app_name dan logo DIHAPUS 2026-10-12 bersama kolomnya — keduanya tidak
// dibaca layar mana pun (lihat migrasi
// 2026_10_12_000001_remove_app_name_and_logo_from_mill_settings_table.php).
// Yang DIPERTAHANKAN di bawah adalah apa yang dulu kebetulan diuji lewat
// muatan app_name: auto-create sebelum update, dan otorisasi per mill.
// Keduanya kini memakai immediate_sync_enabled sebagai muatannya.

it('updates immediate_sync_enabled on update()', function () {
    $result = $this->service->update($this->admin, $this->businessUnit->id, ['immediate_sync_enabled' => true]);

    expect($result['immediate_sync_enabled'])->toBeTrue();
});

it('no longer accepts app_name or logo — neither reaches the stored row', function () {
    // Dikirim seperti pemanggil lama; harus diabaikan, bukan menimbulkan galat
    // 500 dan bukan tersimpan diam-diam.
    $result = $this->service->update($this->admin, $this->businessUnit->id, [
        'app_name' => 'Mill Baru',
        'logo' => 'apa pun',
    ]);

    expect($result)->not->toHaveKey('app_name');
    expect($result)->not->toHaveKey('logo');

    $tersimpan = MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail();
    expect($tersimpan->getAttributes())->not->toHaveKey('app_name');
    expect($tersimpan->getAttributes())->not->toHaveKey('logo');
});

// Dua jaminan di bawah DULU hanya diuji lewat `logo`. Kolomnya dicabut, tetapi
// jaminannya tidak: unggahan yang sah tersimpan, dan format yang tidak didukung
// ditolak tanpa membuat baris apa pun. Keduanya kini diuji lewat
// home_page_image — satu-satunya unggahan yang masih ada di layar ini.
it('stores an uploaded home_page_image file and returns a resolved URL', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $gambar = fakeRealImage('home.jpg', 500);

    $result = $this->service->update($this->admin, $this->businessUnit->id, [], $gambar);

    expect($result['home_page_image'])->not->toBeNull();
    $millSetting = MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail();
    Storage::disk(MillSettingService::LOGO_DISK)->assertExists($millSetting->home_page_image);
});

it('throws a ValidationException when the uploaded home_page_image has an unsupported format', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $berkas = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->update($this->admin, $this->businessUnit->id, [], $berkas);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('home_page_image');
    }

    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();
});

it('auto-creates the default row before applying an update when none exists yet', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $result = $this->service->update($this->admin, $this->businessUnit->id, ['immediate_sync_enabled' => true]);

    expect($result['immediate_sync_enabled'])->toBeTrue();
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('throws an AuthorizationException when a Mill Management user updates a business_unit_id other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    expect(fn () => $this->service->update($this->millManagement, $otherBusinessUnit->id, ['immediate_sync_enabled' => true]))
        ->toThrow(AuthorizationException::class);
});

// --- listStations() -------------------------------------------------------

// Sejak audit 2026-10-04 #10 urutannya per Production Line lalu nama (tiap
// line punya set station yang sama) — kedua station di sini satu line.
it('lists stations belonging to the business unit ordered by name, each with its icon', function () {
    $line = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    Station::factory()->forProductionLine($line)->withIcon('truck')->create(['name' => 'Weighbridge Z']);
    Station::factory()->forProductionLine($line)->create(['name' => 'Weighbridge A']);
    Station::factory()->create(['name' => 'Weighbridge Lain']); // different business unit

    $result = $this->service->listStations($this->admin, $this->businessUnit->id);

    expect($result)->toHaveCount(2);
    expect(collect($result)->pluck('name')->all())->toBe(['Weighbridge A', 'Weighbridge Z']);
    expect(collect($result)->firstWhere('name', 'Weighbridge Z')['icon'])->toBe('truck');
    expect(collect($result)->firstWhere('name', 'Weighbridge A')['icon'])->toBeNull();
});

// --- setStationIcon() -------------------------------------------------------

it('sets a station icon override when a supported icon name is sent', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->setStationIcon($this->admin, $this->businessUnit->id, $station->id, 'truck');

    expect($result['icon'])->toBe('truck');
    expect($station->fresh()->icon)->toBe('truck');
});

it('resets a station icon to default (null) when icon is sent as null', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withIcon('truck')->create();

    $result = $this->service->setStationIcon($this->admin, $this->businessUnit->id, $station->id, null);

    expect($result['icon'])->toBeNull();
    expect($station->fresh()->icon)->toBeNull();
});

it('throws a ValidationException when the sent icon is not in the supported list', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    try {
        $this->service->setStationIcon($this->admin, $this->businessUnit->id, $station->id, 'not-a-real-icon');
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('icon');
    }

    expect($station->fresh()->icon)->toBeNull();
});

it('throws a ModelNotFoundException when setting the icon for a station that does not belong to the given business unit', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    expect(fn () => $this->service->setStationIcon($this->admin, $this->businessUnit->id, $station->id, 'truck'))
        ->toThrow(ModelNotFoundException::class);

    expect($station->fresh()->icon)->toBeNull();
});

it('throws an AuthorizationException for a Mill Management user setting a station icon for a business_unit_id other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    expect(fn () => $this->service->setStationIcon($this->millManagement, $otherBusinessUnit->id, $station->id, 'truck'))
        ->toThrow(AuthorizationException::class);
});

it('mengurutkan station per Production Line lalu nama, dengan production_line_name (audit #10)', function () {
    $lineB = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Line B']);
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Line A']);
    Station::factory()->forProductionLine($lineB)->create(['name' => 'Aaa']);
    Station::factory()->forProductionLine($lineA)->create(['name' => 'Zzz']);

    $result = $this->service->listStations($this->admin, $this->businessUnit->id);

    expect(collect($result)->map(fn ($s) => $s['production_line_name'].'/'.$s['name'])->all())
        ->toBe(['Line A/Zzz', 'Line B/Aaa']);
});
