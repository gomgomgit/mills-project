<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodOverlapException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\StationType;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PeriodService — screen-128--kelola-periode-pelaporan /
 * usecase-128--kelola-periode-pelaporan (Kelola Periode Pelaporan —
 * admin-only CRUD over the reporting periods every Full Cycle per Station
 * report is bucketed by).
 *
 * Shared by the API controller (App\Http\Controllers\Api\PeriodController)
 * and the Livewire component (App\Livewire\MasterData\
 * KelolaPeriodePelaporan), same as every other master-data service in this
 * codebase (ProductionLineService/StationService/MachineryGroupService) —
 * so the web form and the API enforce the identical rule set.
 *
 * SPLIT OF RESPONSIBILITY — this class owns list/create/update/delete
 * (usecase-128). Closing and reopening ONE STATION TYPE inside a period
 * (usecase-140) lives in PeriodClosureService: it is a different use case
 * with its own concurrency contract (a conditional UPDATE), and keeping it
 * separate stops the CRUD path from having to know anything about the 18
 * station record tables.
 *
 * SCOPE OF A PERIOD IS THE MILL, FULL STOP (keputusan user 2026-09-25).
 * Until then the scope was (business_unit_id, station_type) and
 * `station_type` NULL meant "every station type in this mill". Both the
 * column and the NULL-wildcard concept are gone:
 *
 *  - ONE DATE ONLY EVER BELONGS TO ONE PERIOD PER MILL. The overlap check
 *    no longer takes a station type at all (see findOverlapping()) — two
 *    periods of the same mill may not share a single day even when they
 *    were "about" different stations.
 *  - THE PERIOD NO LONGER TAKES A STATION CHOICE FROM ITS CALLER.
 *    validate() does not accept `station_type`; create() derives the
 *    station list itself (see activeStationTypesForMill()).
 *  - WHAT IS PER STATION TYPE is only the close/reopen STATUS, one row per
 *    type in `period_stations` ({@see PeriodStation}), because stations do
 *    not finish at the same time. The row set is not a scope filter — it is
 *    the list of things that can be closed separately.
 *  - "Semua Stasiun" as a label is gone with the NULL scope it described.
 *
 * STATION TYPE IS MASTER DATA (2026-09-22): a station type is a row in
 * `station_types`, never a case of App\Enums\StationType — adding a type is
 * an INSERT, so anything hardcoding the enum would go stale the moment a
 * type is added without a code change. The enum is still the right tool
 * when logic names ONE specific type; this screen never does.
 */
class PeriodService
{
    /**
     * code => ['name' => string, 'sort_order' => int], lazily read from the
     * `station_types` master table and memoised for the lifetime of this
     * service instance (one request / one Livewire render — both the
     * controller and the component resolve a fresh instance from the
     * container, so this never goes stale).
     *
     * @var array<string, array{name: string, sort_order: int}>|null
     */
    protected ?array $stationTypeMeta = null;

    /**
     * listPeriods() — business_logic step "list": paginate, eager-load
     * businessUnit + the period's station rows (with their closer), optional
     * business_unit_id and status filters, newest period first (order by
     * start_date desc), per_page clamped to 100 by the shared Pagination
     * helper's MAX_PER_PAGE.
     *
     * THE STATUS FILTER MEANS "HAS AT LEAST ONE STATION IN THIS STATUS"
     * (an EXISTS against `period_stations`), not "all of its stations are".
     * Since 2026-09-25 a period holds one status per station type, so the
     * filter had to pick one of the two readings, and "all" is the harmful
     * one: a period with 18 closed stations and 1 still open matches
     * NEITHER `closed` nor `open` under "all", so it disappears from every
     * filtered view while plainly existing in the unfiltered one — the
     * worst failure mode a list filter can have. Under "any" that same
     * period shows up under both `closed` and `open`, which is exactly what
     * it is, and no row can ever be hidden by a filter value.
     *
     * The filter VALUES are unchanged (draft|open|closed), so the
     * `#[Url]`-bound `filterStatus` in the Livewire component keeps working
     * for URLs bookmarked before this change — only the set of rows a value
     * matches got wider.
     */
    public function listPeriods(
        int $page,
        int $perPage,
        ?string $businessUnitId = null,
        ?string $status = null
    ): array {
        $query = Period::query()
            ->with(['businessUnit', 'stations.closedBy'])
            ->orderByDesc('start_date')
            ->orderBy('name');

        if ($businessUnitId !== null && $businessUnitId !== '') {
            $query->where('business_unit_id', $businessUnitId);
        }

        if ($status !== null && $status !== '') {
            // Qualified with the child table on purpose: `status` is no
            // longer a `periods` column, and an unqualified where() here
            // would be refused by PeriodQueryBuilder.
            $query->whereHas('stations', fn ($stations) => $stations->where('period_stations.status', $status));
        }

        $perPage = min(max($perPage, 1), Pagination::MAX_PER_PAGE);

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (Period $period) => $this->toRow($period))
            ->all();

        return $formatted;
    }

    /**
     * getDetail() — ONE period, in the exact shape one entry of
     * listPeriods()'s `data[]` has (screen-142--detail-periode-pelaporan /
     * usecase-145, GET /api/periods/{id} and the Livewire detail component).
     *
     * IT RETURNS toRow() UNCHANGED, ON PURPOSE. The detail screen shows the
     * same object the list screen shows, so giving it a second shape —
     * flattened, renamed, or with the station rows lifted out — would mean
     * two representations of a period that have to be kept in step forever.
     * `stations[]` is already ordered by the master's sort_order here; no
     * caller may reorder it.
     *
     * Eager-loads exactly what toRow() reads (businessUnit for the mill
     * name, stations.closedBy for the per-row closer name), mirroring
     * listPeriods().
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException 404 NOT_FOUND — no such period, or it
     *                                was deleted by another Admin
     */
    public function getDetail(string $id): array
    {
        /** @var Period $period */
        $period = Period::query()
            ->with(['businessUnit', 'stations.closedBy'])
            ->findOrFail($id);

        return $this->toRow($period);
    }

    /**
     * businessUnitOptions() — feeds the Business Unit-select on the
     * create/edit form. Mirrors ProductionLineService::
     * businessUnitOptions() exactly; returns [] (not an exception) when no
     * Business Unit exists at all, so the form can show the "create a
     * Business Unit first" hint.
     *
     * @return list<array{id: string, name: string}>
     */
    public function businessUnitOptions(): array
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => $businessUnit->id,
                'name' => $businessUnit->name,
            ])
            ->all();
    }

    /**
     * stationTypeOptions() — every ACTIVE station type in the master table,
     * in process order. NOT a form input any more (a period takes no
     * station choice since 2026-09-25) — it is the label/order source for
     * rendering a period's station rows and for a station-type filter. The
     * caller no longer prepends an empty "Semua Stasiun" option: that scope
     * does not exist.
     *
     * @return list<array{code: string, name: string}>
     */
    public function stationTypeOptions(): array
    {
        return StationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->map(fn (StationType $stationType) => [
                'code' => $stationType->code,
                'name' => $stationType->name,
            ])
            ->all();
    }

    /**
     * create() — business_logic steps "create": validate (business_unit_id
     * exists, name required + unique per mill, end_date >= start_date) →
     * 422 → check range overlap in the same mill → 422 PERIOD_OVERLAP
     * naming the conflicting period → INSERT the period AND one
     * `period_stations` row per station type active in that mill, all
     * status='draft'.
     *
     * Both writes happen in ONE transaction: a period without its station
     * rows has nothing that can be closed and nothing that locks its
     * records, which is a worse state than no period at all.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws PeriodOverlapException
     */
    public function create(array $data): array
    {
        $attributes = $this->validate($data, null);

        $this->guardAgainstOverlap(
            $attributes['business_unit_id'],
            $attributes['start_date'],
            $attributes['end_date'],
            null
        );

        $attributes['created_by'] = auth()->id();

        $period = DB::transaction(function () use ($attributes) {
            $period = Period::create($attributes);

            foreach ($this->activeStationTypesForMill($attributes['business_unit_id']) as $code) {
                PeriodStation::create([
                    'period_id' => $period->id,
                    'station_type' => $code,
                    'status' => PeriodStatus::Draft->value,
                    'closed_by' => null,
                    'closed_at' => null,
                ]);
            }

            return $period;
        });

        $period->load(['businessUnit', 'stations.closedBy']);

        return $this->toRow($period);
    }

    /**
     * update() — business_logic steps "update": findOrFail → 404 → refuse
     * with 409 PERIOD_CLOSED_IMMUTABLE when AT LEAST ONE of the period's
     * station rows is closed (BEFORE anything is written, so a rejected
     * attempt leaves the row bit-for-bit unchanged) → same validation as
     * create() with the uniqueness and overlap checks excluding this row →
     * UPDATE with updated_by=actor → BACKFILL the station rows the mill has
     * gained since the period was created.
     *
     * THE BACKFILL, AND WHY IT IS HERE (keputusan user 2026-09-26)
     * create() takes a SNAPSHOT of the mill's active station types. A type
     * added to the mill afterwards therefore had no `period_stations` row in
     * any period that already existed — it could not be closed separately,
     * and its records were never locked by the period. That is a silent hole
     * in the very guarantee this whole model exists for: a period governs its
     * entire mill, so a snapshot that drifts away from the mill quietly
     * cancels that rule. update() closes the hole by re-deriving
     * activeStationTypesForMill() and inserting a `draft` row for every code
     * that has none yet.
     *
     * THE BACKFILL ONLY EVER ADDS. A row is never removed, not even when its
     * station type has been retired from the mill or from the master table: a
     * closed row carries WHO closed it and WHEN, and that record must not
     * evaporate because an unrelated save happened to run. Existing rows —
     * draft, open or closed — are left exactly as they are.
     *
     * IT RUNS AFTER THE 409 GUARD, NOT BEFORE — deliberately. A refused
     * update must leave the period bit-for-bit unchanged (this method's
     * oldest documented property, and inserting rows would change
     * station_count and status_summary on a call that returned an error).
     * THE COST IS REAL AND MUST NOT BE FORGOTTEN: a period that already has
     * ONE closed station can never be backfilled through update(), because
     * every update() on it is refused. Today's way out is manual and
     * available: reopen the closed station ("Buka Kembali"), save the period
     * once, then close the station again. The proper fix is a dedicated
     * add-only "sync stations" action that is allowed even on an immutable
     * period — adding a draft row breaks no seal, it only creates one more
     * thing that CAN be sealed — but that is a new use case, not a quiet
     * change of what update() refuses.
     *
     * The UPDATE and the backfill inserts share ONE transaction, for the same
     * reason create()'s do: a half-applied station list is worse than none.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws PeriodClosedImmutableException
     * @throws ValidationException
     * @throws PeriodOverlapException
     */
    public function update(string $id, array $data): array
    {
        $period = Period::findOrFail($id);

        $this->guardAgainstClosedStation($period);

        $attributes = $this->validate($data, $period->id);

        $this->guardAgainstOverlap(
            $attributes['business_unit_id'],
            $attributes['start_date'],
            $attributes['end_date'],
            $period->id
        );

        $attributes['updated_by'] = auth()->id();

        DB::transaction(function () use ($period, $attributes) {
            $period->update($attributes);

            $this->backfillStationRows($period);
        });

        $period->load(['businessUnit', 'stations.closedBy']);

        return $this->toRow($period);
    }

    /**
     * Inserts a `draft` row for every station type active in the period's
     * mill that has no row yet. ADD-ONLY — see update()'s docblock.
     *
     * The mill read is the period's CURRENT business_unit_id, i.e. the one it
     * has after the update: a period that was moved to another mill governs
     * that mill from then on, so that is the inventory its rows must cover.
     * Rows inherited from the previous mill stay behind (nothing is ever
     * removed), which leaves the union of both inventories — an accepted
     * consequence of the never-remove rule, not an oversight.
     */
    protected function backfillStationRows(Period $period): void
    {
        $existing = $period->stations()
            // Qualified on purpose: this reads `period_stations`, and the
            // unqualified spelling of a column that ALSO used to live on
            // `periods` is exactly the ambiguity PeriodQueryBuilder exists
            // to keep out of this file.
            ->pluck('period_stations.station_type')
            ->all();

        $missing = array_diff(
            $this->activeStationTypesForMill($period->business_unit_id),
            $existing
        );

        foreach ($missing as $code) {
            PeriodStation::create([
                'period_id' => $period->id,
                'station_type' => $code,
                'status' => PeriodStatus::Draft->value,
                'closed_by' => null,
                'closed_at' => null,
            ]);
        }
    }

    /**
     * delete() — business_logic step "delete": findOrFail → 404 → refuse
     * with 409 PERIOD_CLOSED_IMMUTABLE when AT LEAST ONE station row is
     * closed (nothing is deleted) → else DELETE.
     *
     * @throws ModelNotFoundException
     * @throws PeriodClosedImmutableException
     */
    public function delete(string $id): void
    {
        $period = Period::findOrFail($id);

        $this->guardAgainstClosedStation($period);

        $period->delete();
    }

    /**
     * THE IMMUTABILITY RULE IS "ANY", NOT "ALL" (keputusan user
     * 2026-09-25). A period is frozen the moment ONE of its station types
     * is closed, even if the other 18 are still open.
     *
     * Why "any" and not "all": `period_stations.period_id` is
     * cascadeOnDelete, so delete() on a period takes its station rows with
     * it. Under an "all" rule an Admin could delete a period holding one
     * closed Sterilizer row simply because Clarification was still open —
     * silently unlocking records that had been sealed. update() follows the
     * same rule because moving the date range moves what the closed station
     * locks, which is the same loss of a seal by another route.
     *
     * @throws PeriodClosedImmutableException
     */
    protected function guardAgainstClosedStation(Period $period): void
    {
        $hasClosedStation = $period->stations()
            ->where('status', PeriodStatus::Closed->value)
            ->exists();

        if ($hasClosedStation) {
            throw new PeriodClosedImmutableException;
        }
    }

    /**
     * findOverlapping() — the single SQL clause behind every PERIOD_OVERLAP
     * decision, for BOTH create() and update():
     *
     *   WHERE business_unit_id = ?
     *     AND start_date <= :new_end
     *     AND end_date   >= :new_start
     *
     * Two things are deliberate here:
     *
     * 1. BOUNDS ARE INCLUSIVE — a period ending exactly on another's start
     *    date DOES overlap (they share that day, and a record dated that
     *    day would belong to both).
     * 2. NO STATION TYPE TAKES PART (keputusan user 2026-09-25). One date
     *    belongs to exactly one period per mill, full stop. The old clause
     *    also matched on `station_type`, with NULL treated as a wildcard in
     *    both directions; both the column and the wildcard are gone, and
     *    with them the possibility of two periods of one mill sharing a day
     *    "because they were about different stations" — which was never
     *    answerable when a record had to be assigned to a period.
     *
     * $excludeId keeps a period from colliding with itself on update.
     */
    public function findOverlapping(
        string $businessUnitId,
        string $startDate,
        string $endDate,
        ?string $excludeId = null
    ): ?Period {
        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '<>', $excludeId))
            ->orderBy('start_date')
            ->first();
    }

    /**
     * Throws PeriodOverlapException naming the conflicting period — the
     * edge_case_handling entry requires the Admin to be able to tell WHICH
     * period is in the way without leaving the form.
     *
     * @throws PeriodOverlapException
     */
    protected function guardAgainstOverlap(
        string $businessUnitId,
        string $startDate,
        string $endDate,
        ?string $excludeId
    ): void {
        $conflict = $this->findOverlapping($businessUnitId, $startDate, $endDate, $excludeId);

        if ($conflict === null) {
            return;
        }

        throw new PeriodOverlapException(sprintf(
            'Rentang tanggal beririsan dengan periode "%s" (%s s/d %s) pada mill yang sama. '
            .'Satu tanggal hanya boleh dimiliki satu periode; ubah rentang tanggalnya atau '
            .'sesuaikan periode tersebut terlebih dahulu.',
            $conflict->name,
            $this->formatDate($conflict->start_date),
            $this->formatDate($conflict->end_date)
        ));
    }

    /**
     * The station types a NEW period gets a `period_stations` row for:
     * every type that the mill actually has an ACTIVE station of, and that
     * is still active in the `station_types` master table, in master
     * process order.
     *
     * WHAT "ACTIVE IN THIS MILL" MEANS, AND WHY
     * The mill's station inventory is `stations` — rows carrying both
     * `business_unit_id` (denormalised, see Station's docblock) and
     * `type`. `production_lines` is NOT consulted: a station always hangs
     * off a production line of its own mill, so iterating the mill's
     * stations already covers every line, and adding the join would only
     * be a second way to spell the same set. `stations.is_active` is
     * honoured because a retired station produces no new records, and
     * `station_types.is_active` is honoured because a type retired from the
     * master must not be newly registered anywhere (it is also the FK
     * target, so it must exist there at all).
     *
     * `stations` is the right source rather than the master table alone
     * because the master is the catalogue of what a mill COULD have (19
     * rows, including the historical `other`), not what this one DOES have.
     * A period listing Storage Tank for a mill with no storage tank offers
     * the Admin a closure action that seals nothing.
     *
     * A MILL WITH NO STATIONS GETS A PERIOD WITH NO STATION ROWS — not an
     * exception, and deliberately not the full master list as a fallback.
     * The period is still a legitimate date range for the mill (an Admin
     * may plan periods for a mill still being provisioned), and a mill
     * without stations has no station records, so nothing can escape the
     * period lock. Falling back to the master list would make the row set
     * mean one thing for a provisioned mill (its own inventory) and
     * something else for an empty one (the whole catalogue), which is the
     * kind of hidden branch nobody remembers when reading a closure list.
     * Such a period simply has nothing to close, reports `station_count` 0
     * and `status_summary` 'empty', and stays editable/deletable.
     *
     * THE LIST IS RE-DERIVED ON EVERY create() AND EVERY SUCCESSFUL
     * update() (see update()'s backfill, keputusan user 2026-09-26). A
     * station type added to the mill after a period was created gets its row
     * the next time that period is saved; it is never removed again once it
     * exists. The one gap left is a period that already holds a closed
     * station: update() refuses it outright, so it cannot be backfilled
     * until that station is reopened — spelled out in update()'s docblock.
     *
     * Also PUBLIC on purpose: the screen shows the Admin which stations a
     * period is about to register, now that the form no longer lets them
     * choose. Re-deriving that list in the view would be a second copy of
     * this rule.
     *
     * @return list<string>
     */
    public function activeStationTypesForMill(string $businessUnitId): array
    {
        $codesInMill = Station::query()
            ->where('business_unit_id', $businessUnitId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('type')
            // Station::$casts['type'] is App\Enums\StationType, so pluck()
            // hands back enum cases here — period_stations.station_type is
            // the raw code string (FK to station_types.code).
            ->map(fn ($type) => $type instanceof StationTypeEnum ? $type->value : (string) $type)
            ->all();

        if ($codesInMill === []) {
            return [];
        }

        return StationType::query()
            ->where('is_active', true)
            ->whereIn('code', $codesInMill)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    /**
     * Validates business_unit_id, name, start_date and end_date in one
     * Validator pass — produces shared_decisions.error_format's
     * `{ message, errors: { field: [...] } }` shape.
     *
     * `station_type` IS NOT AN INPUT ANY MORE (keputusan user 2026-09-25).
     * A period covers its whole mill; its station rows are derived by
     * create() from the mill's inventory, never chosen by the caller. A
     * `station_type` key in $data is ignored rather than rejected, so a
     * stale client cannot 422 on a field that no longer means anything.
     *
     * NAME UNIQUENESS IS SCOPED TO business_unit_id ALONE — STRICTER than
     * before. The old rule scoped it to (business_unit_id, station_type)
     * and matched the NULL scope with whereNull(), which PostgreSQL's own
     * UNIQUE index could never enforce (every NULL is distinct there), so
     * two all-station periods of one mill could share a name. One name per
     * mill is now both the app rule and the DB's UNIQUE
     * (business_unit_id, name).
     *
     * @param  array<string, mixed>  $data
     * @return array{business_unit_id: string, name: string, start_date: string, end_date: string}
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $businessUnitId = $this->emptyToNull($data['business_unit_id'] ?? null);

        $nameUniqueRule = Rule::unique('periods', 'name')
            ->where(fn ($query) => $query->where('business_unit_id', $businessUnitId));

        if ($excludeId !== null) {
            $nameUniqueRule = $nameUniqueRule->ignore($excludeId);
        }

        $payload = [
            'business_unit_id' => $businessUnitId,
            'name' => $this->emptyToNull($data['name'] ?? null),
            'start_date' => $this->emptyToNull($data['start_date'] ?? null),
            'end_date' => $this->emptyToNull($data['end_date'] ?? null),
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'name.required' => 'Nama Periode wajib diisi.',
            'name.max' => 'Nama Periode maksimal 255 karakter.',
            'name.unique' => 'Nama Periode sudah digunakan pada Business Unit ini.',
            'start_date.required' => 'Tanggal Mulai wajib diisi.',
            'start_date.date' => 'Tanggal Mulai tidak valid.',
            'end_date.required' => 'Tanggal Selesai wajib diisi.',
            'end_date.date' => 'Tanggal Selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal Selesai tidak boleh lebih awal dari Tanggal Mulai.',
        ];

        $validated = Validator::make($payload, $rules, $messages)->validate();

        // Normalise both dates to Y-m-d before they reach the overlap query
        // or the INSERT: the request may carry an ISO-8601 datetime, while
        // `periods.start_date`/`end_date` are DATE columns and the overlap
        // comparison is day-granular.
        $validated['start_date'] = Carbon::parse($validated['start_date'])->toDateString();
        $validated['end_date'] = Carbon::parse($validated['end_date'])->toDateString();

        return $validated;
    }

    /**
     * toRow() — THE RESPONSE CONTRACT of screen-128 (Kelola Periode
     * Pelaporan) and of `GET /api/periods`, returned by listPeriods(),
     * create() and update() alike. Read this before writing a caller; the
     * shape changed on 2026-09-25 and the old flat keys are GONE.
     *
     * PARENT — one period, no status of its own:
     *   id                    string   period uuid
     *   business_unit_id      string
     *   business_unit_name    ?string  null when the relation is missing
     *   name                  string
     *   start_date            ?string  Y-m-d
     *   end_date              ?string  Y-m-d
     *   created_by            ?string  user uuid
     *   updated_by            ?string  user uuid
     *   created_at            ?string  ISO 8601
     *   stations              list     see below, ordered by the master
     *                                  table's sort_order (process order)
     *   station_count         int      count(stations)
     *   closed_station_count  int      how many of them are closed
     *   is_immutable          bool     closed_station_count > 0 — the exact
     *                                  condition update()/delete() refuse
     *                                  on (409 PERIOD_CLOSED_IMMUTABLE), so
     *                                  the screen disables Edit/Delete from
     *                                  this flag instead of re-deriving the
     *                                  rule and getting it wrong
     *   status_summary        string   'empty'  — no station rows at all
     *                                  'draft' | 'open' | 'closed' — every
     *                                           station row has that status
     *                                  'mixed'  — anything else, e.g.
     *                                           Sterilizer closed while
     *                                           Clarification is open
     *
     * EACH ENTRY OF `stations`:
     *   id                 string   period_stations uuid — THIS is the id
     *                               PeriodClosureService::close()/reopen()/
     *                               open() and unverifiedCount() take. Do
     *                               not pass the period id to them.
     *   station_type       string   station_types.code, e.g. 'sterilizer'
     *   station_type_label string   master `name`, falling back to the code
     *   status             string   draft | open | closed
     *   closed_by          ?string  user uuid, set only when closed
     *   closed_by_name     ?string
     *   closed_at          ?string  ISO 8601, set only when closed
     *
     * DELIBERATELY ABSENT AT THE PARENT LEVEL: `status`, `station_type`,
     * `station_type_label`, `closed_by`, `closed_by_name`, `closed_at`.
     * They are plural now — one set per station — and there is no honest
     * single value for them. A parent key named `status` in particular
     * would invite `$row['status'] === 'closed'` to live on, reading as if
     * it still answered "is this period closed": exactly the silent-false
     * bug class Period::guardMovedAttribute() and PeriodQueryBuilder were
     * added to make noisy. `status_summary` is named so that nobody
     * mistakes it for the thing that was removed.
     */
    protected function toRow(Period $period): array
    {
        $stations = $period->stations
            ->sortBy(fn (PeriodStation $station) => $this->stationTypeSortOrder($station->station_type))
            ->values()
            ->map(fn (PeriodStation $station) => [
                'id' => $station->id,
                'station_type' => $station->station_type,
                'station_type_label' => $this->stationTypeLabel($station->station_type),
                'status' => $this->stationStatusValue($station),
                'closed_by' => $station->closed_by,
                'closed_by_name' => optional($station->closedBy)->name,
                'closed_at' => optional($station->closed_at)->toIso8601String(),
            ])
            ->all();

        $statuses = array_column($stations, 'status');
        $closedCount = count(array_filter($statuses, fn (?string $status) => $status === PeriodStatus::Closed->value));

        return [
            'id' => $period->id,
            'business_unit_id' => $period->business_unit_id,
            'business_unit_name' => optional($period->businessUnit)->name,
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'created_by' => $period->created_by,
            'updated_by' => $period->updated_by,
            'created_at' => optional($period->created_at)->toIso8601String(),
            'stations' => $stations,
            'station_count' => count($stations),
            'closed_station_count' => $closedCount,
            'is_immutable' => $closedCount > 0,
            'status_summary' => $this->statusSummary($statuses),
        ];
    }

    /**
     * 'empty' | 'draft' | 'open' | 'closed' | 'mixed' — see toRow()'s
     * contract. A single badge for a list row; never a substitute for the
     * per-station statuses when deciding what an action may do.
     *
     * @param  list<string|null>  $statuses
     */
    protected function statusSummary(array $statuses): string
    {
        if ($statuses === []) {
            return 'empty';
        }

        $distinct = array_values(array_unique($statuses));

        return count($distinct) === 1 ? (string) $distinct[0] : 'mixed';
    }

    /**
     * station_type_label — the master table's `name` joined by code, with
     * the code itself as the fallback for a type no longer in the master.
     * Never derived from App\Enums\StationType: the label a mill sees is
     * data, editable in `station_types` without a code change.
     *
     * The null argument (and with it the 'Semua Stasiun' constant this
     * class used to expose) is GONE — `period_stations.station_type` is NOT
     * NULL and the all-stations scope is expressed by having one row per
     * station type, not by a row without one.
     */
    public function stationTypeLabel(string $code): string
    {
        return $this->stationTypeMeta()[$code]['name'] ?? $code;
    }

    /**
     * Master process order for a code; an unknown code sorts last rather
     * than first, so a retired type never jumps to the top of the list.
     */
    protected function stationTypeSortOrder(string $code): int
    {
        return $this->stationTypeMeta()[$code]['sort_order'] ?? PHP_INT_MAX;
    }

    /**
     * @return array<string, array{name: string, sort_order: int}>
     */
    protected function stationTypeMeta(): array
    {
        return $this->stationTypeMeta ??= StationType::query()
            ->get(['code', 'name', 'sort_order'])
            ->mapWithKeys(fn (StationType $stationType) => [
                $stationType->code => [
                    'name' => $stationType->name,
                    'sort_order' => $stationType->sort_order,
                ],
            ])
            ->all();
    }

    /**
     * `period_stations.status` is cast to PeriodStatus by the model, but a
     * row built from a raw array (or partially hydrated) can still hold the
     * plain string — normalise both to the string value.
     */
    protected function stationStatusValue(PeriodStation $station): ?string
    {
        return $station->status instanceof PeriodStatus
            ? $station->status->value
            : $station->status;
    }

    protected function formatDate(mixed $date): string
    {
        if ($date === null) {
            return '-';
        }

        return $date instanceof Carbon
            ? $date->format('d/m/Y')
            : Carbon::parse((string) $date)->format('d/m/Y');
    }

    /**
     * Normalises an empty-string input to null — mirrors
     * ProductionLineService::emptyToNull()'s identical helper, so a Livewire
     * form binding an untouched field to '' reaches the required-rules as a
     * missing value rather than as a present empty one.
     */
    protected function emptyToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
