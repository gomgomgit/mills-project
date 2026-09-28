<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClarificationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ClarificationReportController — screen-132--laporan-clarification-web
 * (Laporan Periode Clarification). auth_requirement: authenticated,
 * Supervisor / Mill Management / Admin (see routes/api.php's
 * 'role:supervisor,mill_management,admin' middleware).
 *
 * GET-ONLY BY DESIGN. There is no POST/PUT/PATCH/DELETE action here and
 * none on the /api/clarification-reports prefix — this screen is a
 * read-only report and must not offer any path that could alter
 * Clarification data.
 *
 * All resolution/aggregation/export logic lives in
 * ClarificationReportService, shared with the Livewire component
 * (App\Livewire\Dashboard\LaporanClarification), so the web page and the
 * API can never disagree on a figure — the same split as
 * BoilerRoomReportController / LaporanBoilerRoom.
 *
 * PRODUCTION IS DERIVED, NOT RECORDED: `summary` returns
 * production.total_ton as the SUM of pure_oil_production_rate_ton_hour over
 * the rows that carry one, ALWAYS beside production.reading_count. There is
 * no production column in the schema, and an hour with no rate reading is
 * not an hour that produced zero. The full reasoning is in the
 * ClarificationReportService class docblock.
 *
 * Note which guard closes which hole: business_unit_id from the client is
 * IGNORED for the mill-bound roles — Supervisor / Mill Management (200 with
 * their own mill's data, deliberately NOT 403 — a 403 would confirm the
 * other mill exists), while a period_id belonging to another mill IS
 * refused with 403 in ClarificationReportService::authorizePeriod().
 *
 * OPERATOR IS REFUSED ON ALL FOUR ROUTES. This is the WEB report and there
 * is NO Operator widening: the mobile Clarification report is screen-138
 * and has not been built, so there is no caller to widen for. The role list
 * here carries no `operator`, and ClarificationReportService::guardAccess()
 * refuses it two layers deeper as well. Widening must always be both, and
 * must always also add Operator to the MILL-BOUND branch of
 * resolveBusinessUnit() — otherwise it falls into the unbound Admin branch
 * where the client's business_unit_id IS honoured.
 */
class ClarificationReportController extends Controller
{
    public function __construct(protected ClarificationReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/clarification-reports/business-units/options.
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
     * periods() — GET /api/clarification-reports/periods?business_unit_id=...
     * business_logic steps 1-2. Returns { data: [] } with HTTP 200 when the
     * mill has no period covering Clarification yet — an empty picker plus a
     * UI hint, never a 404. An Admin who did not pick a mill gets 422
     * VALIDATION_ERROR; so does a bound account whose users.business_unit_id
     * is NULL, and in that case the all-mills list is never read at all.
     */
    public function periods(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listPeriods(
                $this->queryString($request, 'business_unit_id'),
            ),
        ]);
    }

    /**
     * summary() — GET /api/clarification-reports/summary?period_id=...
     * business_logic steps 3-16: resolve the mill (422), authorise the
     * period (404 / 403), then every figure on the screen for that period —
     * recording coverage, DERIVED production with its reading count,
     * downtime with its own reading count (null when never recorded, 0 when
     * recorded and genuinely zero), the six metrics each with its own
     * denominator, the daily recap carrying all three tank temperatures on
     * one row, and the per-unit recap.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Ordered on purpose: an Admin with no mill is 422 BEFORE the
        // period is looked up, so an incomplete request never turns into a
        // 404/403 about a period it was never entitled to ask about.
        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $this->productionLineId($request)),
        );
    }

    /**
     * export() — GET /api/clarification-reports/export?period_id=...&format=csv
     * business_logic step 17: one line per TIME SLOT with the record's
     * context columns repeated, followed by all seven non-time_slot columns
     * including `findings` verbatim. NOT a JSON response. 422 EXPORT_FAILED
     * above 50.000 detail lines, 422 VALIDATION_ERROR for a format outside
     * csv|excel.
     *
     * The permission guard and the row-limit check run EAGERLY inside the
     * service, before the response starts streaming — otherwise a 403/422
     * would reach the client as a successful but empty download.
     *
     * A CLOSED period exports exactly like an open one: the period lock
     * governs writing data, not reading a report.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $this->service->resolveBusinessUnit($businessUnitId);

        $periodId = $this->requirePeriodId($request);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($periodId, $format, $businessUnitId, $this->productionLineId($request));
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
