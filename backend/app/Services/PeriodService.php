<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodOverlapException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\StationType;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
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
 * (usecase-128). Closing and reopening a period (usecase-140) lives in
 * PeriodClosureService: it is a different use case with its own
 * concurrency contract (a conditional UPDATE), and keeping it separate
 * stops the CRUD path from having to know anything about the 18 station
 * record tables.
 *
 * SCOPE OF A PERIOD is (business_unit_id, station_type) — a mill plus a
 * kind of station, NOT a Production Line. `station_type` NULL means the
 * period covers EVERY station type in that mill, which is why both the
 * name-uniqueness check and the overlap check treat NULL as a wildcard in
 * both directions (see findOverlapping()).
 *
 * STATION TYPE IS MASTER DATA (2026-09-22): `station_type` is validated
 * with Rule::exists('station_types', 'code'), never against
 * App\Enums\StationType — exactly as StationService::validate() does for
 * `stations.type`. Adding a station type is an INSERT into
 * `station_types`, so hardcoding the enum here would leave this rule stale
 * the moment a type is added without a code change. The enum is still the
 * right tool when logic names ONE specific type; this screen never does.
 */
class PeriodService
{
    /** Label shown for a period whose station_type is NULL. */
    public const ALL_STATION_TYPES_LABEL = 'Semua Stasiun';

    /**
     * code => name, lazily read from the `station_types` master table and
     * memoised for the lifetime of this service instance (one request /
     * one Livewire render — both the controller and the component resolve
     * a fresh instance from the container, so this never goes stale).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    /**
     * listPeriods() — business_logic step "list": paginate, eager-load
     * businessUnit + closedBy, optional business_unit_id and status
     * filters, newest period first (order by start_date desc), per_page
     * clamped to 100 by the shared Pagination helper's MAX_PER_PAGE.
     */
    public function listPeriods(
        int $page,
        int $perPage,
        ?string $businessUnitId = null,
        ?string $status = null
    ): array {
        $query = Period::query()
            ->with(['businessUnit', 'closedBy'])
            ->orderByDesc('start_date')
            ->orderBy('name');

        if ($businessUnitId !== null && $businessUnitId !== '') {
            $query->where('business_unit_id', $businessUnitId);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
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
     * stationTypeOptions() — feeds the optional "Jenis Stasiun" select.
     * Read from the `station_types` master table (active rows, process
     * order), NOT from App\Enums\StationType — the caller prepends its own
     * empty "Semua Stasiun" option for the NULL scope.
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
     * exists, name required + unique per scope, station_type in the
     * station_types master or null, end_date >= start_date) → 422 → check
     * range overlap on the same scope → 422 PERIOD_OVERLAP naming the
     * conflicting period → INSERT with status='draft' and
     * created_by=actor.
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
            $attributes['station_type'],
            $attributes['start_date'],
            $attributes['end_date'],
            null
        );

        $attributes['status'] = PeriodStatus::Draft->value;
        $attributes['created_by'] = auth()->id();

        $period = Period::create($attributes);
        $period->load(['businessUnit', 'closedBy']);

        return $this->toRow($period);
    }

    /**
     * update() — business_logic steps "update": findOrFail → 404 → refuse
     * with 409 PERIOD_CLOSED_IMMUTABLE when the period is already closed
     * (BEFORE anything is written, so a rejected attempt leaves the row
     * bit-for-bit unchanged) → same validation as create() with the
     * uniqueness and overlap checks excluding this row → UPDATE with
     * updated_by=actor.
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

        if ($this->statusValue($period) === PeriodStatus::Closed->value) {
            throw new PeriodClosedImmutableException;
        }

        $attributes = $this->validate($data, $period->id);

        $this->guardAgainstOverlap(
            $attributes['business_unit_id'],
            $attributes['station_type'],
            $attributes['start_date'],
            $attributes['end_date'],
            $period->id
        );

        $attributes['updated_by'] = auth()->id();

        $period->update($attributes);
        $period->load(['businessUnit', 'closedBy']);

        return $this->toRow($period);
    }

    /**
     * delete() — business_logic step "delete": findOrFail → 404 → refuse
     * with 409 PERIOD_CLOSED_IMMUTABLE when the period is closed (nothing
     * is deleted) → else DELETE.
     *
     * @throws ModelNotFoundException
     * @throws PeriodClosedImmutableException
     */
    public function delete(string $id): void
    {
        $period = Period::findOrFail($id);

        if ($this->statusValue($period) === PeriodStatus::Closed->value) {
            throw new PeriodClosedImmutableException;
        }

        $period->delete();
    }

    /**
     * findOverlapping() — the single SQL clause behind every PERIOD_OVERLAP
     * decision, for BOTH create() and update():
     *
     *   WHERE business_unit_id = ?
     *     AND (station_type = ? OR station_type IS NULL OR ? IS NULL)
     *     AND start_date <= :new_end
     *     AND end_date   >= :new_start
     *
     * Two things are deliberate here:
     *
     * 1. BOUNDS ARE INCLUSIVE — a period ending exactly on another's start
     *    date DOES overlap (they share that day, and a record dated that
     *    day would belong to both).
     * 2. NULL IS A WILDCARD IN BOTH DIRECTIONS — an existing all-types
     *    period blocks a typed one, and a new all-types period is blocked
     *    by any typed one in the same mill. When $stationType is null no
     *    station_type predicate is applied at all (that is the `? IS NULL`
     *    arm); when it is set, the predicate matches its own type OR the
     *    all-types rows.
     *
     * $excludeId keeps a period from colliding with itself on update.
     */
    public function findOverlapping(
        string $businessUnitId,
        ?string $stationType,
        string $startDate,
        string $endDate,
        ?string $excludeId = null
    ): ?Period {
        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->when($stationType !== null, fn ($query) => $query->where(
                fn ($scope) => $scope
                    ->where('station_type', $stationType)
                    ->orWhereNull('station_type')
            ))
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
        ?string $stationType,
        string $startDate,
        string $endDate,
        ?string $excludeId
    ): void {
        $conflict = $this->findOverlapping($businessUnitId, $stationType, $startDate, $endDate, $excludeId);

        if ($conflict === null) {
            return;
        }

        throw new PeriodOverlapException(sprintf(
            'Rentang tanggal beririsan dengan periode "%s" (%s s/d %s) pada mill dan jenis stasiun yang sama. '
            .'Ubah rentang tanggal atau buka kembali periode tersebut terlebih dahulu.',
            $conflict->name,
            $this->formatDate($conflict->start_date),
            $this->formatDate($conflict->end_date)
        ));
    }

    /**
     * Validates business_unit_id, station_type, name, start_date and
     * end_date in one Validator pass — produces shared_decisions.
     * error_format's `{ message, errors: { field: [...] } }` shape.
     *
     * Name uniqueness is scoped to (business_unit_id, station_type), with
     * the NULL scope matched via whereNull rather than `= null`: the DB's
     * own UNIQUE index cannot enforce it there (PostgreSQL treats every
     * NULL as distinct — see the create_periods_table migration's note),
     * so this rule IS the enforcement for all-types periods.
     *
     * @param  array<string, mixed>  $data
     * @return array{business_unit_id: string, station_type: string|null, name: string, start_date: string, end_date: string}
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $businessUnitId = $this->emptyToNull($data['business_unit_id'] ?? null);
        $stationType = $this->emptyToNull($data['station_type'] ?? null);

        $nameUniqueRule = Rule::unique('periods', 'name')
            ->where(function ($query) use ($businessUnitId, $stationType) {
                $query->where('business_unit_id', $businessUnitId);

                return $stationType === null
                    ? $query->whereNull('station_type')
                    : $query->where('station_type', $stationType);
            });

        if ($excludeId !== null) {
            $nameUniqueRule = $nameUniqueRule->ignore($excludeId);
        }

        $payload = [
            'business_unit_id' => $businessUnitId,
            'station_type' => $stationType,
            'name' => $this->emptyToNull($data['name'] ?? null),
            'start_date' => $this->emptyToNull($data['start_date'] ?? null),
            'end_date' => $this->emptyToNull($data['end_date'] ?? null),
        ];

        $rules = [
            'business_unit_id' => ['required', 'string', Rule::exists('business_units', 'id')],
            // `nullable` MUST come before the exists rule: a null
            // station_type is the "covers every station type in this mill"
            // scope, not a missing value. Validated against the
            // station_types master table for the same reason
            // StationService::validate() does — see this class's docblock.
            'station_type' => ['nullable', 'string', Rule::exists('station_types', 'code')],
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];

        $messages = [
            'business_unit_id.required' => 'Business Unit wajib dipilih.',
            'business_unit_id.exists' => 'Business Unit yang dipilih tidak ditemukan.',
            'station_type.exists' => 'Jenis stasiun yang dipilih tidak ditemukan.',
            'name.required' => 'Nama Periode wajib diisi.',
            'name.max' => 'Nama Periode maksimal 255 karakter.',
            'name.unique' => 'Nama Periode sudah digunakan pada Business Unit dan jenis stasiun ini.',
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
        $validated['station_type'] = $validated['station_type'] ?? null;

        return $validated;
    }

    /**
     * Maps a Period (with businessUnit + closedBy eager-loaded) to the
     * shared row shape returned by list/create/update.
     */
    protected function toRow(Period $period): array
    {
        return [
            'id' => $period->id,
            'business_unit_id' => $period->business_unit_id,
            'business_unit_name' => optional($period->businessUnit)->name,
            'station_type' => $period->station_type,
            'station_type_label' => $this->stationTypeLabel($period->station_type),
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'status' => $this->statusValue($period),
            'closed_by' => $period->closed_by,
            'closed_by_name' => optional($period->closedBy)->name,
            'closed_at' => optional($period->closed_at)->toIso8601String(),
            'created_by' => $period->created_by,
            'updated_by' => $period->updated_by,
            'created_at' => optional($period->created_at)->toIso8601String(),
        ];
    }

    /**
     * station_type_label — the master table's `name` joined by code, or
     * "Semua Stasiun" for the NULL scope. Never derived from
     * App\Enums\StationType: the label a mill sees is data, editable in
     * `station_types` without a code change.
     */
    public function stationTypeLabel(?string $code): string
    {
        if ($code === null) {
            return self::ALL_STATION_TYPES_LABEL;
        }

        return $this->stationTypeNames()[$code] ?? $code;
    }

    /**
     * @return array<string, string>
     */
    protected function stationTypeNames(): array
    {
        return $this->stationTypeNames ??= StationType::query()
            ->pluck('name', 'code')
            ->all();
    }

    /**
     * `status` is cast to PeriodStatus by the model, but a Period built
     * from a raw array (or a partially hydrated row) can still hold the
     * plain string — normalise both to the string value.
     */
    protected function statusValue(Period $period): ?string
    {
        return $period->status instanceof PeriodStatus
            ? $period->status->value
            : $period->status;
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
     * ProductionLineService::emptyToNull()'s identical helper. Essential
     * here: the Livewire form binds an unselected "Jenis Stasiun" to '',
     * which must reach the service as the NULL all-types scope.
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
