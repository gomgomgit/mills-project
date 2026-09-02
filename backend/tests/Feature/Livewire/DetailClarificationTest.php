<?php

/**
 * DetailClarificationTest (Feature/Livewire) —
 * screen-109--detail-clarification-web / usecase-113--detail-clarification-web.
 *
 * Component tests for App\Livewire\Data\DetailClarification, mirroring
 * tests/Feature/Livewire/DetailBoilerRoomTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailClarification;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('berhasil: renders all header fields and the Clarification Detail grid, read-only', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create();
    ClarificationDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['clarification_tank_temp_c' => 65.5]);

    Livewire::actingAs($this->user)
        ->test(DetailClarification::class, ['id' => $record->id])
        ->assertSee($record->clarification_id)
        ->assertSee('65.5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = ClarificationRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailClarification::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailClarification::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Clarification route', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailClarification::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.clarification'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailClarification::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="clarification-detail-rows-empty"');
});
