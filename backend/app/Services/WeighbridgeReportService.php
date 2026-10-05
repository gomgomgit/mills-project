<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Models\WeighbridgeRecord;
use App\Support\ExportValue;
use App\Support\ReportPeriodDays;
use App\Support\SheetWriter;
use DateTimeInterface;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * WeighbridgeReportService — screen-143--laporan-weighbridge-web /
 * usecase-146--laporan-weighbridge-web (Laporan Periode Weighbridge).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * WeighbridgeReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanWeighbridge), the same split used by
 * StorageTankReportService / CagesTrackReportService — so the web page and
 * the API can never disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/weighbridge-reports prefix carries no POST/PUT/PATCH/DELETE. A report
 * must never be able to mutate the data it reports on.
 *
 * ==================================================================
 * WHAT MAKES THIS REPORT DIFFERENT FROM THE OTHER FIVE STATION REPORTS
 * ==================================================================
 * The other five summarise READINGS taken at fixed slots of a shift. This
 * one summarises TRANSACTIONS: one row = one vehicle weighed once. And every
 * transaction belongs to exactly ONE OF TWO FLOWS that answer DIFFERENT
 * QUESTIONS:
 *
 *   - weighbridge_type = 'receive'  — FFB arriving from an estate/supplier;
 *     record_datetime is the ARRIVAL time, and the interesting breakdown is
 *     BY ORIGIN (estate_supplier).
 *   - weighbridge_type = 'dispatch' — a shipment leaving the mill;
 *     record_datetime is the DEPARTURE time, and the interesting breakdown
 *     is BY DESTINATION.
 *
 * TEN RULES FOLLOW FROM THAT, every one of which fails SILENTLY (a plausible
 * number, never an error) if it is got wrong:
 *
 *  1. THE TWO FLOWS ARE NEVER SUMMED. Not one key in the payload totals the
 *     two, and there is no combined trip count, combined weight, combined
 *     average, combined daily column or grand total anywhere. "How many tons
 *     were weighed today" is not a question anybody can act on — it is
 *     neither how much FFB came in nor how much product went out, and the
 *     two load units are not even comparable (a full tanker vs. one FFB
 *     cage). The two flows are therefore published as two independent metric
 *     sets: summaryFlow() is called twice and the results are never folded.
 *
 *  2. THERE IS NO VEHICLE-IN-PLANT DURATION ANYWHERE, AND THE ABSENCE IS
 *     DELIBERATE — not an oversight to be "completed" later. Migration
 *     2026_08_19_000010 MERGED arrival_datetime and dispatch_datetime into a
 *     single `record_datetime` and DROPPED both in up(); they survive only in
 *     down(), where they are RESTORED on rollback. Each trip therefore
 *     carries EXACTLY ONE timestamp. A duration needs TWO timestamps on the
 *     SAME trip; that data does not exist, so the metric is impossible — and
 *     a duration derived from one timestamp would read as valid while
 *     resting on nothing. No duration key, no duration column in the CSV, no
 *     duration card on the screen, not even an empty placeholder.
 *
 *  3. THE PER-HOUR DISTRIBUTION IS THE REPLACEMENT for that metric, approved
 *     by the user, and it follows the 24-bucket pattern Laporan Cages &
 *     Tracks already established. It IS computable from a single timestamp
 *     and answers the neighbouring operational question: WHEN DOES THE
 *     WEIGHBRIDGE QUEUE UP. 24 buckets per flow, every hour present even at
 *     zero, plus busiest_hour / busiest_hour_trip_count / empty_hour_count.
 *     One chart PER FLOW, never one stacked chart — stacking would put a
 *     combined bar height on screen, which is precisely the number rule 1
 *     forbids.
 *
 *  4. period_id AND production_line_id ARE BOTH REQUIRED. A missing one is
 *     422 VALIDATION_ERROR and ZERO weighbridge_records queries run. There is
 *     deliberately NO all-lines fallback: a total mixing a dozen production
 *     lines is not a number anyone can act on, so "no line chosen" means
 *     "show no figure", never "show the whole mill".
 *
 *  5. LINE FILTERING READS weighbridge_records.production_line_id — THE
 *     RECORD'S OWN COLUMN — and never joins to `stations`. The column is a
 *     point-in-time SNAPSHOT (2026_09_28_000041, NOT NULL since
 *     ...000043) taken when the record was created. Filtering through
 *     `stations` would REWRITE HISTORY every time a station is moved to
 *     another line: last month's figures would silently change. See the
 *     docblock of WeighbridgeRecord::productionLine().
 *
 *  6. PERIOD MEMBERSHIP IS DECIDED BY record_datetime — when the weighing
 *     actually happened — INCLUSIVE AT BOTH ENDS, and never by created_at or
 *     the mobile sync time. A row synced late still belongs to the period it
 *     happened in. Same rule, same wording, as
 *     CagesTrackReportService::recordQueryFor().
 *
 *  7. ROWS WITH record_datetime NULL CANNOT BE PLACED IN ANY PERIOD, so they
 *     are excluded from EVERY figure — and counted in undated_trip_count so
 *     the reader can explain the difference between this screen and the Data
 *     Browser. The column is nullable on purpose: the merge migration could
 *     not use ->change() (doctrine/dbal is not installed), so required-ness
 *     is enforced in WeighbridgeRecordService. Null rows can therefore exist.
 *
 *  8. EVERY METRIC HAS ITS OWN DENOMINATOR, AND NULL IS NEVER ZERO.
 *     net_weight is nullable (an unfinished weighing), so
 *     net_weight_total / net_weight_avg are computed over ONLY the rows where
 *     net_weight IS NOT NULL, each carrying its own net_weight_trip_count,
 *     while trip_count counts ALL rows of that flow.
 *     missing_net_weight_trip_count is reported PER FLOW and never merged
 *     into one number. An average over all trips would be dragged down by
 *     trips that were never weighed to the end.
 *
 *  9. A NULL destination FORMS ONE GROUP OF ITS OWN in by_destination and is
 *     NEVER dropped. Dropping it would make the per-destination trip counts
 *     stop summing to the dispatch trip_count, and the reader would be left
 *     with a difference they cannot explain.
 *
 * 10. DRAFT ROWS COUNT IN EVERY FIGURE — zero status filter is applied to any
 *     metric query — and draft_trip_count is published alongside so the
 *     reader knows how much of the report rests on unfinished data. This
 *     matches all five existing station reports, none of which filters record
 *     status; a report that counts differently per station is more misleading
 *     than one that openly includes unfinished data.
 *
 * ------------------------------------------------------------------
 * THERE IS DELIBERATELY NO THRESHOLD FLAGGING ANYWHERE IN THIS REPORT
 * ------------------------------------------------------------------
 * No out-of-range marking, no threshold card, no safe/danger colouring, no
 * severity, no outlier detection, no IQR. Weighbridge has no
 * operational-target master table, and a threshold derived from the period's
 * own data would be READ as an official limit while being nothing but a
 * statistic about the very data it judges. Extreme values still contribute
 * to totals and averages like any other trip.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT THREE DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management — not validated, not compared,
 *      discarded. Probing another mill's id returns 200 with the CALLER'S OWN
 *      data, deliberately not a 403: a 403 would confirm the other mill
 *      exists, and the parameter is never used for those roles.
 *   2. resolveProductionLine() REFUSES a line belonging to another mill with
 *      403 and zero summary queries. Here there IS a concrete handle on
 *      another mill's data. (This is where this service DIVERGES from the
 *      five earlier report services, which return null for a foreign line
 *      because choosing a line was optional at the API layer there. Here the
 *      line is REQUIRED, so "null" would be indistinguishable from "not
 *      sent" and a probe would get the 422 "please choose a line" instead of
 *      a refusal.)
 *   3. authorizePeriod() REFUSES a period belonging to another mill with 403.
 *
 * A Supervisor / Mill Management account whose users.business_unit_id is NULL
 * FAILS CLOSED and the whole-mill list is never even read — see
 * allBusinessUnits(), which is public and deliberately trivial so a spy can
 * prove it was never called.
 *
 * OPERATOR IS ADMITTED SINCE 2026-10-05, when screen-144 (the mobile
 * Weighbridge report) was built on these very endpoints — the same reason the
 * five sibling report services already admitted it. The widening was the
 * THREE changes this docblock had warned about, and they landed together:
 * routes/api.php, guardAccess() below, and — the one that is easy to miss —
 * the MILL-BOUND branch of resolveBusinessUnit(). THAT THIRD ONE IS WHAT
 * MAKES THE OTHER TWO SAFE: resolveBusinessUnit() branches on
 * `Supervisor || MillManagement` and treats everything else as Admin, so
 * widening only the guard would have dropped Operator into the Admin branch,
 * where the client's business_unit_id IS honoured — and an Operator could
 * then read any mill's report by naming it. That is a cross-mill leak, not a
 * display defect, which is why the unit test asserts the OUTCOME ('Operator
 * sending another mill's id still gets its own data') rather than merely that
 * the role is accepted.
 *
 * WHAT WAS NOT WIDENED, on purpose: businessUnitOptions() still refuses
 * Operator with 403 — a role bound to one mill has no picker, and handing it
 * the list of every mill is precisely the leak this widening avoids. The WEB
 * route /reports/weighbridge (screen-143) was not widened either; Operator has
 * no web report UI. Only the four /api/weighbridge-reports/* routes changed.
 *
 * ------------------------------------------------------------------
 * ALL AGGREGATION HAPPENS IN PHP, NONE OF IT IN SQL
 * ------------------------------------------------------------------
 * Same decision as StorageTankReportService, and for the same two reasons
 * plus one specific to this screen. First, SQL aggregate behaviour over
 * NULLABLE columns is NOT identical in SQLite (the test suite) and PostgreSQL
 * (production), and the separate-denominator and null-is-not-zero rules are
 * exactly what gets lost in that difference. Second, hour-of-day and
 * date-part extraction have NO portable SQL spelling: PostgreSQL wants
 * EXTRACT(HOUR FROM ...) / date_trunc, SQLite wants strftime('%H', ...), and
 * a raw expression written for one is either a syntax error or silently wrong
 * on the other. Third, `ILIKE` — the obvious reach for case-insensitive
 * grouping — is a SYNTAX ERROR on SQLite while bare `LIKE` is
 * case-SENSITIVE on PostgreSQL, so a grouping expression could pass the whole
 * suite and still be wrong in production. NOT ONE raw SQL expression is
 * issued anywhere below. The queries only ever select raw rows and filter
 * them with Laravel's own portable helpers (whereDate / whereNull /
 * whereNotNull); the hour and the date come from Carbon in PHP. The row set
 * is bounded by one reporting period and one production line; the export
 * path streams in chunks.
 */
class WeighbridgeReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES. One weighbridge_records
     * row IS one exported line (there is no detail table), so header count
     * and line count coincide here. Same value as the sibling reports.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang statusnya dipakai di seluruh payload layar ini.
     *
     * Sebuah periode mencakup Weighbridge bila ia PUNYA baris
     * `period_stations` untuk jenis ini. Cabang lama "station_type NULL =
     * berlaku untuk semua jenis stasiun" HILANG bersama kolomnya sendiri
     * (2026-09-25): cakupan semua-stasiun kini dinyatakan lewat ADANYA satu
     * baris per jenis stasiun. Spec layar ini masih menyebut cabang kedua itu
     * (business_logic 4) karena ditulis dari kosakata lama; yang berlaku
     * adalah skema, dan kelima laporan stasiun sebelumnya menyelesaikannya
     * dengan cara yang sama persis.
     */
    protected const STATION_TYPE = StationTypeEnum::Weighbridge->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /** The two flows, in payload order. NEVER summed — see rule 1. */
    public const FLOW_RECEIVE = 'receive';

    public const FLOW_DISPATCH = 'dispatch';

    /** @var array<int, string> */
    public const FLOWS = [self::FLOW_RECEIVE, self::FLOW_DISPATCH];

    /** Indonesian labels for the two flows, used by the export and the screen. */
    public const FLOW_LABELS = [
        self::FLOW_RECEIVE => 'Arus Masuk',
        self::FLOW_DISPATCH => 'Arus Keluar',
    ];

    /** The statuses that count as an unfinished (draft) log sheet. */
    public const DRAFT_STATUSES = [
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ];

    /**
     * How many leading EXPORT_HEADER columns are CONTEXT, repeated verbatim
     * on every row — period, mill, production line, flow type. Published as
     * a constant so the export shape is derived, never counted by hand.
     */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 4;

    /**
     * Export column headers — 4 context columns first (repeated on every
     * row), then EXACTLY ONE timestamp column, then the trip's own fields.
     *
     * THERE IS NO DURATION COLUMN, AND THERE NEVER CAN BE: a trip carries one
     * timestamp (rule 2). There is also no created_at / sync-time column — a
     * second time column would invite exactly the subtraction this report
     * must not offer, and period membership is not decided by it either
     * (rule 6).
     *
     * Weights are exported in KILOGRAMS, raw and unrounded, the same unit the
     * input form (form-weighbridge) and the Data Browser
     * (data-browser-weighbridge) label them with. No unit conversion happens
     * anywhere in this service.
     *
     * 4 context + 1 timestamp + 12 trip columns = 17 columns.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Jenis Arus',
        'Waktu Penimbangan',
        'No. Kartu WB',
        'No. Kendaraan',
        'Nama Pengemudi',
        'Asal (Estate/Supplier)',
        'Tujuan',
        'Divisi',
        'Blok',
        'Berat Bruto (kg)',
        'Berat Tara (kg)',
        'Berat Bersih (kg)',
        'Kuantitas (tandan)',
        'Status',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 1 — which mill the caller is allowed to look at.
     *
     * Operator / Supervisor / Mill Management: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored outright, so
     * probing another mill's id is a no-op that still returns the caller's own
     * data with HTTP 200. Operator joined this branch on 2026-10-05 together
     * with the guardAccess() widening for screen-144 — and it had to, because
     * the `else` below is the ADMIN branch, where the argument IS honoured.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null and
     * never an empty result set, which would read as "this mill has no data".
     *
     * A bound account with no mill FAILS CLOSED, and fails EARLY: the return
     * happens before any repository call, so allBusinessUnits() is provably
     * never reached from this path. Falling back to "every mill" would turn
     * one broken master-data row into a cross-mill leak.
     *
     * NOTE ON THE SPEC'S HTTP CODE: business_logic 1 calls the no-mill case
     * "403 FORBIDDEN", while the endpoint table lists 422 VALIDATION_ERROR
     * for /summary and /export and the matching unit test only demands "code
     * FORBIDDEN and a message instructing the user to contact Admin". The
     * five sibling services all answer 422 here, and 422 is the right
     * reading: nothing is being refused, the account's master data is
     * incomplete. The MESSAGE carries the contact-Admin instruction, and the
     * all-mills list is never offered as a way out.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Supervisor->value
            || $role === UserRole::MillManagement->value
            // screen-144 (mobile Weighbridge report). Operator belongs HERE
            // and nowhere else: the branch below treats whatever reaches it
            // as Admin and honours the client's business_unit_id.
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED —
            // not validated, not compared, discarded.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — the only role not bound to one mill, and the only role that
        // can reach this point: guardAccess() admits exactly four roles and
        // the other three are handled above.
        if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
            // Incomplete input, not refused access — 422, never 403.
            throw ValidationException::withMessages([
                'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
            ]);
        }

        return $requestedBusinessUnitId;
    }

    /**
     * business_logic step 2 — mill picker options, ADMIN ONLY.
     *
     * Supervisor and Mill Management are bound to a single mill and have no
     * picker at all, so asking for this list is a 403 rather than a filtered
     * list of one. The refusal is raised HERE rather than by the route
     * middleware — the middleware already admitted the request — so it
     * carries code = 'FORBIDDEN' through ApiExceptionHandler, and so that
     * clearing the middleware is never enough by itself.
     *
     * An empty master is a valid answer: [] with HTTP 200, never a 404.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $this->allBusinessUnits()
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * THE ONLY PLACE THIS SERVICE READS THE WHOLE-MILL LIST.
     *
     * Public and deliberately trivial so it can be spied on: the "fail
     * closed" rule for a bound account with no business_unit_id is only
     * meaningful if it can be PROVEN that the all-mills list was never built,
     * and a spy that records zero calls to this method is that proof.
     *
     * @return Collection<int, BusinessUnit>
     */
    public function allBusinessUnits(): Collection
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Pemilih Production Line — opsi line DI DALAM mill yang berlaku.
     *
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions() yang
     * khusus Admin: production line BUKAN ikatan akun (tidak ada
     * `users.production_line_id`, dan tidak boleh ada) melainkan KONTEKS YANG
     * DIPILIH. Supervisor pun memilih line, karena satu mill di lapangan
     * punya belasan production line dengan jenis stasiun yang sama berulang
     * di tiap line.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi — itu separuh pertama dari jaminan "line mill
     * lain ditolak"; separuhnya lagi ada di resolveProductionLine(), yang
     * menutup jalur properti/query string.
     *
     * @return list<array{id: string, name: string}>
     */
    public function productionLineOptions(string $businessUnitId): array
    {
        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => [
                'id' => (string) $line->id,
                'name' => (string) $line->name,
            ])
            ->all();
    }

    /**
     * business_logic step 3 — the production line the figures belong to.
     *
     * MEMILIH LINE WAJIB DI LAYAR INI, dan wajib pula di lapis API — berbeda
     * dari kelima laporan stasiun sebelumnya, tempat parameter ini opsional
     * supaya kelima layar mobile yang sudah terbit tidak patah. Weighbridge
     * tidak punya layar mobile terbit (screen-144 belum ada), jadi tidak ada
     * pembaca lama yang harus dijaga, dan spec layar ini memang menuntutnya
     * wajib.
     *
     * Tiga jawaban yang SENGAJA berbeda:
     *   - tidak dikirim  -> 422 VALIDATION_ERROR pada field
     *     production_line_id. NOL kueri weighbridge_records dijalankan. TIDAK
     *     ADA fallback seluruh line: angka gabungan lintas line bukan angka
     *     yang bisa ditindaklanjuti siapa pun.
     *   - milik mill lain -> 403 FORBIDDEN. NOL kueri summary dijalankan. Di
     *     sini ada pegangan nyata ke data mill lain, jadi ditolak terang-
     *     terangan; mengembalikan null akan tidak terbedakan dari "tidak
     *     dikirim" dan probe-nya justru mendapat ajakan memilih line.
     *   - milik mill yang berlaku -> id-nya dipulangkan.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function resolveProductionLine(string $businessUnitId, ?string $requestedProductionLineId): string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        if (! $belongsToMill) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $requestedProductionLineId;
    }

    /**
     * Varian resolveProductionLine() yang MEMULANGKAN null alih-alih
     * melempar, untuk jalur RENDER layar web.
     *
     * Alasannya: di layar, "belum memilih line" adalah keadaan AWAL yang
     * normal dan harus merender ajakan memilih, bukan halaman galat; dan line
     * mill lain yang menyelip lewat query string harus jatuh ke keadaan yang
     * sama, bukan memamerkan bahwa line itu ada. Jalur API dan jalur EKSPOR
     * tetap memakai resolveProductionLine() yang melempar, sehingga satu
     * permintaan yang menyebut line mill lain tetap 403 dan satu berkas yang
     * diunduh tetap tidak pernah memuat line asing.
     */
    public function resolveProductionLineOrNull(string $businessUnitId, ?string $requestedProductionLineId): ?string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            return null;
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        return $belongsToMill ? $requestedProductionLineId : null;
    }

    /**
     * business_logic step 5 — load a period and prove the caller may read it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill and
     * the caller is mill-bound. Admin passes for any mill.
     *
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose and proven by the
     * exception TYPE: a caller who is refused outright must not be able to
     * learn whether a period id exists by comparing a 403 against a 404. The
     * CROSS-MILL oracle is closed the same way — another mill's period
     * answers 403, never 404.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function authorizePeriod(string $periodId): Period
    {
        $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        return $this->authorizePeriodModel($period);
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * business_logic step 4 — the periods selectable for this mill.
     *
     * A period covers Weighbridge when it HAS a `period_stations` row for
     * station_type 'weighbridge'. Newest first. An empty array is a valid
     * answer — a mill with no period yet gets [] with HTTP 200 and a UI hint
     * pointing at Kelola Periode Pelaporan, never a 404.
     *
     * STATUS NEVER FILTERS THIS LIST. A CLOSED period is listed exactly like
     * an open one: the period lock governs WRITING data, not READING a
     * report.
     *
     * The mill is resolved here rather than by the caller so that calling
     * listPeriods() as an Admin without a mill is the documented 422, and
     * calling it as a bound role with someone else's id still reads the
     * caller's own mill.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', self::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue()
            // tidak menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', self::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 6-18 — every figure on the screen for one period
     * on one production line, SPLIT PER FLOW.
     *
     * Order of the guards is deliberate: role first (403), then mill (422),
     * then production line (422 / 403), then the period id (422), then the
     * period itself (404 / 403). A caller who may not be here at all never
     * learns which period ids exist, and an incomplete request never turns
     * into a 404 about a period it was never entitled to ask about.
     *
     * NOT ONE KEY IN THE RETURNED ARRAY TOTALS THE TWO FLOWS. draft_trip_count
     * and undated_trip_count are the only two counters that span both, and
     * they are COMPLETENESS counters rather than metrics — they say how much
     * of the report rests on unfinished data and how many rows could not be
     * placed in the period at all. They are named in the spec's response
     * schema as single top-level integers, and they are deliberately NOT a
     * sum of two metric figures.
     *
     * @param  Period|string|null  $period  model or id (both accepted so callers
     *                                      that already authorised the period do
     *                                      not have to re-read it)
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        // 422 when absent, 403 when it belongs to another mill — and in BOTH
        // cases zero weighbridge_records queries have run by this point.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // ONE query for every dated row of this period+line. Everything below
        // is PHP: no SQL aggregate, no raw expression, no driver-specific
        // date or hour function.
        $trips = $this->tripsFor($period, $productionLineId);

        $byFlow = $trips->groupBy('flow');

        $daily = $this->dailyOf($trips);

        return [
            'business_unit' => $this->businessUnitInfo($businessUnitId),
            'production_line' => $this->productionLineInfo($productionLineId),
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
            ],
            // TWO INDEPENDENT METRIC SETS. summaryFlow() is called twice and
            // the two results are NEVER folded into a third.
            self::FLOW_RECEIVE => $this->summaryFlow(
                $byFlow->get(self::FLOW_RECEIVE, collect()),
                self::FLOW_RECEIVE,
            ),
            self::FLOW_DISPATCH => $this->summaryFlow(
                $byFlow->get(self::FLOW_DISPATCH, collect()),
                self::FLOW_DISPATCH,
            ),
            // Draft rows are INCLUDED in every figure above; this is the
            // disclosure, not a filter.
            'draft_trip_count' => $trips
                ->filter(fn ($trip) => in_array($trip->status, self::DRAFT_STATUSES, true))
                ->count(),
            // Rows that could not be placed in ANY period — counted here and
            // nowhere else, so the reader can reconcile this screen against
            // the Data Browser.
            'undated_trip_count' => $this->undatedTripCount($productionLineId),
            'daily' => $daily,
            'daily_total' => $this->dailyTotalOf($daily),
            'completeness' => [
                'days_in_period' => $this->daysInPeriod($period),
                // COUNT DISTINCT dates having at least one trip. `daily` has
                // exactly one row per such date, so counting it IS the
                // distinct count — one definition, one answer.
                'days_with_trip' => count($daily),
                // Penyebut persentase hari bertrip: berhenti di HARI INI untuk
                // periode yang masih berjalan (temuan audit 2026-10-04 #3).
                'days_counted' => ReportPeriodDays::counted($period),
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the same
     * way as StorageTankReportService::summary() and
     * CagesTrackReportService::summary() at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic step 19 — ONE EXPORTED ROW PER TRIP, with the period /
     * mill / production line / flow context repeated verbatim on every row so
     * the file can be pivoted directly in a spreadsheet. Same convention as
     * the station exports, scoped to a period and a production line.
     *
     * The query is THE SAME as summary()'s: same period range on
     * record_datetime, same line column, same absence of any status filter,
     * and the same exclusion of record_datetime NULL rows (a row that belongs
     * to no period cannot appear in a period's export either).
     *
     * A trip whose net_weight is NULL STAYS A ROW, with an EMPTY cell — never
     * dropped, and never written as 0. Dropping it would make the file
     * disagree with the trip_count on screen; writing 0 would claim a
     * measurement nobody took.
     *
     * THE GUARD AND THE ROW-LIMIT CHECK RUN EAGERLY, at call time, while the
     * rows themselves are yielded lazily from a chunked query. Making this
     * method itself a generator would defer the 403/422 until the first
     * iteration, so a refused export would look like a successful call that
     * produced nothing.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function buildExportRows(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): Generator {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        $rowCount = (clone $recordQuery)->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        if ($rowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        $context = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $context);
    }

    /**
     * business_logic step 19 — the streamed file around buildExportRows().
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(
        Period|string|null $period = null,
        string $format = 'csv',
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): StreamedResponse {
        $this->guardAccess();

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Runs the guard + the mill resolution + the line resolution + the
        // row-limit check NOW, before a single byte of the response is
        // committed — a refused export must never begin streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId, $productionLineId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows, $format) {
                // A failure WHILE writing is still EXPORT_FAILED (422), not a
                // half-written file reported as a success.
                try {
                    $handle = SheetWriter::open($format);
                    $handle->row(self::EXPORT_HEADER);

                    foreach ($rows as $row) {
                        $handle->row($row);
                    }

                    $handle->close();
                } catch (ExportFailedException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw new ExportFailedException;
                }
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException;
        }
    }

    // ------------------------------------------------------------------
    // Aggregation — ALL OF IT IN PHP, none of it in SQL
    // ------------------------------------------------------------------

    /**
     * Every figure of ONE flow, computed over that flow's rows only.
     *
     * Called twice from buildSummary() and never folded: there is no third
     * call that sums the two, and no key below is derived from the other
     * flow. A flow with zero trips still returns the FULL group — trip_count
     * 0, totals and averages null, 24 hourly buckets at 0, busiest_hour null,
     * empty_hour_count 24, and an empty breakdown list. It is never omitted
     * from the payload: an absent section reads as "this does not exist",
     * while a present section full of zeroes reads as "there were none", and
     * only the second is true.
     *
     * `by_origin` is emitted for the RECEIVE flow and `by_destination` for
     * the DISPATCH flow — one breakdown each, the one that answers that
     * flow's question. Neither flow carries the other's key.
     *
     * @param  Collection<int, object>  $trips
     */
    protected function summaryFlow(Collection $trips, string $flow): array
    {
        $tripCount = $trips->count();

        // THE DENOMINATOR OF THE WEIGHT FIGURES, and the only place it is
        // established: rows where net_weight IS NOT NULL. Nulls are dropped,
        // never replaced by zero.
        $weights = $trips
            ->map(fn ($trip) => $trip->net_weight)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        $netWeightTripCount = count($weights);

        $group = [
            // ALL rows of the flow, including those with NULL net_weight.
            'trip_count' => $tripCount,
            // null, NOT 0, when nothing was weighed to the end: zero would
            // claim a measured total of nothing.
            'net_weight_total' => $netWeightTripCount === 0
                ? null
                : round(array_sum($weights), 2),
            // Guarded division: never a DivisionByZeroError, and never 0.
            'net_weight_avg' => $netWeightTripCount === 0
                ? null
                : round(array_sum($weights) / $netWeightTripCount, 2),
            // THE DENOMINATOR, published next to the average it belongs to.
            'net_weight_trip_count' => $netWeightTripCount,
            // PER FLOW. Never merged with the other flow's figure.
            'missing_net_weight_trip_count' => $tripCount - $netWeightTripCount,
        ];

        $group += $this->hourlyBlockOf($trips);

        if ($flow === self::FLOW_RECEIVE) {
            $group['by_origin'] = $this->byOriginOf($trips);
        } else {
            $group['by_destination'] = $this->byDestinationOf($trips);
        }

        return $group;
    }

    /**
     * The 24-bucket hour-of-day distribution of one flow, plus its busiest
     * hour and its empty-hour count.
     *
     * ALL 24 HOURS ARE ALWAYS PRESENT, in ascending order, even at zero — an
     * hour that is missing from the list reads as an hour that does not
     * exist, which is never what is meant. Each trip lands in the bucket of
     * ITS OWN record_datetime hour and in no other bucket, so the 24 counts
     * sum EXACTLY to the flow's trip_count.
     *
     * busiest_hour is null — not 0 — when the flow has no dated trip at all:
     * hour 0 is a real hour, and naming it would claim a peak nobody
     * measured. Ties resolve to the EARLIEST hour, so the answer is
     * deterministic rather than dependent on iteration order.
     *
     * THE HOUR COMES FROM PHP, NEVER FROM SQL. EXTRACT(HOUR FROM ...) is
     * PostgreSQL, strftime('%H', ...) is SQLite, and neither runs on the
     * other; `$trip->hour` was taken from a Carbon instance in tripsFor().
     *
     * @param  Collection<int, object>  $trips
     * @return array{hourly: list<array{hour: int, trip_count: int}>, busiest_hour: int|null, busiest_hour_trip_count: int, empty_hour_count: int}
     */
    protected function hourlyBlockOf(Collection $trips): array
    {
        $counts = array_fill(0, 24, 0);

        foreach ($trips as $trip) {
            $counts[$trip->hour]++;
        }

        $hourly = [];

        foreach ($counts as $hour => $count) {
            $hourly[] = ['hour' => $hour, 'trip_count' => $count];
        }

        $busiestHour = null;
        $busiestCount = 0;

        foreach ($counts as $hour => $count) {
            // Strictly greater than: the earliest hour wins a tie.
            if ($count > $busiestCount) {
                $busiestHour = $hour;
                $busiestCount = $count;
            }
        }

        return [
            'hourly' => $hourly,
            'busiest_hour' => $busiestHour,
            'busiest_hour_trip_count' => $busiestCount,
            'empty_hour_count' => count(array_filter($counts, fn ($count) => $count === 0)),
        ];
    }

    /**
     * The RECEIVE flow grouped by estate_supplier — where the FFB came from.
     *
     * NEVER TRUNCATED, and there is no 'others' / 'lain-lain' bucket:
     * trimming the list makes the smallest supplier vanish from the report
     * entirely, and the trip counts then stop summing to the flow's
     * trip_count. Every origin that has a trip in the period gets its own
     * row, so the trip_count column sums EXACTLY to receive.trip_count.
     *
     * Ordered by net_weight_total DESCENDING, per business_logic 14, with
     * trip_count then the label as deterministic tie-breakers.
     *
     * @param  Collection<int, object>  $trips
     * @return list<array{estate_supplier: string, trip_count: int, net_weight_total: float|null, net_weight_trip_count: int}>
     */
    protected function byOriginOf(Collection $trips): array
    {
        return $this->groupedBreakdownOf($trips, 'estate_supplier', 'estate_supplier');
    }

    /**
     * The DISPATCH flow grouped by destination — where the shipment went.
     *
     * A NULL destination FORMS ONE GROUP OF ITS OWN and is NEVER DROPPED
     * (business_logic 15). Keeping it is what makes the per-destination trip
     * counts sum EXACTLY to dispatch.trip_count; dropping it would leave the
     * reader with a difference they cannot explain. The group's key is
     * literal null — not an empty string and not a made-up label — so the
     * screen can label it "Belum diisi" without the service inventing a
     * destination that was never recorded.
     *
     * A trip can have a net weight and NO destination, or the reverse: each
     * metric is computed from the trips where THAT metric is filled, with its
     * own denominator.
     *
     * @param  Collection<int, object>  $trips
     * @return list<array{destination: string|null, trip_count: int, net_weight_total: float|null, net_weight_trip_count: int}>
     */
    protected function byDestinationOf(Collection $trips): array
    {
        return $this->groupedBreakdownOf($trips, 'destination', 'destination', true);
    }

    /**
     * The shared body of byOriginOf() / byDestinationOf().
     *
     * $keepNull decides whether a null grouping value survives as a group of
     * its own (destination: yes — it is a real, reportable state) or is
     * normalised to an empty string (estate_supplier: the column is
     * non-nullable, so this branch should never be reached; normalising keeps
     * the key's declared type `string`).
     *
     * GROUPING HAPPENS IN PHP ON THE EXACT STORED VALUE. No `GROUP BY`, no
     * `LIKE`, and emphatically no `ILIKE` — ILIKE is a SYNTAX ERROR on SQLite
     * while bare LIKE is case-SENSITIVE on PostgreSQL, so a SQL-side grouping
     * expression could pass the entire test suite and still merge (or fail to
     * merge) the wrong rows in production. Two values that differ only in
     * case are two distinct, separately reported origins — exactly what was
     * typed in, with no judgement applied.
     *
     * @param  Collection<int, object>  $trips
     * @return list<array<string, mixed>>
     */
    protected function groupedBreakdownOf(
        Collection $trips,
        string $attribute,
        string $keyName,
        bool $keepNull = false,
    ): array {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($trips as $trip) {
            $raw = $trip->{$attribute};

            if ($raw === null && ! $keepNull) {
                $raw = '';
            }

            // A deliberately unambiguous bucket key: null can never collide
            // with the literal string "null" typed by an operator.
            $bucket = $raw === null ? "\0null" : 'v:'.$raw;

            if (! isset($groups[$bucket])) {
                $groups[$bucket] = [
                    $keyName => $raw,
                    'trip_count' => 0,
                    'net_weight_total' => null,
                    'net_weight_trip_count' => 0,
                ];
            }

            $groups[$bucket]['trip_count']++;

            if ($trip->net_weight !== null) {
                $groups[$bucket]['net_weight_total'] = (float) ($groups[$bucket]['net_weight_total'] ?? 0.0)
                    + $trip->net_weight;
                $groups[$bucket]['net_weight_trip_count']++;
            }
        }

        $rows = array_values($groups);

        usort($rows, function (array $a, array $b) use ($keyName) {
            // Null total sorts as 0.0 for ORDERING ONLY — it stays null in
            // the payload, because "never weighed" is not "weighed nothing".
            $byWeight = ((float) ($b['net_weight_total'] ?? 0.0)) <=> ((float) ($a['net_weight_total'] ?? 0.0));

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byTrips = $b['trip_count'] <=> $a['trip_count'];

            if ($byTrips !== 0) {
                return $byTrips;
            }

            // Deterministic last resort. null sorts last among equals.
            return ((string) ($a[$keyName] ?? "\u{FFFF}")) <=> ((string) ($b[$keyName] ?? "\u{FFFF}"));
        });

        return array_map(function (array $row) {
            if ($row['net_weight_total'] !== null) {
                $row['net_weight_total'] = round((float) $row['net_weight_total'], 2);
            }

            return $row;
        }, $rows);
    }

    /**
     * The daily recap — one row per DATE that has at least one trip, with the
     * two flows kept in SEPARATE COLUMNS.
     *
     * A date with no trip at all gets NO ROW: a zero row would read as
     * "measured, and the answer was zero", which is a different and stronger
     * claim than "nothing was recorded". The screen renders the absence as
     * an em dash in the trend instead.
     *
     * The date part comes from PHP (`$trip->date`, formatted from a Carbon
     * instance in tripsFor()), never from a SQL date function: `date_trunc`
     * is PostgreSQL, `strftime('%Y-%m-%d', ...)` is SQLite, and neither runs
     * on the other.
     *
     * Per-flow weight totals are null — never 0.0 — on a date where that flow
     * has no trip whose net_weight is filled.
     *
     * @param  Collection<int, object>  $trips
     * @return list<array{date: string, receive_trip_count: int, receive_net_weight_total: float|null, dispatch_trip_count: int, dispatch_net_weight_total: float|null}>
     */
    protected function dailyOf(Collection $trips): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($trips as $trip) {
            if (! isset($rows[$trip->date])) {
                $rows[$trip->date] = [
                    'date' => $trip->date,
                    'receive_trip_count' => 0,
                    'receive_net_weight_total' => null,
                    'dispatch_trip_count' => 0,
                    'dispatch_net_weight_total' => null,
                ];
            }

            $rows[$trip->date][$trip->flow.'_trip_count']++;

            if ($trip->net_weight !== null) {
                $key = $trip->flow.'_net_weight_total';
                $rows[$trip->date][$key] = (float) ($rows[$trip->date][$key] ?? 0.0) + $trip->net_weight;
            }
        }

        ksort($rows);

        return array_map(function (array $row) {
            foreach ([self::FLOW_RECEIVE, self::FLOW_DISPATCH] as $flow) {
                $key = $flow.'_net_weight_total';

                if ($row[$key] !== null) {
                    $row[$key] = round((float) $row[$key], 2);
                }
            }

            return $row;
        }, array_values($rows));
    }

    /**
     * The daily recap's footer row: the sum of every `daily` row, WITH THE
     * TWO FLOWS STILL SEPARATE. There is no fifth key adding them together.
     *
     * By construction these four numbers agree with the two flow groups:
     * receive_trip_count equals receive.trip_count and
     * receive_net_weight_total equals receive.net_weight_total, because both
     * are computed over the same dated rows. That agreement is what lets a
     * reader check the table against the headline cards.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{receive_trip_count: int, receive_net_weight_total: float|null, dispatch_trip_count: int, dispatch_net_weight_total: float|null}
     */
    protected function dailyTotalOf(array $daily): array
    {
        $total = [
            'receive_trip_count' => 0,
            'receive_net_weight_total' => null,
            'dispatch_trip_count' => 0,
            'dispatch_net_weight_total' => null,
        ];

        foreach ($daily as $row) {
            foreach ([self::FLOW_RECEIVE, self::FLOW_DISPATCH] as $flow) {
                $total[$flow.'_trip_count'] += (int) $row[$flow.'_trip_count'];

                $key = $flow.'_net_weight_total';

                if ($row[$key] !== null) {
                    $total[$key] = (float) ($total[$key] ?? 0.0) + (float) $row[$key];
                }
            }
        }

        foreach ([self::FLOW_RECEIVE, self::FLOW_DISPATCH] as $flow) {
            $key = $flow.'_net_weight_total';

            if ($total[$key] !== null) {
                $total[$key] = round((float) $total[$key], 2);
            }
        }

        return $total;
    }

    /**
     * Every DATED trip of the period on this production line, flattened to
     * plain objects carrying exactly what the aggregation needs.
     *
     * `flow`, `date` and `hour` are derived HERE, once, in PHP — so no
     * aggregation below ever needs a database date function, and the hour a
     * trip is bucketed into is provably the hour of its own record_datetime.
     *
     * NO STATUS FILTER IS APPLIED: draft rows count in every figure
     * (rule 10).
     *
     * @return Collection<int, object>
     */
    protected function tripsFor(Period $period, string $productionLineId): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            ->orderBy('weighbridge_records.record_datetime')
            ->orderBy('weighbridge_records.id')
            ->get()
            ->map(fn (WeighbridgeRecord $record) => (object) [
                'flow' => $this->flowOf($record),
                // "Y-m-d" of the WEIGHING, not of the row's creation.
                'date' => $this->dateStringOf($record->record_datetime),
                // 0-23, taken from this trip's own single timestamp.
                'hour' => (int) $record->record_datetime->format('G'),
                // NULL STAYS NULL. Never coerced to 0.0 — a weighing that was
                // never finished must not be able to drag an average down.
                'net_weight' => $record->net_weight === null ? null : (float) $record->net_weight,
                'destination' => $record->destination,
                'estate_supplier' => (string) ($record->estate_supplier ?? ''),
                'status' => $this->recordStatusValue($record),
            ])
            ->values();
    }

    /**
     * Base query over the period's Weighbridge trips.
     *
     * THREE THINGS THIS QUERY DOES AND ONE IT DELIBERATELY DOES NOT:
     *
     *  - It filters on `weighbridge_records.production_line_id`, the record's
     *     OWN snapshot column, and performs NO JOIN TO `stations` for that or
     *     for anything else. A join would rewrite history whenever a station
     *     is moved to another line. The mill scope that the sibling services
     *     get from such a join is already closed upstream here:
     *     resolveProductionLine() has proven the line belongs to the
     *     effective mill, and a line belongs to exactly one mill.
     *  - It bounds membership INCLUSIVELY on `record_datetime` — the time the
     *     weighing happened. whereDate() compares the DATE PART, so a trip at
     *     23:50 on the last day is inside and a trip at 00:10 on the day after
     *     is outside. Laravel renders whereDate() per driver (`::date` on
     *     PostgreSQL, `strftime` on SQLite), which is exactly why it is used
     *     instead of a hand-written expression.
     *  - It EXCLUDES rows whose record_datetime is NULL: such a row cannot be
     *     placed in any period at all (rule 7). They are counted, separately
     *     and openly, by undatedTripCount().
     *  - It applies NO status filter: draft rows are part of every figure.
     *
     * It is never `created_at` and never a sync time — a row entered late
     * still belongs to the period it happened in.
     */
    protected function recordQueryFor(Period $period, string $productionLineId): Builder
    {
        return WeighbridgeRecord::query()
            ->where('weighbridge_records.production_line_id', $productionLineId)
            ->whereNotNull('weighbridge_records.record_datetime')
            ->whereDate('weighbridge_records.record_datetime', '>=', $period->start_date->toDateString())
            ->whereDate('weighbridge_records.record_datetime', '<=', $period->end_date->toDateString())
            ->select('weighbridge_records.*');
    }

    /**
     * How many trips on this production line carry NO weighing timestamp at
     * all.
     *
     * NOT bounded by the period — that is the whole point: without a
     * timestamp a row cannot be placed in any period, so there is no period
     * to bound it by. It is reported so the difference between this screen's
     * figures and the row count the reader knows from the Data Browser can be
     * EXPLAINED rather than merely noticed.
     */
    protected function undatedTripCount(string $productionLineId): int
    {
        return WeighbridgeRecord::query()
            ->where('weighbridge_records.production_line_id', $productionLineId)
            ->whereNull('weighbridge_records.record_datetime')
            ->count();
    }

    // ------------------------------------------------------------------
    // Export streaming
    // ------------------------------------------------------------------

    /**
     * The lazy half of buildExportRows(): one array per TRIP, pulled in
     * chunks so a long period never materialises as one collection.
     *
     * The three context values (period / mill / production line) are computed
     * ONCE by the caller and repeated verbatim on every row, together with the
     * per-row flow label — four context columns, matching
     * EXPORT_CONTEXT_COLUMN_COUNT.
     *
     * EXACTLY ONE TIMESTAMP COLUMN, and no duration column: see rule 2.
     * net_weight NULL is written as null, which fputcsv() renders as an EMPTY
     * CELL — not 0, and the row is not dropped.
     *
     * @param  array{0: string, 1: string, 2: string}  $context
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $context): Generator
    {
        $query = (clone $recordQuery)
            ->orderBy('weighbridge_records.record_datetime')
            ->orderBy('weighbridge_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var WeighbridgeRecord $record */
            yield array_merge($context, [
                // The flow type, so receive and dispatch rows stay
                // distinguishable in the file without ever being summed.
                self::FLOW_LABELS[$this->flowOf($record)] ?? $this->flowOf($record),
                optional($record->record_datetime)->format('Y-m-d H:i'),
                $record->wb_card_number,
                $record->vehicle_number,
                $record->driver_name,
                $record->estate_supplier,
                $record->destination,
                $record->division,
                $record->block,
                $record->gross_weight,
                $record->tare_weight,
                // Stays NULL -> empty cell. Never 0, never a dropped row.
                $record->net_weight,
                $record->quantity,
                // Label Indonesia, bukan enum mentah (temuan audit 2026-10-04 #8c).
                ExportValue::status($this->recordStatusValue($record)),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on purpose:
     * clearing the middleware must never be enough by itself.
     *
     * OPERATOR IS ADMITTED SINCE 2026-10-05 — screen-144, the mobile
     * Weighbridge report, calls these endpoints. It was THREE changes
     * together: routes/api.php, this method, and the MILL-BOUND branch of
     * resolveBusinessUnit(). This method alone would have dropped Operator
     * into the Admin branch where the client's business_unit_id IS honoured:
     * a cross-mill leak, not a display defect. That was the lesson from
     * screen-129/135, and it is why the two edits are commented as halves of
     * one change rather than two independent additions.
     *
     * businessUnitOptions() is the one entry point that still refuses
     * Operator, and it refuses it HERE's successor layer — its own check —
     * precisely because this gate now lets Operator through.
     *
     * @return string the caller's role
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function guardAccess(): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $role = $this->roleOf($user);

        if (! in_array($role, [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
            // screen-144 (mobile). Admitting the role here is only HALF the
            // widening — see resolveBusinessUnit(), which must also place
            // Operator in the mill-bound branch, or this line opens a
            // cross-mill read.
            UserRole::Operator->value,
        ], true)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $role;
    }

    /**
     * The mill check shared by authorizePeriod() and by every entry point
     * that accepts an already-loaded Period. Re-running it on a model that
     * was authorised a moment ago costs nothing and closes the gap where a
     * caller hands in a Period it never checked.
     *
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function authorizePeriodModel(Period $period): Period
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ((string) $period->business_unit_id !== (string) (auth()->user()->business_unit_id ?? '')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    /**
     * A missing period_id is 422 VALIDATION_ERROR — incomplete input, not a
     * 404 for the empty string and not a silently empty report.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function requirePeriod(Period|string|null $period): Period
    {
        if ($period === null || $period === '') {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        if ($period instanceof Period) {
            $period = $period->relationLoaded('businessUnit') ? $period : $period->load('businessUnit');

            return $this->authorizePeriodModel($period);
        }

        return $this->authorizePeriod($period);
    }

    /**
     * The mill the figures belong to — THE EFFECTIVE one resolved by
     * resolveBusinessUnit(), never the one the client asked for. For a
     * mill-bound role that is always the account's own mill, which is exactly
     * what makes a probe with another mill's id answer 200 with the caller's
     * own name.
     *
     * @return array{id: string, name: string}
     */
    protected function businessUnitInfo(string $businessUnitId): array
    {
        /** @var BusinessUnit|null $businessUnit */
        $businessUnit = BusinessUnit::query()->find($businessUnitId, ['id', 'name']);

        return [
            'id' => $businessUnitId,
            'name' => (string) ($businessUnit?->name ?? ''),
        ];
    }

    /**
     * The production line the figures belong to, named. Figures mean nothing
     * without saying which line produced them — which is precisely why
     * choosing one is mandatory here.
     *
     * @return array{id: string, name: string}
     */
    protected function productionLineInfo(string $productionLineId): array
    {
        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()->find($productionLineId, ['id', 'name']);

        return [
            'id' => $productionLineId,
            'name' => (string) ($line?->name ?? ''),
        ];
    }

    /** Inclusive day count of the period — both ends belong to it. */
    protected function daysInPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    /**
     * The flow a trip belongs to, normalised to one of FLOWS.
     *
     * `weighbridge_type` is a plain string column (the enum lives in the DB
     * constraint, not in a PHP cast), so an unexpected value is possible in
     * principle; it is mapped to 'receive', which is the column's own DB
     * default, rather than silently creating a third flow the payload has no
     * place for.
     */
    protected function flowOf(WeighbridgeRecord $record): string
    {
        $value = $record->weighbridge_type instanceof \BackedEnum
            ? $record->weighbridge_type->value
            : (string) $record->weighbridge_type;

        return in_array($value, self::FLOWS, true) ? $value : self::FLOW_RECEIVE;
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya DATAR, persis seperti
     * kelima laporan stasiun sebelumnya, sehingga pemisahan
     * periods/period_stations tidak merembes ke kontrak API.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, weighbridge). Karena itu `status` adalah status STASIUN INI
     * di periode itu (period_stations.status) — periode sendiri tidak punya
     * status lagi sejak 2026-09-25.
     *
     * @return array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}
     */
    protected function periodOption(Period $period): array
    {
        return [
            'id' => (string) $period->id,
            'name' => (string) $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $this->statusValue($period),
            'station_type' => self::STATION_TYPE,
            'station_type_label' => $this->stationTypeLabel(self::STATION_TYPE),
        ];
    }

    /**
     * Status yang dilaporkan layar ini adalah status BARIS period_stations
     * untuk jenis stasiun layar ini, bukan status periode: sejak 2026-09-25
     * periode tidak punya status sendiri (Period::$status melempar
     * LogicException), karena stasiun tidak ditutup serentak.
     *
     * Tanpa baris untuk jenis ini jawabannya 'draft' — arti draft memang
     * "stasiun ini belum dipakai di periode ini". Sengaja bukan string
     * kosong: nilai status harus tetap salah satu dari draft/open/closed
     * karena blade mencocokkannya.
     *
     * Status TIDAK pernah membatasi pembacaan maupun ekspor di layar ini.
     */
    protected function statusValue(Period $period): string
    {
        $status = $this->stationRowOf($period)?->status;

        if ($status === null) {
            return PeriodStatus::Draft->value;
        }

        return is_object($status) ? $status->value : (string) $status;
    }

    /**
     * Baris period_stations untuk jenis stasiun layar ini. Memakai relasi
     * yang sudah di-eager-load bila ada — listPeriods() memuatnya terbatas
     * pada jenis ini — dan baru menembak kueri sendiri bila belum.
     */
    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', self::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', self::STATION_TYPE)
            ->first();
    }

    protected function recordStatusValue(WeighbridgeRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /**
     * Label resolved from the `station_types` master table, not from
     * App\Enums\StationType — station types are DATA since 2026-09-22, so a
     * type added by INSERT must render its real name without a code change.
     */
    protected function stationTypeLabel(string $code): string
    {
        if ($this->stationTypeNames === null) {
            $this->stationTypeNames = StationType::query()
                ->get(['code', 'name'])
                ->pluck('name', 'code')
                ->all();
        }

        return $this->stationTypeNames[$code] ?? $code;
    }

    protected function dateStringOf(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Same Content-Type/filename convention as every other export in this
     * codebase. format=excel is a real .xlsx written by App\Support\SheetWriter (temuan
     * audit 2026-10-04 #1 — previously a CSV body under an xlsx name).
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format, Period $period): array
    {
        $slug = str($period->name !== '' ? $period->name : 'periode')->slug()->value();
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "laporan-weighbridge_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-weighbridge_{$slug}_{$timestamp}.csv",
        ];
    }
}
