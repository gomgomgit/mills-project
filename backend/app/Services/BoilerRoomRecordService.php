<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveBoilerRoomStationException;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\Station;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * BoilerRoomRecordService — screen-098--data-browser-boiler-room-web /
 * screen-108--detail-boiler-room-web / screen-118--form-boiler-room-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * BoilerRoomRecordController) and the Livewire components (App\Livewire\
 * Data\{DataBrowserBoilerRoom,DetailBoilerRoom,FormBoilerRoom}), mirroring
 * EngineRoomRecordService's structure exactly — Boiler Room follows the
 * same "hourly grid" pattern as Engine Room/Storage Tank/Effluent Plant/
 * Threshing/Pressing/Depricarping/Kernel Plant: `details` may contain any
 * number of rows (1..24), each with a unique `time_slot` in strictly
 * ascending canonical order (validateDetails()); create() INSERTs exactly
 * the given rows; update() upserts (rows with an existing `id` kept in
 * `$details` are UPDATEd, rows without one are INSERTed, and any existing
 * row whose id is no longer present in `$details` is DELETEd).
 *
 * UNLIKE Threshing/Pressing/Depricarping/Kernel Plant: Boiler Room has NO
 * operational-target reference table (no seeder/model, no Target
 * Operasional section on any of its 6 screens) — same deliberate scope
 * difference as Engine Room/Storage Tank/Effluent Plant.
 *
 * "Filled" row (filled_slot_count / minimum-one-row validation): a
 * boiler_room_detail row counts as filled when at least one of its 15
 * non-time_slot columns is non-null — like Engine Room, this station has
 * NO identifying/context columns (no shift/inspector_id equivalent), so
 * all 15 remaining columns are reading/status/text columns and all
 * participate in the "filled" check.
 *
 * fuel_feed_rate/id_fan_load/sa_fan_load are free-text string columns (not
 * numeric) because the paper-form units are mixed/ambiguous (Hz/%/tons,
 * A/%) per entity-catalog's actual field type — not coerced to float.
 *
 * IMPORTANT — enum empty-string-vs-null: 2 of the 15 columns
 * (blowdown_executed, sootblowing_executed) are backed by a SQLite CHECK
 * constraint (Laravel enum('y','n')) that rejects an empty string.
 * normalizeDetails() coerces '' to null for these columns specifically
 * before validation/persistence — the same mitigation Engine Room/Kernel
 * Dispatch/Effluent Plant/Storage Tank needed for their own enum columns.
 */
class BoilerRoomRecordService
{
    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['boiler_room_id', 'date', 'note'];

    protected const ENUM_FIELDS = ['blowdown_executed', 'sootblowing_executed'];

    protected const READING_FIELDS = [
        'steam_pressure_bar', 'steam_temp_c', 'feed_water_temp_c',
        'feed_water_tank_level_percent', 'boiler_water_level_percent', 'water_tds_ppm',
        'water_ph', 'fuel_feed_rate', 'id_fan_load',
        'sa_fan_load', 'exhaust_gas_temp_c', 'dust_collector_differential_pressure_mmh2o',
        'blowdown_executed', 'sootblowing_executed', 'findings',
    ];

    protected const DETAIL_FIELDS = self::READING_FIELDS;

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * boilerRoomRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
     * 0..23 -> hour = (7 + i) % 24`), so web and mobile always agree on
     * row order/coverage.
     *
     * @return array<int, string>
     */
    public static function canonicalTimeSlots(): array
    {
        return collect(range(0, 23))
            ->map(fn (int $i) => sprintf('%02d:00', (7 + $i) % 24))
            ->all();
    }

    /**
     * create() — validate header + details (at least one valid row, unique
     * + strictly ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its
     * boiler_room_detail rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'boiler-room')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveBoilerRoomStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            // BoilerRoomRecord may carry the same "minimal satu detail row
            // sebelum status=saved" constraint shape as ThreshingRecord (see
            // that model's own doc comment) — create as Synced (same
            // guard-satisfying placeholder pattern) before any detail rows
            // exist, insert the details, THEN flip status to saved.
            // Harmless no-op if no such guard exists on this model.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = BoilerRoomRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'boilerRoomDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — validate header + details (at least one valid row, unique
     * + strictly ascending canonical time_slot order), UPDATE the record,
     * then upsert its boiler_room_detail rows (insert rows without an id,
     * update rows whose id still appears in $details, delete any existing
     * row whose id is no longer present) inside a DB transaction — mirrors
     * EngineRoomRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = BoilerRoomRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'boilerRoomDetails']);

        return $this->toDetailRow($record);
    }

    protected function normalizeFormFields(array $data): array
    {
        $attributes = [];

        foreach (self::FORM_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        return $attributes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(function ($row) {
                $normalized = [
                    'id' => $row['id'] ?? null,
                    'time_slot' => $row['time_slot'] ?? null,
                ];

                foreach (self::DETAIL_FIELDS as $field) {
                    $value = $row[$field] ?? null;

                    // Empty-string-to-null coercion for the enum columns —
                    // SQLite's CHECK constraint (Laravel enum()) rejects '',
                    // so an empty dropdown selection must become null, not
                    // ''. Same fix Engine Room/Kernel Dispatch/Effluent
                    // Plant/Storage Tank needed for their own enum columns.
                    if (in_array($field, self::ENUM_FIELDS, true) && $value === '') {
                        $value = null;
                    }

                    $normalized[$field] = $value;
                }

                return $normalized;
            })
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'boiler_room_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'boiler_room_id.required' => 'Boiler Room ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — at least one valid detail row (a `time_slot` from
     * the 24 canonical slots AND at least one of its reading columns
     * non-null) must exist, and every row's `time_slot` must be strictly
     * ascending (canonical order) and unique across the array — mirrors
     * EngineRoomRecordService::validateDetails() exactly.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Boiler Room Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Boiler Room Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Boiler Room Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Boiler Room Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * A row counts as "filled" when at least one of its READING_FIELDS (all
     * 15 non-time_slot columns — this station has no identifying/context
     * columns to exclude) is non-null/non-empty.
     */
    protected function isRowFilled(array $row): bool
    {
        foreach (self::READING_FIELDS as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * upsertDetails() — for each valid detail row (a selected `time_slot`
     * AND at least one reading column filled — same filter as
     * validateDetails()), insert rows without an id, update rows whose id
     * still appears in $details, and delete any existing row whose id is
     * no longer present — mirrors EngineRoomRecordService::upsertDetails()
     * exactly.
     */
    protected function upsertDetails(BoilerRoomRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        $keptIds = [];

        foreach ($validRows as $row) {
            $detailAttributes = ['boiler_room_record_id' => $record->id, 'time_slot' => $row['time_slot']];

            foreach (self::DETAIL_FIELDS as $field) {
                $detailAttributes[$field] = $row[$field];
            }

            if (! empty($row['id']) && BoilerRoomDetail::where('id', $row['id'])->where('boiler_room_record_id', $record->id)->exists()) {
                BoilerRoomDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = BoilerRoomDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        BoilerRoomDetail::where('boiler_room_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * EngineRoomRecordService::applyVerification() exactly.
     */
    protected function applyVerification(array &$attributes, array $data, User $actor): void
    {
        if ($actor->role === UserRole::Supervisor) {
            $attributes['checked_by'] = ! empty($data['checked']) ? $actor->id : null;
        }

        if ($actor->role === UserRole::MillManagement) {
            $attributes['acknowledged_by'] = ! empty($data['acknowledged']) ? $actor->id : null;
        }
    }

    /**
     * listRecords() — validate the date range, build the filtered query
     * (with filled_slot_count computed via withCount()), paginate, return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $readingFields = self::READING_FIELDS;

        $query = $this->buildFilteredQuery($filters)
            ->withCount(['boilerRoomDetails as filled_slot_count' => function (Builder $detailQuery) use ($readingFields) {
                $detailQuery->where(function (Builder $q) use ($readingFields) {
                    foreach ($readingFields as $field) {
                        $q->orWhereNotNull($field);
                    }
                });
            }])
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (BoilerRoomRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — re-run the filter query (unpaginated), enforce the row
     * limit, generate a CSV body.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $query = $this->buildFilteredQuery($filters)
            ->with(['checkedBy:id,name', 'acknowledgedBy:id,name'])
            ->orderByDesc('date');

        $total = $query->count();

        if ($total > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException();
        }

        try {
            $records = $query->get();

            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($records) {
                $handle = fopen('php://output', 'w');

                fputcsv($handle, [
                    'Boiler Room ID',
                    'Date',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var BoilerRoomRecord $record */
                    fputcsv($handle, [
                        $record->boiler_room_id,
                        optional($record->date)->toDateString(),
                        $record->checkedBy?->name,
                        $record->acknowledgedBy?->name,
                        $record->status?->value,
                    ], ',', '"', '\\');
                }

                fclose($handle);
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "boiler-room-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "boiler-room-records_{$timestamp}.csv",
        ];
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $businessUnitId = $filters['business_unit_id'] ?? null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw new InvalidDateRangeException();
        }

        $query = BoilerRoomRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Maps a BoilerRoomRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(BoilerRoomRecord $record): array
    {
        return [
            'id' => $record->id,
            'boiler_room_id' => $record->boiler_room_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — findOrFail (404 via ModelNotFoundException) then
     * resolve station/createdBy/checkedBy/acknowledgedBy to display names
     * and the boilerRoomDetails grid, sorted into canonical time-slot
     * order (not alphabetical — '00:00' would otherwise sort before
     * '07:00').
     */
    public function getDetail(string $id): array
    {
        $record = BoilerRoomRecord::with([
            'station',
            'createdBy',
            'checkedBy',
            'acknowledgedBy',
            'boilerRoomDetails',
        ])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a BoilerRoomRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(BoilerRoomRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->boilerRoomDetails
            ->sortBy(fn (BoilerRoomDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(function (BoilerRoomDetail $row) {
                $mapped = ['id' => $row->id, 'time_slot' => $row->time_slot];

                foreach (self::DETAIL_FIELDS as $field) {
                    $mapped[$field] = $row->{$field};
                }

                return $mapped;
            })
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'boiler_room_id' => $record->boiler_room_id,
            'date' => optional($record->date)->toDateString(),
            'note' => $record->note,
            'created_by_name' => $record->createdBy?->name,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'details' => $details,
        ];
    }
}
