<?php

/**
 * WebAccessTest — temuan audit 2026-10-04 di area auth & shell web:
 *   #1 sidebar difilter per peran + halaman 403 ramah (Indonesia, di dalam shell)
 *   #2 login web Operator → /beranda (akses terbatas), bukan 403 tanpa jalan keluar
 *   #4 akun yang dinonaktifkan di tengah sesi dikeluarkan / token-nya ditolak
 *   #14 '/' dan '/login' saat sudah login dialihkan
 *
 * Sidebar diperiksa lewat href menu di HTML shell (bukan teks label), karena
 * yang harus dibuktikan adalah TAUTAN ke rute terlarang tidak dirender.
 */

use App\Enums\UserRole;
use App\Livewire\Auth\LoginForm;
use App\Livewire\UserManagement\KelolaUserRole;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function sidebarHtml(string $html): string
{
    preg_match('#<aside class="shell-sidebar".*?</aside>#s', $html, $m);

    return $m[0] ?? '';
}

beforeEach(function () {
    $this->bu = BusinessUnit::factory()->create();
});

it('#1 sidebar Supervisor tidak memuat menu admin maupun Laporan Manajemen', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();

    $sidebar = sidebarHtml($this->actingAs($supervisor)->get('/dashboard')->assertOk()->getContent());

    expect($sidebar)->toContain('href="'.route('dashboard').'"')
        ->toContain('href="'.route('reports.stations').'"')
        ->toContain('href="'.route('settings.password').'"')
        ->not->toContain('href="'.route('master-data.corporates').'"')
        ->not->toContain('href="'.route('master-data.stations').'"')
        ->not->toContain('href="'.route('users.index').'"')
        ->not->toContain('href="'.route('mill-settings').'"')
        ->not->toContain('href="'.route('reports.management').'"')
        ->not->toContain('Struktur Organisasi');
});

it('#1 sidebar Admin tidak memuat Laporan Manajemen (rute khusus mill_management)', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $sidebar = sidebarHtml($this->actingAs($admin)->get('/dashboard')->assertOk()->getContent());

    expect($sidebar)->not->toContain('href="'.route('reports.management').'"')
        ->toContain('href="'.route('master-data.corporates').'"')
        ->toContain('href="'.route('users.index').'"')
        ->toContain('href="'.route('mill-settings').'"');
});

it('#1 sidebar Mill Management memuat Laporan Manajemen + Mills Setting, tanpa menu admin', function () {
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->bu)->create();

    $sidebar = sidebarHtml($this->actingAs($mm)->get('/dashboard')->assertOk()->getContent());

    expect($sidebar)->toContain('href="'.route('reports.management').'"')
        ->toContain('href="'.route('mill-settings').'"')
        ->not->toContain('href="'.route('users.index').'"')
        ->not->toContain('href="'.route('master-data.machinery').'"');
});

it('#1 halaman 403 berbahasa Indonesia di dalam shell, dengan Logout dan tautan beranda', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();

    $html = $this->actingAs($supervisor)->get('/master-data/corporates')->assertForbidden()->getContent();

    expect($html)->toContain('Akses Ditolak')
        ->toContain('data-testid="forbidden-logout"')
        ->toContain('action="'.route('logout').'"')
        ->toContain('class="shell-sidebar"')
        ->toContain('href="'.url('/dashboard').'" class="forbidden-card__primary"')
        ->toContain('Peran Anda tidak memiliki akses');
});

it('#2 login web Operator diarahkan ke /beranda, bukan /dashboard', function () {
    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create([
        'password_hash' => Hash::make('Passw0rd!'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('username', $operator->username)
        ->set('password', 'Passw0rd!')
        ->call('login')
        ->assertRedirect('/beranda');

    $this->assertAuthenticatedAs($operator, 'web');
});

it('#2 beranda Operator: pesan akses terbatas + sidebar hanya Beranda & Ganti Password', function () {
    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();

    $html = $this->actingAs($operator)->get('/beranda')->assertOk()->getContent();
    $sidebar = sidebarHtml($html);

    expect($html)->toContain('aplikasi mobile')->toContain('data-testid="logout-button"');
    preg_match_all('#<a href="([^"]+)"#', $sidebar, $links);
    expect($links[1])->toEqualCanonicalizing([route('operator.home'), route('settings.password')]);

    $this->actingAs($operator)->get('/settings/password')->assertOk();
    $this->actingAs($operator)->get('/dashboard')->assertForbidden();
});

it('#2 login mobile Operator tetap mendapat token', function () {
    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create([
        'password_hash' => Hash::make('Passw0rd!'),
    ]);

    $this->postJson('/api/login', [
        'username' => $operator->username,
        'password' => 'Passw0rd!',
        'device_name' => 'hp-1',
    ])->assertOk()->assertJsonStructure(['token']);
});

it('#14 "/" mengalihkan ke Login untuk tamu dan ke beranda peran untuk user login', function () {
    $this->get('/')->assertRedirect(route('login'));

    $admin = User::factory()->role(UserRole::Admin)->create();
    $this->actingAs($admin)->get('/')->assertRedirect('/dashboard');

    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();
    $this->actingAs($operator)->get('/')->assertRedirect('/beranda');
});

it('#14 /login saat sudah login dialihkan ke beranda peran', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/login')->assertRedirect('/dashboard');
});

it('#4 sesi web akun yang dinonaktifkan dikeluarkan pada request berikutnya', function () {
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->bu)->create();

    $this->actingAs($mm)->get('/mill-settings')->assertOk();

    $mm->update(['is_active' => false]);

    $this->get('/mill-settings')
        ->assertRedirect(route('login'))
        ->assertSessionHas('auth_error');
    $this->assertGuest('web');
});

it('#4 request JSON dari sesi akun nonaktif dijawab 401', function () {
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => false]);

    $this->actingAs($admin)->getJson('/dashboard')->assertStatus(401);
});

it('#4 menonaktifkan user mencabut token Sanctum-nya, dan token akun nonaktif ditolak', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();
    $operator->createToken('hp-1');
    $plain = $operator->createToken('hp-2')->plainTextToken;

    Livewire::actingAs($admin)
        ->test(KelolaUserRole::class)
        ->call('toggleStatus', $operator->id, false)
        ->assertSet('successMessage', 'User berhasil dinonaktifkan.');

    expect($operator->tokens()->count())->toBe(0);

    // Token yang (karena alasan apa pun) masih ada tetap ditolak.
    $other = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->bu)->create();
    $token = $other->createToken('hp-3')->plainTextToken;
    $other->update(['is_active' => false]);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/stations')->assertStatus(401);
    expect($plain)->toBeString();
});

it('#1 permintaan JSON tetap mendapat 403 JSON berbahasa Indonesia, bukan halaman HTML', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->bu)->create();

    $this->actingAs($supervisor)->getJson('/master-data/corporates')
        ->assertForbidden()
        ->assertJsonPath('message', 'Anda tidak memiliki akses untuk aksi ini.');
});

it('#1 halaman 403 untuk tamu dirender tanpa error', function () {
    $html = view('errors.403')->render();

    expect($html)->toContain('Akses Ditolak')->toContain(route('login'));
});
