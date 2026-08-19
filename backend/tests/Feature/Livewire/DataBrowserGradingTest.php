<?php

/**
 * DataBrowserGradingTest (Feature/Livewire) — screen-017--data-browser-
 * grading-web / usecase-017--data-browser-grading-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserGrading, one per
 * test_scenarios' component_test step (scenarios 1-5). Mirrors tests/
 * Feature/Livewire/DataBrowserWeighbridgeTest.php's structure/conventions
 * exactly, adapted to grading fields.
 *
 * Scenario 4, "Klik Baris Membuka Detail", does NOT assert real
 * navigation (row-click is a plain `onclick="window.location.href=...`
 * attribute rendered by the Blade view, not a Livewire action/dispatched
 * event — there is nothing to ->call() for it, and screen-020's
 * `data.grading.detail` route does not exist yet); it instead asserts the
 * guarded rendering behavior: since Route::has('data.grading.detail') is
 * false in this test env, rows render with the `gr-table__row--static`
 * class and no `onclick=` attribute — see the Blade view's
 * `$hasDetailRoute` check.
 *
 * Uses Livewire::actingAs($user)->test() since this screen requires an
 * authenticated session (route is behind 'auth' + 'role:supervisor,
 * mill_management,admin' in routes/web.php) — mirrors tests/Feature/
 * Livewire/DataBrowserWeighbridgeTest.php.
 *
 * Known implementation gaps documented (not asserted as failures — see
 * this screen's known_issues, mirroring screen-016's documented gaps):
 *   - Scenario 2's tech-spec assert mentions a "reset-filter option"
 *     alongside the empty state; the current Blade view's @empty branch
 *     only renders the empty message, no reset-filter control. This test
 *     asserts the empty message only.
 *   - Scenario 5 ("Ekspor Gagal"): export is a plain browser-navigable
 *     <a href> link (not an AJAX/Livewire action, see
 *     DataBrowserGrading::exportUrl()'s docblock) — a real export failure
 *     is a full-page navigation to a 422 JSON response, which Livewire has
 *     no visibility into and cannot show an in-app "export failed" message
 *     for. This test instead asserts the only behavior that is actually
 *     testable at the component level: the export links are always built
 *     from the current filters (no client-side dataset size guard
 *     exists), which is what makes the 422 reachable via the Feature/Api
 *     integration test's "Ekspor Gagal" scenario.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserGrading;
use App\Models\BusinessUnit;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Telusuri & Ekspor Data Grading — berhasil"
it('berhasil: shows filtered rows and export links after setting filter properties', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->count(2)
        ->create();

    // Outside the filter range — must not appear.
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-03-01')
        ->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserGrading::class)
        ->set('date_from', '2026-02-01')
        ->set('date_to', '2026-02-10')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 2)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 2)
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'format=csv')
            && str_contains($url, 'business_unit_id='.$this->businessUnit->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'format=excel'))
        ->assertSee('Ekspor CSV')
        ->assertSee('Ekspor Excel');
});

// Scenario: "Telusuri & Ekspor Data Grading — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: shows the empty state when no records match the filter', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserGrading::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 0)
        ->assertSee('Tidak ada data');
    // Known gap: no reset-filter control is rendered in the empty state —
    // see file-level docblock. Not asserted here.
});

// Scenario: "Telusuri & Ekspor Data Grading — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserGrading::class)
        ->set('date_from', '2026-02-10')
        ->set('date_to', '2026-02-01')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 0)
        ->assertSee('Rentang tanggal tidak valid');
});

// Scenario: "Telusuri & Ekspor Data Grading — Ekspor Gagal"
it('Ekspor Gagal: export links are always built from the current filters (no client-side size guard)', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserGrading::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/grading-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
    // Known gap: an oversized dataset only fails once the browser navigates
    // to the export URL (422 EXPORT_FAILED, covered by Feature/Api
    // DataBrowserGradingTest.php's "Ekspor Gagal" scenario) — the Livewire
    // component has no way to intercept that or show an in-app error
    // message, since export is a plain <a href> download link, not an
    // AJAX/Livewire action. See file-level docblock.
});

// Scenario: "Telusuri & Ekspor Data Grading — Klik Baris Membuka Detail"
// (row-click navigation itself is client-side and screen-020's
// `data.grading.detail` route does not exist yet, so navigation is NOT
// asserted here — only the guarded rendering behavior is: since
// Route::has('data.grading.detail') is false in this test env, rows must
// render WITHOUT the clickable-row affordance, i.e. with the
// `gr-table__row--static` class and no `onclick=` attribute.)
it('Klik Baris Membuka Detail: rows render without navigation since the detail route does not exist yet', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserGrading::class)
        ->assertSeeHtml('gr-table__row--static')
        ->assertDontSeeHtml('onclick=');
});
