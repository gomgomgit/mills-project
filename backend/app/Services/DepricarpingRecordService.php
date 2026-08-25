<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveDepricarpingStationException;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
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
 * DepricarpingRecordService — screen-051--data-browser-depricarping-web /
 * screen-055--detail-depricarping-web / screen-059--form-depricarping-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * DepricarpingRecordController) and the Livewire components (App\Livewire\
 * Data\DataBrowserDepricarping / DetailDepricarping / FormDepricarping), so
 * filtering/pagination/export/create/update rules stay identical between
 * entry points — mirrors PressingRecordService's/ThreshingRecordService's
 * pattern exactly.
 *
 * REVISED 2026-08-24 (entity-catalog v12): depricarping_detail is now a
 * DYNAMIC add-row/remove-row grid — the user explicitly rejected the
 * original FIXED 24-row design (all 24 canonical time-slot rows always
 * inserted at once, no add/remove concept at all) as wasting screen space.
 * This now mirrors PressingRecordService's `pressing_detail`
 * upsert/delete pattern exactly: `details` may contain any number of rows
 * (1..24), each with a unique `time_slot` in strictly ascending canonical
 * order (validateDetails()); create() INSERTs exactly the given rows (no
 * longer all 24); update() upserts (insertDetails()/updateDetails() are
 * replaced by a single `upsertDetails()` — rows with an existing `id` kept
 * in `$details` are UPDATEd, rows without one are INSERTed, and any
 * existing row whose id is no longer present in `$details` is DELETEd).
 * `canonicalTimeSlots()` is KEPT — no longer used to force exactly 24 rows,
 * but still the source of truth for "which slots exist, in what order" for
 * both `validateDetails()`'s ascending-order check and `toDetailRow()`'s
 * sort order.
 *
 * STRUCTURAL DIFFERENCE FROM PRESSING/THRESHING: those two stations have a
 * single free-text "Downtime Reason" reading column. Depricarping's own
 * source log sheet instead has TWO separate columns —
 * `downtime_minutes` (integer duration) and `findings` (free text) — kept
 * as two distinct fields end-to-end (mobile repo, this service, the
 * migration/model), never merged, and unaffected by this dynamic-row
 * revision (a row counts as filled when EITHER is set, same as any other
 * reading column).
 *
 * "Filled" row (filled_slot_count / minimum-one-row validation): a
 * depricarping_detail row counts as filled when at least one of its 8
 * reading columns (fan_static_pressure_mmh2o, polishing_drum_speed_rpm,
 * air_velocity_ms, fibre_moisture_percent, kernel_recovery_in_fibre_percent,
 * nut_silo_1_temp_c, nut_silo_2_temp_c, downtime_minutes) OR `findings` is
 * non-null/non-empty. The model's own `saving` guard
 * (DepricarpingRecord::booted(), "minimal satu depricarping-detail sebelum
 * status=saved") checks ROW EXISTENCE, satisfied once at least one row has
 * been added — the real "minimal satu baris terisi" business rule is
 * enforced by THIS service's validateDetails(), not by that guard.
 */
class DepricarpingRecordService
{
    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['presser_id', 'date', 'note'];

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * depricarpingRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
     * 0..23 -> hour = (7 + i) % 24`), and PressingRecordService's/
     * ThreshingRecordService's identical helper, so web and mobile always
     * agree on row order/coverage.
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
     * create() — screen-059--form-depricarping-web business_logic steps
     * 1-3: validate header + details (at least one valid row, unique +
     * strictly ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its
     * depricarping_detail rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'depricarping')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveDepricarpingStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            // DepricarpingRecord::booted()'s `saving` guard rejects
            // status=saved on a brand-new record with zero
            // DepricarpingDetail rows — and at this point none exist yet
            // (inserted by upsertDetails() right after). Create as Synced
            // (same guard-satisfying placeholder pattern
            // PressingRecordService::create() uses), insert the details,
            // THEN flip status to saved.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = DepricarpingRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'depricarpingDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-059--form-depricarping-web business_logic steps
     * 5-7: validate header + details (at least one valid row, unique +
     * strictly ascending canonical time_slot order), UPDATE the record,
     * then upsert its depricarping_detail rows (insert rows without an id,
     * update rows whose id still appears in $details, delete any existing
     * row whose id is no longer present) inside a DB transaction — mirrors
     * PressingRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = DepricarpingRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'depricarpingDetails']);

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
     * @return array<int, array{id: ?string, time_slot: ?string, fan_static_pressure_mmh2o: mixed, polishing_drum_speed_rpm: mixed, air_velocity_ms: mixed, fibre_moisture_percent: mixed, kernel_recovery_in_fibre_percent: mixed, nut_silo_1_temp_c: mixed, nut_silo_2_temp_c: mixed, downtime_minutes: mixed, findings: mixed}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'time_slot' => $row['time_slot'] ?? null,
                'fan_static_pressure_mmh2o' => $row['fan_static_pressure_mmh2o'] ?? null,
                'polishing_drum_speed_rpm' => $row['polishing_drum_speed_rpm'] ?? null,
                'air_velocity_ms' => $row['air_velocity_ms'] ?? null,
                'fibre_moisture_percent' => $row['fibre_moisture_percent'] ?? null,
                'kernel_recovery_in_fibre_percent' => $row['kernel_recovery_in_fibre_percent'] ?? null,
                'nut_silo_1_temp_c' => $row['nut_silo_1_temp_c'] ?? null,
                'nut_silo_2_temp_c' => $row['nut_silo_2_temp_c'] ?? null,
                'downtime_minutes' => $row['downtime_minutes'] ?? null,
                'findings' => $row['findings'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'presser_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'presser_id.required' => 'Presser ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (a `time_slot` from the 24 canonical slots AND at least one of
     * its 8 reading columns non-null) must exist, and every row's
     * `time_slot` must be strictly ascending (canonical order) and unique
     * across the array — mirrors PressingRecordService::validateDetails()
     * exactly, adapted to Depricarping's own 8 reading columns.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Depricarping Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Depricarping Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Depricarping Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Depricarping Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * @param  array{fan_static_pressure_mmh2o: mixed, polishing_drum_speed_rpm: mixed, air_velocity_ms: mixed, fibre_moisture_percent: mixed, kernel_recovery_in_fibre_percent: mixed, nut_silo_1_temp_c: mixed, nut_silo_2_temp_c: mixed, downtime_minutes: mixed, findings: mixed}  $row
     */
    protected function isRowFilled(array $row): bool
    {
        return $row['fan_static_pressure_mmh2o'] !== null
            || $row['polishing_drum_speed_rpm'] !== null
            || $row['air_velocity_ms'] !== null
            || $row['fibre_moisture_percent'] !== null
            || $row['kernel_recovery_in_fibre_percent'] !== null
            || $row['nut_silo_1_temp_c'] !== null
            || $row['nut_silo_2_temp_c'] !== null
            || $row['downtime_minutes'] !== null
            || ($row['findings'] !== null && $row['findings'] !== '');
    }

    /**
     * upsertDetails() — business_logic steps 3 + 7: for each valid detail
     * row (a selected `time_slot` AND at least one reading column filled —
     * same filter as validateDetails()), insert rows without an id, update
     * rows whose id still appears in $details, and delete any existing row
     * whose id is no longer present — mirrors
     * PressingRecordService::upsertDetails() exactly.
     */
    protected function upsertDetails(DepricarpingRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        $keptIds = [];

        foreach ($validRows as $row) {
            $detailAttributes = [
                'depricarping_record_id' => $record->id,
                'time_slot' => $row['time_slot'],
                'fan_static_pressure_mmh2o' => $row['fan_static_pressure_mmh2o'],
                'polishing_drum_speed_rpm' => $row['polishing_drum_speed_rpm'],
                'air_velocity_ms' => $row['air_velocity_ms'],
                'fibre_moisture_percent' => $row['fibre_moisture_percent'],
                'kernel_recovery_in_fibre_percent' => $row['kernel_recovery_in_fibre_percent'],
                'nut_silo_1_temp_c' => $row['nut_silo_1_temp_c'],
                'nut_silo_2_temp_c' => $row['nut_silo_2_temp_c'],
                'downtime_minutes' => $row['downtime_minutes'],
                'findings' => $row['findings'],
            ];

            if (! empty($row['id']) && DepricarpingDetail::where('id', $row['id'])->where('depricarping_record_id', $record->id)->exists()) {
                DepricarpingDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = DepricarpingDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        DepricarpingDetail::where('depricarping_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * PressingRecordService::applyVerification() exactly.
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
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query (with filled_slot_count computed via
     * withCount()), paginate, return {data, meta}.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount(['depricarpingDetails as filled_slot_count' => function (Builder $detailQuery) {
                $detailQuery->where(function (Builder $q) {
                    $q->whereNotNull('fan_static_pressure_mmh2o')
                        ->orWhereNotNull('polishing_drum_speed_rpm')
                        ->orWhereNotNull('air_velocity_ms')
                        ->orWhereNotNull('fibre_moisture_percent')
                        ->orWhereNotNull('kernel_recovery_in_fibre_percent')
                        ->orWhereNotNull('nut_silo_1_temp_c')
                        ->orWhereNotNull('nut_silo_2_temp_c')
                        ->orWhereNotNull('downtime_minutes')
                        ->orWhereNotNull('findings');
                });
            }])
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (DepricarpingRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic steps 5-6: re-run the filter query
     * (unpaginated), enforce the row limit, generate a CSV body.
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
                    'Presser ID',
                    'Date',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var DepricarpingRecord $record */
                    fputcsv($handle, [
                        $record->presser_id,
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
                "depricarping-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "depricarping-records_{$timestamp}.csv",
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

        $query = DepricarpingRecord::query();

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
     * Maps a DepricarpingRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(DepricarpingRecord $record): array
    {
        return [
            'id' => $record->id,
            'presser_id' => $record->presser_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-055--detail-depricarping-web business_logic
     * steps 1-4: findOrFail (404 via ModelNotFoundException) then resolve
     * station/createdBy/checkedBy/acknowledgedBy to display names and the
     * depricarpingDetails grid, sorted into canonical time-slot order (not
     * alphabetical — '00:00' would otherwise sort before '07:00').
     */
    public function getDetail(string $id): array
    {
        $record = DepricarpingRecord::with([
            'station',
            'createdBy',
            'checkedBy',
            'acknowledgedBy',
            'depricarpingDetails',
        ])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a DepricarpingRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(DepricarpingRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->depricarpingDetails
            ->sortBy(fn (DepricarpingDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(fn (DepricarpingDetail $row) => [
                'id' => $row->id,
                'time_slot' => $row->time_slot,
                'fan_static_pressure_mmh2o' => $row->fan_static_pressure_mmh2o,
                'polishing_drum_speed_rpm' => $row->polishing_drum_speed_rpm,
                'air_velocity_ms' => $row->air_velocity_ms,
                'fibre_moisture_percent' => $row->fibre_moisture_percent,
                'kernel_recovery_in_fibre_percent' => $row->kernel_recovery_in_fibre_percent,
                'nut_silo_1_temp_c' => $row->nut_silo_1_temp_c,
                'nut_silo_2_temp_c' => $row->nut_silo_2_temp_c,
                'downtime_minutes' => $row->downtime_minutes,
                'findings' => $row->findings,
            ])
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'presser_id' => $record->presser_id,
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
