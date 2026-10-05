<?php

/**
 * NotFoundPageTest — halaman 404 ramah pengguna (resources/views/errors/404.blade.php)
 * menggantikan halaman bawaan Laravel "404 NOT FOUND".
 *
 *   - URL yang tidak cocok dengan rute mana pun → kartu mandiri berbahasa
 *     Indonesia dengan tautan ke '/' (yang mengalihkan sesuai status login).
 *   - abort(404) di rute web saat user sudah login → di dalam shell aplikasi.
 *   - Permintaan JSON / api/* tetap mendapat envelope JSON, bukan HTML.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Support\Facades\Route;

it('URL tak dikenal untuk tamu menampilkan halaman 404 berbahasa Indonesia dengan tautan ke beranda', function () {
    $html = $this->get('/halaman-yang-tidak-ada')->assertNotFound()->getContent();

    expect($html)->toContain('Halaman Tidak Ditemukan')
        ->toContain('data-testid="not-found-page"')
        ->toContain('/halaman-yang-tidak-ada')
        ->toContain('href="'.route('home').'"')
        ->toContain('Halaman Sebelumnya')
        ->not->toContain('class="shell-sidebar"');
});

it('abort(404) di rute web saat sudah login dirender di dalam shell dengan tautan ke beranda peran', function () {
    Route::middleware('web')->get('/__uji-404', fn () => abort(404));
    $supervisor = User::factory()->role(UserRole::Supervisor)
        ->forBusinessUnit(BusinessUnit::factory()->create())->create();

    $html = $this->actingAs($supervisor)->get('/__uji-404')->assertNotFound()->getContent();

    expect($html)->toContain('Halaman Tidak Ditemukan')
        ->toContain('class="shell-sidebar"')
        ->toContain('href="'.url('/dashboard').'" class="notfound-card__primary"')
        ->toContain('data-testid="not-found-back"');
});

it('path yang diminta di-escape, bukan disisipkan mentah ke HTML', function () {
    $html = $this->get('/x%3Cscript%3Ealert(1)%3C%2Fscript%3E')->assertNotFound()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('permintaan api/* yang tak dikenal tetap mendapat 404 JSON, bukan halaman HTML', function () {
    $this->getJson('/api/tidak-ada')
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    $this->get('/api/tidak-ada')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});
