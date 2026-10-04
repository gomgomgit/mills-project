<?php

/**
 * RecordIdShapeGuardTest — audit 2026-10-04: /data/{stasiun}/{id} dan
 * /data/{stasiun}/{id}/edit dengan `{id}` bukan UUID (mis. "abc") berakhir
 * di halaman error Laravel di PostgreSQL (SQLSTATE 22P02 — teks bukan-UUID
 * dibandingkan dengan kolom `uuid` adalah QueryException, bukan "tidak
 * ketemu"), karena mount() hanya menangkap ModelNotFoundException.
 *
 * SQLite tidak punya tipe uuid sehingga query-nya diam-diam mengembalikan
 * 0 baris — itu sebabnya asersi di sini bukan sekadar `notFound`, tetapi
 * juga bahwa id berbentuk salah TIDAK PERNAH sampai ke SQL sebagai binding
 * (kondisi yang di PostgreSQL meledak). Tanpa guard, asersi binding gagal.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

$stations = [
    'BoilerRoom', 'CagesTrack', 'Clarification', 'CpoDispatch', 'Depricarping', 'EffluentPlant',
    'EngineRoom', 'Grading', 'KernelDispatch', 'KernelPlant', 'Pressing', 'ProcessQualityControl',
    'ProcessWater', 'SolidWasteDisposal', 'Sterilizer', 'StorageTank', 'Threshing', 'Weighbridge',
];

$components = [];
foreach ($stations as $station) {
    $components["Detail{$station}"] = ['App\\Livewire\\Data\\Detail'.$station];
    $components["Form{$station} (edit)"] = ['App\\Livewire\\Data\\Form'.$station];
}

beforeEach(function () {
    $businessUnit = BusinessUnit::factory()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($businessUnit)->create();
});

it('id bukan UUID → keadaan "Record tidak ditemukan", tanpa id itu menyentuh SQL', function (string $component, string $badId) {
    $bindings = [];
    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            $bindings[] = $binding;
        }
    });

    Livewire::actingAs($this->user)
        ->test($component, ['id' => $badId])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');

    expect($bindings)->not->toContain($badId);
})->with($components)->with([
    'teks biasa' => 'abc',
    'angka' => '123',
    'uuid terpotong' => '00000000-0000-0000-0000-00000000000',
]);

it('UUID valid yang tidak dikenal tetap jatuh ke keadaan tidak ditemukan yang sama', function (string $component) {
    Livewire::actingAs($this->user)
        ->test($component, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
})->with($components);
