<?php

/**
 * MillsSettingTest (Feature/Livewire) — screen-034--mills-setting /
 * usecase-034--mills-setting.
 *
 * Component tests for App\Livewire\Settings\MillsSetting, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->test()
 * mirrors tests/Feature/Livewire/KelolaStationTest.php's pattern.
 *
 * CRITICAL divergence from every master-data Livewire suite: this
 * component behaves differently per actor at mount() time — Mill
 * Management is auto-scoped to their own business unit (no picker, data
 * loaded immediately on mount), Admin starts with nothing selected until
 * they set `selectedBusinessUnitId` (mirrors KelolaStation's
 * `filterBusinessUnitId` picker pattern, but here it also drives which
 * record is being edited, not just a list filter).
 *
 * "akses ditolak" for the route itself (role:admin,mill_management gate)
 * is out of scope here — Livewire::test() does not run route middleware,
 * mirrors KelolaStationTest.php's documented deviation for that case; not
 * duplicated in this suite since MillSettingTest.php (Feature/Api) already
 * covers 403 for Supervisor/Operator at the HTTP layer, and this
 * component's OWN ownership scoping (Mill Management to another
 * business_unit_id) is exercised directly below instead, since that is
 * this screen's actual novel access-control surface.
 */

use App\Enums\UserRole;
use App\Livewire\Settings\MillsSetting;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Mills Setting — Admin berhasil mengatur mill terpilih"
it('berhasil: Admin selects a mill, edits the form, and saves successfully', function () {
    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->assertSet('isAdmin', true)
        ->assertSet('selectedBusinessUnitId', '')
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertSet('app_name', 'Mill Unit Alpha')
        ->set('app_name', 'Mill Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Mills Setting berhasil disimpan.');

    $millSetting = MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail();
    expect($millSetting->app_name)->toBe('Mill Baru');
});

// Scenario: "Mills Setting — Mill Management langsung terarah ke mill sendiri"
it('Mill Management: is auto-scoped to their own business unit on mount, no picker shown', function () {
    Livewire::actingAs($this->millManagement)
        ->test(MillsSetting::class)
        ->assertSet('isAdmin', false)
        ->assertSet('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertSet('app_name', 'Mill Unit Alpha')
        ->assertViewHas('businessUnitOptions', []);
});

// Scenario: "Mills Setting — Mill belum punya setting, dibuat default"
it('Mill belum punya setting: selecting a mill with no existing row shows default values', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertSet('app_name', 'Mill Unit Alpha');
});

// Scenario: "Mills Setting — Mill Management akses mill lain ditolak"
it('Mill Management akses mill lain: forcing a different business_unit_id surfaces an access-denied message, not a crash', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    Livewire::actingAs($this->millManagement)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $otherBusinessUnit->id)
        ->assertSet('formErrorMessage', fn ($message) => $message !== null)
        ->assertSet('app_name', '');
});

// Scenario: "Mills Setting — Pilih icon station"
it('Pilih icon station: sets a station icon override and reflects it in the stations list', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge 1']);

    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertViewHas('iconOptions', fn ($options) => collect($options)->pluck('value')->contains('truck'))
        ->call('setStationIcon', $station->id, 'truck')
        ->assertSet('successMessage', 'Icon station berhasil disimpan.')
        ->assertSet('stations', fn ($stations) => collect($stations)->firstWhere('id', $station->id)['icon'] === 'truck');

    expect($station->fresh()->icon)->toBe('truck');
});

it('resets a station icon to default when the empty option is selected', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withIcon('truck')->create();

    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->call('setStationIcon', $station->id, '')
        ->assertSet('stations', fn ($stations) => collect($stations)->firstWhere('id', $station->id)['icon'] === null);

    expect($station->fresh()->icon)->toBeNull();
});

// Scenario: "Mills Setting — Belum ada station terdaftar"
it('Belum ada station: shows an empty stations list without error', function () {
    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertSet('stations', []);
});

// Write-through saving toggle (product decision 2026-09-14) — per-mill,
// server-side, so an Admin can switch a mill's mobile devices between
// "push on every save" and "wait for the Sinkronisasi button" without a
// mobile rebuild. Defaults off so no existing mill changes behaviour.
it('defaults the immediate-sync toggle to off for a mill that never set it', function () {
    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->assertSet('immediate_sync_enabled', false);
});

it('persists the immediate-sync toggle and reloads it', function () {
    // An Admin picks the mill first (selecting one reloads the form), so the
    // toggle is set AFTER the selection, not before.
    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->set('immediate_sync_enabled', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail()->immediate_sync_enabled)->toBeTrue();

    // Reload in a fresh component instance — the saved value must come back.
    Livewire::actingAs($this->admin)
        ->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $this->businessUnit->id)
        ->assertSet('immediate_sync_enabled', true);
});

it('leaves the toggle untouched when another field is saved without it', function () {
    $setting = MillSetting::factory()->create([
        'business_unit_id' => $this->businessUnit->id,
        'immediate_sync_enabled' => true,
    ]);

    app(\App\Services\MillSettingService::class)->update(
        $this->admin,
        $this->businessUnit->id,
        ['app_name' => 'Nama Baru'],
    );

    expect($setting->fresh()->immediate_sync_enabled)->toBeTrue();
});
