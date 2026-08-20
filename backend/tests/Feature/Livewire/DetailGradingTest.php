<?php

/**
 * DetailGradingTest (Feature/Livewire) — screen-020--detail-grading-web /
 * usecase-020--detail-grading-web.
 *
 * Component tests for App\Livewire\Data\DetailGrading, one per
 * test_scenarios' component_test step. Mirrors
 * tests/Feature/Livewire/DetailWeighbridgeTest.php's setup/conventions
 * (Livewire::actingAs, RefreshDatabase via tests/Pest.php).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailGrading;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail Grading — berhasil"
it('berhasil: renders all header fields grouped and the grading detail grid, read-only', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();
    $parameter = GradingParameter::factory()->create(['name' => 'Masak']);
    GradingDetail::factory()->forGradingRecord($record)->forGradingParameter($parameter)->create();

    Livewire::actingAs($this->user)
        ->test(DetailGrading::class, ['id' => $record->id])
        ->assertSee($record->grading_number)
        ->assertSee('Masak')
        ->assertDontSee('Record tidak ditemukan');
});

it('Nama Quality Parameter Ter-resolve: shows the parameter name, not the raw id', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();
    $parameter = GradingParameter::factory()->create(['name' => 'Brondolan Segar']);
    GradingDetail::factory()->forGradingRecord($record)->forGradingParameter($parameter)->create();

    Livewire::actingAs($this->user)
        ->test(DetailGrading::class, ['id' => $record->id])
        ->assertSee('Brondolan Segar')
        ->assertDontSee($parameter->id);
});

// Scenario: "Lihat Detail Grading — Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailGrading::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Grading route', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailGrading::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.grading'));
});
