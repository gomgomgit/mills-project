<?php

namespace App\Livewire\Dashboard;

use App\Exceptions\InvalidDateRangeException;
use App\Services\ManagementReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * ManagementReport — screen-026--laporan-manajemen ("Laporan Manajemen"),
 * route name `reports.management`, /reports/management.
 *
 * Reuses ManagementReportService — the exact same service the API
 * controller (App\Http\Controllers\Api\ManagementReportController) uses —
 * so filtering/aggregation rules stay identical between the web and API
 * entry points (mirrors DashboardHome/DashboardService).
 *
 * Filter state defaults to start-of-month..today on first mount (business
 * spec rule: "Filter rentang tanggal default: awal bulan berjalan sampai
 * hari ini"). Business unit is always the logged-in user's own — no
 * business-unit picker, per screen_tech_spec (Mill Management scoped to
 * their own mill only).
 *
 * PRODUCTION LINE WAJIB DIPILIH (temuan audit 2026-10-04 #2b) — sama dengan
 * keenam laporan stasiun: line adalah konteks yang DIPILIH, bukan ikatan
 * akun, dan selama belum dipilih layar ini tidak menampilkan satu angka
 * pun (total yang mencampur semua line bukan angka yang bisa
 * ditindaklanjuti). Opsi selalu dibatasi mill pengguna; line mill lain
 * lewat query string dibuang kembali ke "belum memilih".
 */
#[Layout('dashboard.management-report')]
class ManagementReport extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    public ?string $errorMessage = null;

    #[Url(as: 'production_line_id')]
    public string $productionLineId = '';

    public function mount(): void
    {
        $this->date_from = Carbon::today()->startOfMonth()->toDateString();
        $this->date_to = Carbon::today()->toDateString();
    }

    public function exportUrl(string $format): string
    {
        $query = array_filter([
            'production_line_id' => $this->productionLineId !== '' ? $this->productionLineId : null,
            'date_from' => $this->date_from !== '' ? $this->date_from : null,
            'date_to' => $this->date_to !== '' ? $this->date_to : null,
            'format' => $format,
        ], fn ($value) => $value !== null);

        return url('/api/reports/management-summary/export').'?'.http_build_query($query);
    }

    public function render()
    {
        $service = app(ManagementReportService::class);
        $businessUnitId = (string) auth()->user()->business_unit_id;

        $productionLineOptions = $businessUnitId !== '' ? $service->productionLineOptions($businessUnitId) : [];
        if ($this->productionLineId !== '' && ! in_array($this->productionLineId, array_column($productionLineOptions, 'id'), true)) {
            $this->productionLineId = '';
        }

        $breakdown = null;
        $this->errorMessage = null;

        if ($this->productionLineId !== '') {
            try {
                $breakdown = $service->getBreakdown(
                    $businessUnitId,
                    $this->date_from !== '' ? $this->date_from : null,
                    $this->date_to !== '' ? $this->date_to : null,
                    $this->productionLineId,
                );
            } catch (InvalidDateRangeException $e) {
                // date_from > date_to → filter not applied; ONLY the error
                // is shown — no "Belum ada data" and no empty Total row
                // (temuan audit 2026-10-04 #2d). Mirrors the API's 422
                // INVALID_DATE_RANGE.
                $this->errorMessage = $e->getMessage();
            }
        }

        return view('livewire.dashboard.management-report', [
            'breakdown' => $breakdown,
            'productionLineOptions' => $productionLineOptions,
            'selectedProductionLine' => collect($productionLineOptions)->firstWhere('id', $this->productionLineId),
        ]);
    }
}
