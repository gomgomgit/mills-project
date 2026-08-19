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
    $this->service = new MillSettingService();
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
});

// --- getOrCreate() ----------------------------------------------------------

it('creates a default mill-setting row on first GET when none exists for the business unit', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $result = $this->service->getOrCreate($this->admin, $this->businessUnit->id);

    expect($result['app_name'])->toBe('Mill Unit Alpha');
    expect($result['jumlah_cages'])->toBe(1);
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('returns the existing mill-setting row as-is on GET when one already exists', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(7)->create(['app_name' => 'Custom Name']);

    $result = $this->service->getOrCreate($this->admin, $this->businessUnit->id);

    expect($result['app_name'])->toBe('Custom Name');
    expect($result['jumlah_cages'])->toBe(7);
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

    expect($result['app_name'])->toBe('Mill Unit Lain');
});

// --- update() -----------------------------------------------------------

it('updates app_name on update()', function () {
    $result = $this->service->update($this->admin, $this->businessUnit->id, ['app_name' => 'Mill Baru']);

    expect($result['app_name'])->toBe('Mill Baru');
});

it('throws a ValidationException when jumlah_cages is 0 or negative', function () {
    try {
        $this->service->update($this->admin, $this->businessUnit->id, ['jumlah_cages' => 0]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('jumlah_cages');
    }

    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();
});

it('updates jumlah_cages when a positive integer is sent', function () {
    $result = $this->service->update($this->admin, $this->businessUnit->id, ['jumlah_cages' => 8]);

    expect($result['jumlah_cages'])->toBe(8);
});

it('stores an uploaded logo file and returns a resolved logo URL', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg');

    $result = $this->service->update($this->admin, $this->businessUnit->id, [], $logo);

    expect($result['logo'])->not->toBeNull();
    $millSetting = MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail();
    Storage::disk(MillSettingService::LOGO_DISK)->assertExists($millSetting->logo);
});

it('throws a ValidationException when the uploaded logo has an unsupported format', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->update($this->admin, $this->businessUnit->id, [], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();
});

it('auto-creates the default row before applying an update when none exists yet', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $result = $this->service->update($this->admin, $this->businessUnit->id, ['jumlah_cages' => 3]);

    expect($result['app_name'])->toBe('Mill Unit Alpha');
    expect($result['jumlah_cages'])->toBe(3);
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('throws an AuthorizationException when a Mill Management user updates a business_unit_id other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    expect(fn () => $this->service->update($this->millManagement, $otherBusinessUnit->id, ['app_name' => 'Hack']))
        ->toThrow(AuthorizationException::class);
});

// --- listStations() -------------------------------------------------------

it('lists stations belonging to the business unit ordered by name, each with its icon', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->withIcon('truck')->create(['name' => 'Weighbridge Z']);
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge A']);
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
