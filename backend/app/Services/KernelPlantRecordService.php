<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveKernelPlantStationException;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
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
 * KernelPlantRecordService — screen-052--data-browser-kernel-plant-web /
 * screen-056--detail-kernel-plant-web / screen-060--form-kernel-plant-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * KernelPlantRecordController) and the Livewire components (App\Livewire\
 * Data\DataBrowserKernelPlant / DetailKernelPlant / FormKernelPlant), so
 * filtering/pagination/export/create/update rules stay identical between
 * entry points — mirrors DepricarpingRecordService's pattern exactly.
 *
 * REVISED 2026-08-24 (entity-catalog v12): kernel_plant_detail is now a
 * DYNAMIC add-row/remove-row grid — the user explicitly rejected the
 * original FIXED 24-row design (all 24 canonical time-slot rows always
 * inserted at once, no add/remove concept at all) as wasting screen space.
 * This now mirrors DepricarpingRecordService's `depricarping_detail`
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
 * STRUCTURAL SHAPE MATCHES DEPRICARPING (not Threshing/Pressing): Kernel
 * Plant's own source log sheet has TWO separate downtime-related columns —
 * `downtime_minutes` (integer duration) and `findings` (free text) — kept
 * as two distinct fields end-to-end (mobile repo, this service, the
 * migration/model), never merged, mirroring DepricarpingRecordService
 * exactly. The Target Operasional reference table, however, is 3-column
 * (equipment_parameter/target_benchmark/corrective_action_plan) like
 * Threshing/Pressing, NOT 4-column like Depricarping — see
 * KernelPlantOperationalTarget.
 *
 * "Filled" row (filled_slot_count / minimum-one-row validation): a
 * kernel_plant_detail row counts as filled when at least one of its 9
 * reading columns (ripple_mill_1_amps, ripple_mill_2_amps,
 * claybath_hydro_sg, kernel_silo_1_temp_c, kernel_silo_2_temp_c,
 * kernel_moisture_percent, shell_loss_percent, downtime_minutes) OR
 * `findings` is non-null/non-empty. The model's own `saving` guard
 * (KernelPlantRecord::booted(), "minimal satu kernel-plant-detail sebelum
 * status=saved") checks ROW EXISTENCE, satisfied once at least one row has
 * been added — the real "minimal satu baris terisi" business rule is
 * enforced by THIS service's validateDetails(), not by that guard.
 */
class KernelPlantRecordService
{
    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['kernel_plant_id', 'date', 'note'];

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * kernelPlantRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
     * 0..23 -> hour = (7 + i) % 24`), and DepricarpingRecordService's/
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
     * create() — screen-060--form-kernel-plant-web business_logic steps
     * 1-3: validate header + details (at least one valid row, unique +
     * strictly ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its
     * kernel_plant_detail rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'kernel-plant')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveKernelPlantStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            // KernelPlantRecord::booted()'s `saving` guard rejects
            // status=saved on a brand-new record with zero
            // KernelPlantDetail rows — and at this point none exist yet
            // (inserted by upsertDetails() right after). Create as Synced
            // (same guard-satisfying placeholder pattern
            // DepricarpingRecordService::create() uses), insert the
            // details, THEN flip status to saved.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = KernelPlantRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'kernelPlantDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-060--form-kernel-plant-web business_logic steps
     * 5-7: validate header + details (at least one valid row, unique +
     * strictly ascending canonical time_slot order), UPDATE the record,
     * then upsert its kernel_plant_detail rows (insert rows without an id,
     * update rows whose id still appears in $details, delete any existing
     * row whose id is no longer present) inside a DB transaction — mirrors
     * DepricarpingRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = KernelPlantRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'kernelPlantDetails']);

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
     * @return array<int, array{id: ?string, time_slot: ?string, ripple_mill_1_amps: mixed, ripple_mill_2_amps: mixed, claybath_hydro_sg: mixed, kernel_silo_1_temp_c: mixed, kernel_silo_2_temp_c: mixed, kernel_moisture_percent: mixed, shell_loss_percent: mixed, downtime_minutes: mixed, findings: mixed}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'time_slot' => $row['time_slot'] ?? null,
                'ripple_mill_1_amps' => $row['ripple_mill_1_amps'] ?? null,
                'ripple_mill_2_amps' => $row['ripple_mill_2_amps'] ?? null,
                'claybath_hydro_sg' => $row['claybath_hydro_sg'] ?? null,
                'kernel_silo_1_temp_c' => $row['kernel_silo_1_temp_c'] ?? null,
                'kernel_silo_2_temp_c' => $row['kernel_silo_2_temp_c'] ?? null,
                'kernel_moisture_percent' => $row['kernel_moisture_percent'] ?? null,
                'shell_loss_percent' => $row['shell_loss_percent'] ?? null,
                'downtime_minutes' => $row['downtime_minutes'] ?? null,
                'findings' => $row['findings'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'kernel_plant_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'kernel_plant_id.required' => 'Kernel Plant ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (a `time_slot` from the 24 canonical slots AND at least one of
     * its 9 reading columns non-null) must exist, and every row's
     * `time_slot` must be strictly ascending (canonical order) and unique
     * across the array — mirrors DepricarpingRecordService::validateDetails()
     * exactly, adapted to Kernel Plant's own 9 reading columns.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Kernel Plant Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Kernel Plant Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Kernel Plant Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Kernel Plant Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * @param  array{ripple_mill_1_amps: mixed, ripple_mill_2_amps: mixed, claybath_hydro_sg: mixed, kernel_silo_1_temp_c: mixed, kernel_silo_2_temp_c: mixed, kernel_moisture_percent: mixed, shell_loss_percent: mixed, downtime_minutes: mixed, findings: mixed}  $row
     */
    protected function isRowFilled(array $row): bool
    {
        return $row['ripple_mill_1_amps'] !== null
            || $row['ripple_mill_2_amps'] !== null
            || $row['claybath_hydro_sg'] !== null
            || $row['kernel_silo_1_temp_c'] !== null
            || $row['kernel_silo_2_temp_c'] !== null
            || $row['kernel_moisture_percent'] !== null
            || $row['shell_loss_percent'] !== null
            || $row['downtime_minutes'] !== null
            || ($row['findings'] !== null && $row['findings'] !== '');
    }

    /**
     * upsertDetails() — business_logic steps 3 + 7: for each valid detail
     * row (a selected `time_slot` AND at least one reading column filled —
     * same filter as validateDetails()), insert rows without an id, update
     * rows whose id still appears in $details, and delete any existing row
     * whose id is no longer present — mirrors
     * DepricarpingRecordService::upsertDetails() exactly.
     */
    protected function upsertDetails(KernelPlantRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        $keptIds = [];

        foreach ($validRows as $row) {
            $detailAttributes = [
                'kernel_plant_record_id' => $record->id,
                'time_slot' => $row['time_slot'],
                'ripple_mill_1_amps' => $row['ripple_mill_1_amps'],
                'ripple_mill_2_amps' => $row['ripple_mill_2_amps'],
                'claybath_hydro_sg' => $row['claybath_hydro_sg'],
                'kernel_silo_1_temp_c' => $row['kernel_silo_1_temp_c'],
                'kernel_silo_2_temp_c' => $row['kernel_silo_2_temp_c'],
                'kernel_moisture_percent' => $row['kernel_moisture_percent'],
                'shell_loss_percent' => $row['shell_loss_percent'],
                'downtime_minutes' => $row['downtime_minutes'],
                'findings' => $row['findings'],
            ];

            if (! empty($row['id']) && KernelPlantDetail::where('id', $row['id'])->where('kernel_plant_record_id', $record->id)->exists()) {
                KernelPlantDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = KernelPlantDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        KernelPlantDetail::where('kernel_plant_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * DepricarpingRecordService::applyVerification() exactly.
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
            ->withCount(['kernelPlantDetails as filled_slot_count' => function (Builder $detailQuery) {
                $detailQuery->where(function (Builder $q) {
                    $q->whereNotNull('ripple_mill_1_amps')
                        ->orWhereNotNull('ripple_mill_2_amps')
                        ->orWhereNotNull('claybath_hydro_sg')
                        ->orWhereNotNull('kernel_silo_1_temp_c')
                        ->orWhereNotNull('kernel_silo_2_temp_c')
                        ->orWhereNotNull('kernel_moisture_percent')
                        ->orWhereNotNull('shell_loss_percent')
                        ->orWhereNotNull('downtime_minutes')
                        ->orWhereNotNull('findings');
                });
            }])
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (KernelPlantRecord $record) => $this->toListRow($record))
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
                    'Kernel Plant ID',
                    'Date',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var KernelPlantRecord $record */
                    fputcsv($handle, [
                        $record->kernel_plant_id,
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
                "kernel-plant-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "kernel-plant-records_{$timestamp}.csv",
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

        $query = KernelPlantRecord::query();

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
     * Maps a KernelPlantRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(KernelPlantRecord $record): array
    {
        return [
            'id' => $record->id,
            'kernel_plant_id' => $record->kernel_plant_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-056--detail-kernel-plant-web business_logic
     * steps 1-4: findOrFail (404 via ModelNotFoundException) then resolve
     * station/createdBy/checkedBy/acknowledgedBy to display names and the
     * kernelPlantDetails grid, sorted into canonical time-slot order (not
     * alphabetical — '00:00' would otherwise sort before '07:00').
     */
    public function getDetail(string $id): array
    {
        $record = KernelPlantRecord::with([
            'station',
            'createdBy',
            'checkedBy',
            'acknowledgedBy',
            'kernelPlantDetails',
        ])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a KernelPlantRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(KernelPlantRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->kernelPlantDetails
            ->sortBy(fn (KernelPlantDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(fn (KernelPlantDetail $row) => [
                'id' => $row->id,
                'time_slot' => $row->time_slot,
                'ripple_mill_1_amps' => $row->ripple_mill_1_amps,
                'ripple_mill_2_amps' => $row->ripple_mill_2_amps,
                'claybath_hydro_sg' => $row->claybath_hydro_sg,
                'kernel_silo_1_temp_c' => $row->kernel_silo_1_temp_c,
                'kernel_silo_2_temp_c' => $row->kernel_silo_2_temp_c,
                'kernel_moisture_percent' => $row->kernel_moisture_percent,
                'shell_loss_percent' => $row->shell_loss_percent,
                'downtime_minutes' => $row->downtime_minutes,
                'findings' => $row->findings,
            ])
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'kernel_plant_id' => $record->kernel_plant_id,
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
