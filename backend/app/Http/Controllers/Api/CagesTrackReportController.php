<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CagesTrackReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CagesTrackReportController — screen-130--laporan-cages-track-web
 * (Laporan Periode Cages & Tracks). auth_requirement: authenticated,
 * Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and
 * none on the /api/cages-track-reports prefix — this screen is a read-only
 * report and must not offer any path that could alter Cages & Tracks data.
 *
 * All resolution/aggregation/export logic lives in
 * CagesTrackReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanCagesTrack), so the web page and the API
 * can never disagree on a figure — the same split as
 * SterilizerReportController / LaporanSterilizer.
 *
 * Note which guard closes which hole: business_unit_id from the client is
 * IGNORED for every mill-bound role — Supervisor / Mill Management /
 * Operator (200 with their own mill's data, deliberately not 403 — a 403
 * would confirm the other mill exists), while a period_id belonging to
 * another mill IS refused with 403 in
 * CagesTrackReportService::authorizePeriod().
 *
 * 2026-09-24 — OPERATOR IS NOW ACCEPTED on these four routes, for
 * screen-136--laporan-cages-track-mobile (usecase-136), which reuses them
 * as-is exactly as screen-135 reused /api/sterilizer-reports/*. There is
 * no mobile-only endpoint and no mobile-only controller: one endpoint must
 * not answer differently depending on who calls it. The widening also
 * added the `sanctum` guard in routes/api.php, since mobile authenticates
 * with a token rather than a session cookie.
 *
 * The widening stops at the API. The WEB route /reports/cages-track and
 * App\Livewire\Dashboard\LaporanCagesTrack stay without Operator, which
 * has no web UI at all. businessUnitOptions() below also stays Admin-only,
 * so Operator still gets 403 there — now from the service rather than from
 * the route middleware, with the same FORBIDDEN code.
 */
class CagesTrackReportController extends Controller
{
    public function __construct(protected CagesTrackReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/cages-track-reports/business-units/options.
     * Admin-only mill picker; the service throws 403 FORBIDDEN for anyone
     * else, since Supervisor and Mill Management are already bound to one
     * mill and have no picker at all.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/cages-track-reports/periods?business_unit_id=...
     * business_logic steps 1-2. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Cages & Tracks yet — an empty picker plus
     * a UI hint, never a 404. An Admin who did not pick a mill gets 422
     * VALIDATION_ERROR from resolveBusinessUnit(); so does a bound account
     * whose users.business_unit_id is NULL, and in that case the all-mills
     * list is never read.
     */
    public function periods(Request $request): JsonResponse
    {
        $businessUnitId = $this->service->resolveBusinessUnit(
            $this->queryString($request, 'business_unit_id'),
        );

        return response()->json([
            'data' => $this->service->listPeriods($businessUnitId),
        ]);
    }

    /**
     * summary() — GET /api/cages-track-reports/summary?period_id=...
     * business_logic steps 3-17: authorise the period (404 / 403), then
     * every figure on the screen for that period.
     */
    public function summary(Request $request): JsonResponse
    {
        $periodId = $this->requirePeriodId($request);

        $period = $this->service->authorizePeriod($periodId);

        return response()->json($this->service->summary($period, $this->productionLineId($request)));
    }

    /**
     * export() — GET /api/cages-track-reports/export?period_id=...&format=csv
     * business_logic step 18: one line per TIPPING HOUR with the record's
     * context columns repeated. NOT a JSON response. 422 EXPORT_FAILED
     * above 50.000 hourly lines, 422 VALIDATION_ERROR for a format outside
     * csv|excel.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        $period = $this->service->authorizePeriod($periodId);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($period, $format, $businessUnitId, $this->productionLineId($request));
    }

    /**
     * `production_line_id` — PARAMETER PERMINTAAN BARU (2026-09-28), OPSIONAL
     * DAN ADITIF.
     *
     * Ia menyaring angka ke satu production line, lewat kolom
     * `production_line_id` di tabel record itu sendiri (kolom nyata sejak
     * commit ccc884d, di-snapshot dari stasiun saat record dibuat), BUKAN
     * lewat join ke `stations` — sehingga record yang stasiunnya kemudian
     * dipindah tetap terhitung di line asalnya.
     *
     * Sengaja TIDAK wajib di lapis API, meski di layar web memilihnya wajib:
     * kelima endpoint ini dibaca sepuluh layar (5 web + 5 mobile), dan
     * mewajibkannya sekarang akan mematahkan kelima layar mobile sebelum
     * mereka sempat menumbuhkan pemilihnya. Tanpa parameter ini, jawabannya
     * persis seperti sebelum perubahan. Bentuk respons pun tidak berubah —
     * hanya bertambah satu kunci `production_line` yang bernilai null ketika
     * parameter ini tidak dikirim.
     *
     * Sebuah line milik mill lain tidak pernah membocorkan apa pun: cakupan
     * mill sudah ditegakkan lebih dulu di lapis service, jadi penyaringan ke
     * line asing menghasilkan laporan kosong, bukan data mill itu.
     */
    protected function productionLineId(Request $request): ?string
    {
        return $this->queryString($request, 'production_line_id');
    }

    /**
     * period_id is required on both summary and export — a missing one is
     * 422 VALIDATION_ERROR with errors.period_id, not a 404 for the empty
     * string and not a silent empty report.
     *
     * @throws ValidationException
     */
    protected function requirePeriodId(Request $request): string
    {
        $periodId = $this->queryString($request, 'period_id');

        if ($periodId === null) {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        return $periodId;
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
