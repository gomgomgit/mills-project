<?php

/**
 * MasterDataDeleteGuardTest — temuan audit 2026-10-04 #5.
 *   - Hapus Business Unit yang masih punya User/Line/Station/Periode
 *     ditolak dengan rincian (dulu user-nya diam-diam kehilangan BU).
 *   - Hapus Production Line beserta station-nya yang masih kosong, dalam
 *     satu transaksi; ditolak bila station-nya sudah punya record/mesin.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Models\BusinessUnit;
use App\Models\MachineryGroup;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->bu = BusinessUnit::factory()->create();
});

it('BU dengan user terikat tidak bisa dihapus dan user-nya tetap punya BU', function () {
    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();

    Livewire::actingAs($this->admin)->test(KelolaBusinessUnit::class)
        ->call('askDelete', $this->bu->id)
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', fn ($m) => str_contains((string) $m, '1 User'))
        ->assertSet('successMessage', null);

    expect(BusinessUnit::find($this->bu->id))->not->toBeNull()
        ->and($operator->fresh()->business_unit_id)->toBe($this->bu->id);
});

it('pesan penolakan BU merinci semua ketergantungan', function () {
    User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();
    $line = ProductionLine::factory()->forBusinessUnit($this->bu)->create();
    Station::factory()->forProductionLine($line)->create();

    $this->actingAs($this->admin)->deleteJson("/api/business-units/{$this->bu->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', fn ($m) => str_contains($m, '1 User') && str_contains($m, '1 Production Line') && str_contains($m, '1 Station'));
});

it('BU tanpa ketergantungan terhapus dengan pesan sukses', function () {
    Livewire::actingAs($this->admin)->test(KelolaBusinessUnit::class)
        ->call('askDelete', $this->bu->id)
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Business Unit berhasil dihapus.');

    expect(BusinessUnit::find($this->bu->id))->toBeNull();
});

it('Production Line beserta station kosongnya terhapus sekaligus', function () {
    $line = ProductionLine::factory()->forBusinessUnit($this->bu)->create();
    Station::factory()->forProductionLine($line)->weighbridge()->create();
    Station::factory()->forProductionLine($line)->grading()->create();

    Livewire::actingAs($this->admin)->test(KelolaProductionLine::class)
        ->call('askDelete', $line->id)
        ->assertSee('Hapus beserta 2 station-nya?')
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Production Line berhasil dihapus.');

    expect(ProductionLine::find($line->id))->toBeNull()
        ->and(Station::where('production_line_id', $line->id)->count())->toBe(0);
});

it('Production Line ditolak bila station-nya punya record, dan tidak ada yang terhapus', function () {
    $line = ProductionLine::factory()->forBusinessUnit($this->bu)->create();
    $wb = Station::factory()->forProductionLine($line)->weighbridge()->create();
    Station::factory()->forProductionLine($line)->grading()->create();
    WeighbridgeRecord::factory()->forStation($wb)->create();

    Livewire::actingAs($this->admin)->test(KelolaProductionLine::class)
        ->call('askDelete', $line->id)
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', fn ($m) => str_contains((string) $m, '1 record stasiun'));

    expect(ProductionLine::find($line->id))->not->toBeNull()
        ->and(Station::where('production_line_id', $line->id)->count())->toBe(2);
});

it('Production Line ditolak bila station-nya punya Machinery Group', function () {
    $line = ProductionLine::factory()->forBusinessUnit($this->bu)->create();
    $station = Station::factory()->forProductionLine($line)->create();
    MachineryGroup::factory()->forStation($station)->create();

    $this->actingAs($this->admin)->deleteJson("/api/production-lines/{$line->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Machinery Group'));
    expect(Station::find($station->id))->not->toBeNull();
});
