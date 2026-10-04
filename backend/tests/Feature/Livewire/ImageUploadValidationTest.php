<?php

/**
 * ImageUploadValidationTest — temuan audit 2026-10-04 #6: file teks bernama
 * "logo.png" lolos sebagai logo Corporate / Mills Setting. Akar masalah:
 * untuk upload Livewire (TemporaryUploadedFile) aturan 'image'/'mimes'
 * menebak tipe dari EKSTENSI ketika isi file "text/plain". Test di sini
 * memakai upload Livewire sungguhan (->set('logo', UploadedFile)), jalur
 * yang persis dilewati browser, dan memastikan:
 *   - file palsu ditolak SAAT DIPILIH (error + properti dikosongkan),
 *   - tetap ditolak saat Simpan (tidak ada logo tersimpan),
 *   - PNG sungguhan tetap diterima.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Livewire\MasterData\KelolaCompany;
use App\Livewire\MasterData\KelolaCorporate;
use App\Livewire\MasterData\KelolaMachinery;
use App\Livewire\Settings\MillsSetting;
use App\Models\BusinessUnit;
use App\Models\Corporate;
use App\Models\MillSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// PNG 1x1 sungguhan (tanpa ekstensi GD).
const TINY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function fakePngText(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.png', 'ini bukan gambar, cuma teks biasa');
}

function realPng(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.png', base64_decode(TINY_PNG_B64));
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('Corporate: file teks bernama .png ditolak saat dipilih dan tidak pernah tersimpan', function () {
    $component = Livewire::actingAs($this->admin)->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'CX1')
        ->set('form.name', 'Corp X1')
        ->set('logo', fakePngText())
        ->assertHasErrors('logo')
        ->assertSee('Logo harus berupa file gambar JPG atau PNG yang valid.');

    $component->call('save')->assertHasErrors('logo')->assertSet('showForm', true);
    expect(Corporate::where('corporate_code', 'CX1')->exists())->toBeFalse();
});

it('Corporate: PNG sungguhan diterima dan tersimpan', function () {
    Livewire::actingAs($this->admin)->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'CX2')
        ->set('form.name', 'Corp X2')
        ->set('logo', realPng())
        ->assertHasNoErrors('logo')
        ->call('save')
        ->assertHasNoErrors();

    expect(Corporate::where('corporate_code', 'CX2')->value('logo'))->not->toBeNull();
});

it('Company & Business Unit: file teks bernama .png ditolak saat dipilih', function (string $class) {
    Livewire::actingAs($this->admin)->test($class)
        ->call('openCreateForm')
        ->set('logo', fakePngText())
        ->assertHasErrors('logo');
})->with(['Company' => [KelolaCompany::class], 'Business Unit' => [KelolaBusinessUnit::class]]);

it('Kelola Mesin: gambar mesin palsu ditolak saat dipilih', function () {
    Livewire::actingAs($this->admin)->test(KelolaMachinery::class)
        ->set('picture', fakePngText())
        ->assertHasErrors('picture');
});

it('Mills Setting: logo & gambar halaman utama palsu ditolak, tidak tersimpan', function () {
    $bu = BusinessUnit::factory()->create();

    $component = Livewire::actingAs($this->admin)->test(MillsSetting::class)
        ->set('selectedBusinessUnitId', $bu->id)
        ->set('logo', fakePngText())
        ->assertHasErrors('logo')
        ->set('home_page_image', fakePngText())
        ->assertHasErrors('home_page_image');

    $component->call('save')->assertHasErrors(['logo', 'home_page_image'])->assertSet('successMessage', null);
    $setting = MillSetting::where('business_unit_id', $bu->id)->first();
    expect($setting?->logo)->toBeNull()->and($setting?->home_page_image)->toBeNull();
});

// Catatan: jalur API memakai UploadedFile PHP biasa (tanpa ekstensi di path
// sementara) sehingga sudah ditolak sebelum perbaikan; test ini menjaga agar
// service tetap menolak setelah aturan RealImage ditambahkan.
it('Service (jalur API) juga menolak file teks bernama .png', function () {
    $corporate = Corporate::factory()->create();

    $this->actingAs($this->admin)
        ->post('/api/corporates/'.$corporate->id, [
            '_method' => 'PATCH',
            'corporate_code' => $corporate->corporate_code,
            'name' => $corporate->name,
            'logo' => fakePngText(),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('logo');
});
