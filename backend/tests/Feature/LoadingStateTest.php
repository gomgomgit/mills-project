<?php

/**
 * LoadingStateTest — "loading state ketika melakukan action yang butuh waktu
 * untuk read/write data di semua tempat" (web, 2026-10-05).
 *
 * Tiga lapis:
 *   1. Pindai STATIS seluruh view Livewire (+ komponen yang dipakainya):
 *      tiap tombol yang membaca/menulis data (submit, Simpan, Ya Hapus,
 *      verifikasi, buka/tutup periode, ekspor, tambah/hapus baris, paging)
 *      wajib punya wire:loading.attr="disabled" + wire:target pada TAG
 *      TOMBOLNYA (cegah klik ganda), dan tombol aksi tulis wajib punya teks
 *      sibuk (x-busy-label / wire:loading). Tautan ekspor wajib
 *      data-export-link. Area hasil Data Browser wajib .ld-region dengan
 *      wire:target ke properti filternya. Tombol/view baru yang lupa
 *      loading state akan menggagalkan test ini.
 *   2. Render Livewire/Blade nyata: markup loading ada di HTML hasil render
 *      (bukan hanya di sumber) — login, Kelola Corporate, Data Browser,
 *      aksi verifikasi Detail, bar filter laporan, Laporan Manajemen.
 *   3. Middleware SignalDownloadReady: ?_dl=<token> → cookie ms_download
 *      polos (tidak terenkripsi) pada respons ekspor, termasuk respons
 *      error; token tidak valid diabaikan.
 *
 * Bukti perilaku di browser nyata (bar tampil, tombol tidak bisa diklik
 * ganda = tepat satu request/record, area meredup lalu pulih, unduhan
 * mengakhiri status loading) dijalankan dengan Playwright — lihat laporan
 * perubahan; test PHP di sini menjaga markup-nya tidak hilang diam-diam.
 */

use App\Enums\UserRole;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Data\DataBrowserKernelPlant;
use App\Livewire\MasterData\KelolaCorporate;
use App\Models\BusinessUnit;
use App\Models\Corporate;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * Kembalikan pelanggaran loading state di satu sumber Blade.
 *
 * @return list<string>
 */
function loadingStateViolations(string $source, string $label): array
{
    $violations = [];

    // Aksi yang membaca/menulis data lewat tombol. Paging cukup dicegah
    // klik ganda (area datanya sudah meredup); aksi tulis juga wajib teks
    // sibuk.
    $writeAction = '/^(save\w*|confirm\w*|toggleChecked|toggleAcknowledged|toggleStatus|removeDetailRow|addDetailRow|export\w*|\{\{ \$exportAction \}\})/';
    $pagingAction = '/^(previousPage|nextPage|goToPage)\b/';

    preg_match_all('/<button\b[^>]*>(.*?)<\/button>/s', $source, $buttons, PREG_SET_ORDER);
    foreach ($buttons as [$whole, $content]) {
        preg_match('/<button\b[^>]*>/s', $whole, $openTag);
        $tag = $openTag[0];
        $short = preg_replace('/\s+/', ' ', mb_substr($tag, 0, 160));

        $isSubmit = str_contains($tag, 'type="submit"');
        $click = preg_match('/wire:click="([^"]+)"/', $tag, $m) === 1 ? $m[1] : null;
        $isWrite = $isSubmit || ($click !== null && preg_match($writeAction, $click) === 1);
        $isPaging = $click !== null && preg_match($pagingAction, $click) === 1;

        if (! $isWrite && ! $isPaging) {
            continue;
        }

        if (! str_contains($tag, 'wire:loading.attr="disabled"') || ! str_contains($tag, 'wire:target=')) {
            $violations[] = "{$label}: tombol tanpa wire:loading.attr=\"disabled\" + wire:target → {$short}";
        }

        if ($isWrite && ! str_contains($content, '<x-busy-label') && ! str_contains($content, 'wire:loading')) {
            $violations[] = "{$label}: tombol aksi tanpa teks sibuk (x-busy-label) → {$short}";
        }
    }

    // Tautan ekspor (unduhan file lewat <a href>) wajib data-export-link.
    preg_match_all('/<a\b[^>]*href="\{\{ \$(?:exportCsvUrl|exportExcelUrl|this->exportUrl\([^)]*\)) \}\}"[^>]*>(.*?)<\/a>/s', $source, $links, PREG_SET_ORDER);
    foreach ($links as [$whole, $content]) {
        if (! str_contains($whole, 'data-export-link') || ! str_contains($content, '<x-busy-label')) {
            $violations[] = "{$label}: tautan ekspor tanpa data-export-link + x-busy-label → ".preg_replace('/\s+/', ' ', mb_substr($whole, 0, 160));
        }
    }

    return $violations;
}

/** @return array<string, string> label => isi berkas */
function loadingStateSources(): array
{
    $files = collect(File::allFiles(resource_path('views/livewire')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(fn ($file) => [str_replace(resource_path('views').'/', '', $file->getPathname()) => $file->getContents()])
        ->all();

    foreach (['record-verification-actions', 'report-filter-bar', 'modal'] as $component) {
        $files["components/{$component}.blade.php"] = File::get(resource_path("views/components/{$component}.blade.php"));
    }

    ksort($files);

    return $files;
}

// ---------------------------------------------------------------------
// 1. Pindai statis
// ---------------------------------------------------------------------

it('setiap tombol baca/tulis & tautan ekspor di view Livewire punya loading state', function () {
    $sources = loadingStateSources();
    expect(count($sources))->toBeGreaterThan(75);

    $violations = [];
    foreach ($sources as $label => $source) {
        array_push($violations, ...loadingStateViolations($source, $label));
    }

    expect($violations)->toBe([]);
});

it('pemindai benar-benar menangkap tombol tanpa loading state (kontrol negatif)', function () {
    $bad = <<<'BLADE'
        <button type="submit" class="x">Simpan</button>
        <button type="button" wire:click="confirmDelete" class="x">Ya, Hapus</button>
        <button type="button" wire:click="nextPage">Berikutnya</button>
        <button type="button" wire:click="openEditForm('1')">Edit</button>
        <a href="{{ $exportCsvUrl }}" class="x" target="_blank">Ekspor CSV</a>
        BLADE;

    $violations = loadingStateViolations($bad, 'contoh');

    // submit (2: tanpa disabled + tanpa teks sibuk), confirmDelete (2),
    // nextPage (1), tautan ekspor (1). openEditForm sengaja tidak dihitung.
    expect($violations)->toHaveCount(6);
});

it('ke-18 Data Browser: area hasil .ld-region diredupkan pada perubahan filter & halaman', function () {
    $files = File::glob(resource_path('views/livewire/data/data-browser-*.blade.php'));
    expect($files)->toHaveCount(18);

    foreach ($files as $file) {
        $source = File::get($file);
        $ok = preg_match('/<div class="[a-z]+-table-wrap ld-region" wire:loading\.delay\.short\.class="ld-region--busy" wire:loading\.delay\.short\.attr="aria-busy" wire:target="([^"]+)">/', $source, $m) === 1;
        expect($ok)->toBeTrue(basename($file).': area tabel tanpa .ld-region');

        $targets = explode(',', $m[1]);
        foreach (['date_from', 'date_to', 'business_unit_id', 'production_line_id', 'resetFilters', 'previousPage', 'nextPage'] as $target) {
            expect($targets)->toContain($target);
        }
    }
});

it('laporan stasiun, laporan manajemen, Mills Setting & daftar master data punya area hasil .ld-region', function () {
    $expect = [
        'livewire/dashboard/laporan-boiler-room.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-cages-track.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-clarification.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-sterilizer.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-storage-tank.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-weighbridge.blade.php' => 'businessUnitId,productionLineId,periodId',
        'livewire/dashboard/laporan-stasiun.blade.php' => 'businessUnitId,productionLineId',
        'livewire/dashboard/management-report.blade.php' => 'productionLineId,date_from,date_to',
        'livewire/settings/mills-setting.blade.php' => 'selectedBusinessUnitId',
        'livewire/master-data/kelola-station.blade.php' => 'filterBusinessUnitId,filterProductionLineId,resetFilters',
        'livewire/master-data/kelola-periode-pelaporan.blade.php' => 'filterBusinessUnitId,filterStatus,resetFilters',
        'livewire/user-management/kelola-user-role.blade.php' => 'filterRole,filterBusinessUnitId,resetFilters',
    ];

    foreach ($expect as $view => $targetPrefix) {
        $source = File::get(resource_path("views/{$view}"));
        expect($source)->toContain('ld-region')
            ->and($source)->toContain('wire:loading.delay.short.class="ld-region--busy"')
            ->and($source)->toContain('wire:target="'.$targetPrefix);
    }
});

it('bar progres global dipasang di app shell dan layout login', function () {
    expect(File::get(resource_path('views/components/layouts/app.blade.php')))->toContain('<x-loading-assets />')
        ->and(File::get(resource_path('views/auth/login.blade.php')))->toContain('<x-loading-assets />');
});

it('setiap kelas .ld-* yang dipakai markup didefinisikan di loading-assets', function () {
    $css = File::get(resource_path('views/components/loading-assets.blade.php'));

    $used = [];
    foreach (File::allFiles(resource_path('views')) as $file) {
        preg_match_all('/\bld-[a-z][a-z-]*(?:--[a-z-]+)?\b/', $file->getContents(), $m);
        array_push($used, ...$m[0]);
    }
    $used = array_values(array_unique(array_filter($used, fn ($c) => ! in_array($c, ['ld-progress-slide', 'ld-spin', 'ld-live'], true))));

    foreach ($used as $class) {
        expect(preg_match('/\.'.preg_quote($class, '/').'(?![a-z-])/', $css))->toBe(1, "kelas .{$class} dipakai tapi tidak didefinisikan");
    }
});

// ---------------------------------------------------------------------
// 2. HTML hasil render
// ---------------------------------------------------------------------

it('halaman login merender bar progres, live region, dan tombol Masuk dengan teks sibuk', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain('id="ld-progress"')
        ->and($html)->toContain('id="ld-live" role="status" aria-live="polite"')
        ->and($html)->toContain('prefers-reduced-motion');

    Livewire::test(LoginForm::class)
        ->assertSeeHtml('wire:loading.attr="disabled" wire:target="login"')
        ->assertSeeHtml('<span class="ld-label ld-label--busy" wire:loading.inline-flex wire:target="login"><span class="ld-spinner" aria-hidden="true"></span>Memproses…</span>');
});

it('Kelola Corporate: Simpan dan Ya, Hapus dirender dengan disabled-saat-memuat + teks sibuk', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $component = Livewire::actingAs($admin)->test(KelolaCorporate::class)->call('openCreateForm');
    $html = $component->html();

    expect($html)->toContain('wire:loading.attr="disabled" wire:target="save,logo"')
        ->and($html)->toContain('wire:target="save"><span class="ld-spinner" aria-hidden="true"></span>Menyimpan…')
        ->and($html)->toMatch('/class="kc-table-wrap ld-region"[^>]*wire:target="previousPage,nextPage,goToPage"/');

    $corporate = Corporate::factory()->create();
    $html = Livewire::actingAs($admin)->test(KelolaCorporate::class)->call('askDelete', $corporate->id)->html();

    expect($html)->toContain('wire:click="confirmDelete" wire:loading.attr="disabled" wire:target="confirmDelete"')
        ->and($html)->toContain('Menghapus…');
});

it('Data Browser: tautan ekspor bertanda data-export-link dan area tabel .ld-region', function () {
    $businessUnit = BusinessUnit::factory()->create();
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($businessUnit)->create();

    $html = Livewire::actingAs($supervisor)->test(DataBrowserKernelPlant::class)->html();

    expect(substr_count($html, 'data-export-link'))->toBe(2)
        ->and(substr_count($html, '<span class="ld-label ld-label--busy ld-label--js"><span class="ld-spinner" aria-hidden="true"></span>Mengekspor…</span>'))->toBe(2)
        ->and($html)->toContain('class="kp-table-wrap ld-region"')
        ->and($html)->toContain('wire:target="date_from,date_to,business_unit_id,production_line_id,resetFilters,previousPage,nextPage,goToPage"');
});

it('aksi verifikasi Detail: kedua tombol nonaktif selama toggle, tombol yang diklik menampilkan Memverifikasi…', function () {
    $html = Blade::render('<x-record-verification-actions :can-check="true" :can-acknowledge="true" :is-checked="false" :is-acknowledged="true" />');

    expect(substr_count($html, 'wire:loading.attr="disabled"'))->toBe(2)
        ->and(substr_count($html, 'wire:target="toggleChecked,toggleAcknowledged"'))->toBe(2)
        ->and($html)->toContain('wire:target="toggleChecked"><span class="ld-spinner" aria-hidden="true"></span>Memverifikasi…')
        ->and($html)->toContain('wire:target="toggleAcknowledged"><span class="ld-spinner" aria-hidden="true"></span>Membatalkan…')
        // id/data-testid lama tetap.
        ->and($html)->toContain('data-testid="toggle-checked-button"')
        ->and($html)->toContain('data-testid="toggle-acknowledged-button"');
});

it('bar filter laporan: tombol ekspor Livewire nonaktif selama ekspor & menampilkan Mengekspor…', function () {
    $html = Blade::render('<x-report-filter-bar export-action="exportCsv" export-csv-testid="export-csv-button" export-excel-testid="export-excel-button" />');

    expect(substr_count($html, 'wire:loading.attr="disabled" wire:target="exportCsv"'))->toBe(2)
        ->and($html)->toContain('wire:target="exportCsv(&#039;csv&#039;)"><span class="ld-spinner" aria-hidden="true"></span>Mengekspor…')
        ->and($html)->toContain('wire:target="exportCsv(&#039;excel&#039;)"><span class="ld-spinner" aria-hidden="true"></span>Mengekspor…')
        ->and($html)->toContain('data-testid="export-csv-button"');
});

// ---------------------------------------------------------------------
// 3. Sinyal selesai-unduh (SignalDownloadReady)
// ---------------------------------------------------------------------

it('ekspor dengan ?_dl=<token> memasang cookie ms_download polos bernilai token', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $response = $this->actingAs($admin, 'web')
        ->get('/api/kernel-plant-records/export?format=csv&_dl=tok3n123abc');

    $response->assertOk();
    $response->assertPlainCookie('ms_download', 'tok3n123abc');
    expect($response->headers->getCookies()[0]->isHttpOnly())->toBeFalse();
});

it('respons ekspor yang GAGAL tetap memasang cookie (status loading tidak menggantung)', function () {
    $operator = User::factory()->role(UserRole::Operator)->create();

    $response = $this->actingAs($operator, 'web')
        ->get('/api/kernel-plant-records/export?format=csv&_dl=gagal12345');

    expect($response->status())->toBeGreaterThanOrEqual(400);
    $response->assertPlainCookie('ms_download', 'gagal12345');
});

it('token tidak valid atau tanpa _dl: tidak ada cookie ms_download', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin, 'web')->get('/api/kernel-plant-records/export?format=csv')
        ->assertOk()->assertCookieMissing('ms_download');

    $this->actingAs($admin, 'web')->get('/api/kernel-plant-records/export?format=csv&_dl='.urlencode('<script>'))
        ->assertOk()->assertCookieMissing('ms_download');
});
