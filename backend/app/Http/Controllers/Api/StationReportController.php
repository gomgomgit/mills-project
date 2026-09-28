<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * StationReportController — screen-140--laporan-stasiun-web
 * (Pilih Stasiun untuk Laporan). auth_requirement: authenticated,
 * Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. This screen only chooses where to go next; there is
 * no POST/PUT/PATCH/DELETE action here and none on the
 * /api/station-reports prefix.
 *
 * Every rule lives in StationReportService, shared with the Livewire
 * component (App\Livewire\Dashboard\LaporanStasiun) — same split as
 * SterilizerReportController / LaporanSterilizer — so the page and the API
 * can never disagree about which stations exist or which already have a
 * report.
 *
 * Note which answer each situation gets, because three of them are easy to
 * get wrong:
 *   - Supervisor / Mill Management sending ANOTHER mill's business_unit_id
 *     → 200 with their OWN mill. The parameter is discarded, never
 *     validated, so there is nothing to refuse.
 *   - Admin with no business_unit_id → 422 VALIDATION_ERROR, not 403.
 *   - A bound account whose users.business_unit_id is NULL → 422, and the
 *     list of all mills is never read.
 */
class StationReportController extends Controller
{
    public function __construct(protected StationReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/station-reports/business-units/options.
     * Admin-only mill picker; the service answers 403 FORBIDDEN for anyone
     * else, since Supervisor and Mill Management are already bound to one
     * mill and never see a picker.
     *
     * An empty master returns { data: [] } with HTTP 200 — an empty picker
     * plus a UI hint, never a 404.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * stations() — GET /api/station-reports/stations?business_unit_id=...
     *
     * business_unit_id is REQUIRED for Admin (422 when absent) and IGNORED
     * for Supervisor / Mill Management — it is not even read back for
     * those roles, which is why an unknown id from them still returns 200
     * instead of 404.
     *
     * production_line_id — PARAMETER PERMINTAAN BARU (2026-09-28), OPSIONAL
     * DAN ADITIF. Bila dikirim DAN berada di dalam mill yang berlaku, ia
     * ikut terbawa ke setiap `report_path`, sehingga layar laporan tujuan
     * langsung terisi line-nya alih-alih meminta pengguna memilih lagi.
     * Line milik mill lain DIABAIKAN, persis seperti business_unit_id
     * diabaikan untuk peran terikat mill: report_path kembali hanya membawa
     * mill, dan `production_line` pada respons bernilai null. Tanpa
     * parameter ini jawabannya persis seperti sebelum perubahan.
     */
    public function stations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->stations(
                $this->queryString($request, 'business_unit_id'),
                $this->queryString($request, 'production_line_id'),
            ),
        ]);
    }

    /** Trimmed query value, or null when absent/blank. */
    protected function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
