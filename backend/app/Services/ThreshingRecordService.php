<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveThreshingStationException;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * ThreshingRecordService — screen-049--data-browser-threshing-web /
 * screen-053--detail-threshing-web / screen-057--form-threshing-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * ThreshingRecordController) and the Livewire components (App\Livewire\
 * Data\DataBrowserThreshing / DetailThreshing / FormThreshing), so
 * filtering/pagination/export/create/update rules stay identical between
 * entry points — mirrors CagesTrackRecordService's pattern.
 *
 * REVISED 2026-08-24 (entity-catalog v12): threshing_detail is now a
 * DYNAMIC add-row/remove-row grid — the user explicitly rejected the
 * original FIXED 24-row design (all 24 canonical time-slot rows always
 * inserted at once, no add/remove concept at all) as wasting screen space.
 * This now mirrors CagesTrackRecordService's `cages_tipped_time`
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
 * "Filled" row (filled_slot_count / minimum-one-row validation): a
 * threshing_detail row counts as filled when at least one of its 6 reading
 * columns (ffb_throughput_mt_hour, thresher_drum_speed_rpm,
 * motor_current_amps, unstripped_bunch_count_percent,
 * empty_bunch_oil_loss_percent, downtime_reason) is non-null. The model's
 * own `saving` guard (ThreshingRecord::booted(), "minimal satu
 * threshing-detail sebelum status=saved") checks ROW EXISTENCE, satisfied
 * once at least one row has been added — the real "minimal satu baris
 * terisi" business rule is enforced by THIS service's validateDetails(),
 * not by that guard.
 */
class ThreshingRecordService
{
    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['thresher_id', 'date', 'note'];

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * threshingRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
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
     * create() — screen-057--form-threshing-web business_logic steps 1-3:
     * validate header + details (at least one valid row, unique + strictly
     * ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its threshing_detail
     * rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'threshing')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveThreshingStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            // ThreshingRecord::booted()'s `saving` guard rejects
            // status=saved on a brand-new record with zero ThreshingDetail
            // rows — and at this point none exist yet (inserted by
            // upsertDetails() right after). Create as Synced (same
            // guard-satisfying placeholder pattern
            // CagesTrackRecordService::create() uses), insert the details,
            // THEN flip status to saved.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = ThreshingRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'threshingDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-057--form-threshing-web business_logic steps 5-7:
     * validate header + details (at least one valid row, unique + strictly
     * ascending canonical time_slot order), UPDATE the record, then upsert
     * its threshing_detail rows (insert rows without an id, update rows
     * whose id still appears in $details, delete any existing row whose id
     * is no longer present) inside a DB transaction — mirrors
     * CagesTrackRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = ThreshingRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'threshingDetails']);

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
     * @return array<int, array{id: ?string, time_slot: ?string, ffb_throughput_mt_hour: mixed, thresher_drum_speed_rpm: mixed, motor_current_amps: mixed, unstripped_bunch_count_percent: mixed, empty_bunch_oil_loss_percent: mixed, downtime_reason: mixed}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'time_slot' => $row['time_slot'] ?? null,
                'ffb_throughput_mt_hour' => $row['ffb_throughput_mt_hour'] ?? null,
                'thresher_drum_speed_rpm' => $row['thresher_drum_speed_rpm'] ?? null,
                'motor_current_amps' => $row['motor_current_amps'] ?? null,
                'unstripped_bunch_count_percent' => $row['unstripped_bunch_count_percent'] ?? null,
                'empty_bunch_oil_loss_percent' => $row['empty_bunch_oil_loss_percent'] ?? null,
                'downtime_reason' => $row['downtime_reason'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'thresher_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'thresher_id.required' => 'Thresher ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (a `time_slot` from the 24 canonical slots AND at least one of
     * its 6 reading columns non-null) must exist, and every row's
     * `time_slot` must be strictly ascending (canonical order) and unique
     * across the array — mirrors CagesTrackRecordService::validateDetails()
     * exactly, adapted to a canonical STRING order (via
     * canonicalTimeSlots()'s index) instead of a raw integer hour.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Threshing Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Threshing Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Threshing Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Threshing Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * @param  array{ffb_throughput_mt_hour: mixed, thresher_drum_speed_rpm: mixed, motor_current_amps: mixed, unstripped_bunch_count_percent: mixed, empty_bunch_oil_loss_percent: mixed, downtime_reason: mixed}  $row
     */
    protected function isRowFilled(array $row): bool
    {
        return $row['ffb_throughput_mt_hour'] !== null
            || $row['thresher_drum_speed_rpm'] !== null
            || $row['motor_current_amps'] !== null
            || $row['unstripped_bunch_count_percent'] !== null
            || $row['empty_bunch_oil_loss_percent'] !== null
            || ($row['downtime_reason'] !== null && $row['downtime_reason'] !== '');
    }

    /**
     * upsertDetails() — business_logic steps 3 + 7: for each valid detail
     * row (a selected `time_slot` AND at least one reading column filled —
     * same filter as validateDetails()), insert rows without an id, update
     * rows whose id still appears in $details, and delete any existing row
     * whose id is no longer present — mirrors
     * CagesTrackRecordService::upsertDetails() exactly (minus the
     * total_cages/cages_remain computation, which Threshing Detail has no
     * equivalent of).
     */
    protected function upsertDetails(ThreshingRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        $keptIds = [];

        foreach ($validRows as $row) {
            $detailAttributes = [
                'threshing_record_id' => $record->id,
                'time_slot' => $row['time_slot'],
                'ffb_throughput_mt_hour' => $row['ffb_throughput_mt_hour'],
                'thresher_drum_speed_rpm' => $row['thresher_drum_speed_rpm'],
                'motor_current_amps' => $row['motor_current_amps'],
                'unstripped_bunch_count_percent' => $row['unstripped_bunch_count_percent'],
                'empty_bunch_oil_loss_percent' => $row['empty_bunch_oil_loss_percent'],
                'downtime_reason' => $row['downtime_reason'],
            ];

            if (! empty($row['id']) && ThreshingDetail::where('id', $row['id'])->where('threshing_record_id', $record->id)->exists()) {
                ThreshingDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = ThreshingDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        ThreshingDetail::where('threshing_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * CagesTrackRecordService::applyVerification() exactly.
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
            ->withCount(['threshingDetails as filled_slot_count' => function (Builder $detailQuery) {
                $detailQuery->where(function (Builder $q) {
                    $q->whereNotNull('ffb_throughput_mt_hour')
                        ->orWhereNotNull('thresher_drum_speed_rpm')
                        ->orWhereNotNull('motor_current_amps')
                        ->orWhereNotNull('unstripped_bunch_count_percent')
                        ->orWhereNotNull('empty_bunch_oil_loss_percent')
                        ->orWhereNotNull('downtime_reason');
                });
            }])
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (ThreshingRecord $record) => $this->toListRow($record))
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
                    'Thresher ID',
                    'Date',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var ThreshingRecord $record */
                    fputcsv($handle, [
                        $record->thresher_id,
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
                "threshing-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "threshing-records_{$timestamp}.csv",
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

        $query = ThreshingRecord::query();

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
     * Maps a ThreshingRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(ThreshingRecord $record): array
    {
        return [
            'id' => $record->id,
            'thresher_id' => $record->thresher_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-053--detail-threshing-web business_logic steps
     * 1-4: findOrFail (404 via ModelNotFoundException) then resolve
     * station/createdBy/checkedBy/acknowledgedBy to display names and the
     * threshingDetails grid, sorted into canonical time-slot order (not
     * alphabetical — '00:00' would otherwise sort before '07:00').
     */
    public function getDetail(string $id): array
    {
        $record = ThreshingRecord::with([
            'station',
            'createdBy',
            'checkedBy',
            'acknowledgedBy',
            'threshingDetails',
        ])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a ThreshingRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(ThreshingRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->threshingDetails
            ->sortBy(fn (ThreshingDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(fn (ThreshingDetail $row) => [
                'id' => $row->id,
                'time_slot' => $row->time_slot,
                'ffb_throughput_mt_hour' => $row->ffb_throughput_mt_hour,
                'thresher_drum_speed_rpm' => $row->thresher_drum_speed_rpm,
                'motor_current_amps' => $row->motor_current_amps,
                'unstripped_bunch_count_percent' => $row->unstripped_bunch_count_percent,
                'empty_bunch_oil_loss_percent' => $row->empty_bunch_oil_loss_percent,
                'downtime_reason' => $row->downtime_reason,
            ])
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'thresher_id' => $record->thresher_id,
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
