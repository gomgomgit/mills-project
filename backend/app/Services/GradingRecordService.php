<?php

namespace App\Services;

use App\Enums\Uom;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveGradingStationException;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
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
 * GradingRecordService — screen-017--data-browser-grading-web / usecase
 * (Data Browser Grading, web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * GradingRecordController) and the Livewire component (App\Livewire\
 * Data\DataBrowserGrading), so filtering/pagination/export rules stay
 * identical between the two entry points (mirrors the
 * WeighbridgeRecordService pattern used by screen-016).
 *
 * Filters accepted by both listRecords() and export(): 'date_from',
 * 'date_to' (both nullable date strings, filtered against GradingRecord's
 * `date` column — a plain date, NOT a datetime like weighbridge's
 * arrival_datetime), 'business_unit_id' (nullable uuid, filtered via the
 * station->business_unit_id relationship).
 */
class GradingRecordService
{
    /**
     * Row limit enforced on export() (business_logic step 5) — same
     * pragmatic MVP ceiling as WeighbridgeRecordService::EXPORT_ROW_LIMIT
     * (see that class's implementation_notes).
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Header fields accepted from the create()/update() form payload —
     * screen-023--form-grading-web. Mirrors WeighbridgeRecordService::
     * FORM_FIELDS's role (normalizeFormFields() below reads only these
     * keys from the raw $data array).
     */
    protected const FORM_FIELDS = [
        'grading_number', 'date', 'weighbridge_record_id', 'license_plate_no',
        'vehicle_code', 'estate_supplier', 'division', 'netto', 'quantity', 'note',
    ];

    /**
     * create() — screen-023--form-grading-web business_logic steps 1-5:
     * validate header + details, resolve station from production_line_id
     * (2026-08-20: was business_unit_id — Production Line inserted into
     * the hierarchy between Business Unit and Station, see entity-catalog
     * v9), compute each detail row's uom/percentage, then INSERT the
     * record and its details inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'grading')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveGradingStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            // GradingRecord::booted()'s `saving` guard rejects status=saved
            // on a brand-new record with zero GradingDetail rows — and at
            // this point in create() none exist yet (they're inserted by
            // upsertDetails() right after). Create as Synced (same
            // guard-satisfying placeholder GradingRecordFactory's own
            // definition() uses, see its docblock), insert the details,
            // THEN flip status to saved — by then gradingDetails()->count()
            // is > 0 so the guard passes.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = GradingRecord::create($attributes);
            $this->upsertDetails($record, $details, netto: $attributes['netto'], quantity: $attributes['quantity']);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'weighbridgeRecord', 'acknowledgedBy', 'gradingDetails.gradingParameter']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-023--form-grading-web business_logic steps 6-8:
     * validate header + details (business_unit_id/station_id never
     * accepted), UPDATE the record, then upsert its details (insert rows
     * without an id, update rows with an id still present, delete rows
     * previously in the DB but no longer in the array) inside a DB
     * transaction.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = GradingRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details, netto: $attributes['netto'], quantity: $attributes['quantity']);
        });

        $record->load(['station', 'weighbridgeRecord', 'acknowledgedBy', 'gradingDetails.gradingParameter']);

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
     * @return array<int, array{id: ?string, grading_parameter_id: ?string, quantity: mixed}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'grading_parameter_id' => $row['grading_parameter_id'] ?? null,
                'quantity' => $row['quantity'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'grading_number' => ['required', 'string'],
            'date' => ['required', 'date'],
            'weighbridge_record_id' => ['required', 'uuid', 'exists:weighbridge_records,id'],
            'license_plate_no' => ['required', 'string'],
            'vehicle_code' => ['nullable', 'string'],
            'estate_supplier' => ['required', 'string'],
            'division' => ['nullable', 'string'],
            'netto' => ['required', 'numeric'],
            'quantity' => ['required', 'numeric'],
            'note' => ['nullable', 'string'],
        ], [
            'grading_number.required' => 'Grading Number wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
            'weighbridge_record_id.required' => 'WB Card No wajib dipilih.',
            'weighbridge_record_id.exists' => 'WB Card No yang dipilih tidak valid.',
            'license_plate_no.required' => 'License Plate No wajib diisi.',
            'estate_supplier.required' => 'Estate/Supplier wajib diisi.',
            'netto.required' => 'Netto wajib diisi.',
            'quantity.required' => 'Quantity wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (grading_parameter_id + quantity both present) must exist, and
     * no grading_parameter_id may repeat across rows (entity-catalog:
     * "setiap Quality Parameter hanya dapat dipakai di satu baris").
     */
    protected function validateDetails(array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => filled($row['grading_parameter_id']) && $row['quantity'] !== null && $row['quantity'] !== ''
        );

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Grading Detail (Quality Parameter + Qty) harus diisi.',
            ]);
        }

        $duplicateParameterIds = $validRows->pluck('grading_parameter_id')->duplicates();

        if ($duplicateParameterIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Setiap Quality Parameter hanya dapat dipakai di satu baris Grading Detail.',
            ]);
        }

        foreach ($validRows as $row) {
            if (! GradingParameter::whereKey($row['grading_parameter_id'])->exists()) {
                throw ValidationException::withMessages([
                    'details' => 'Quality Parameter yang dipilih tidak valid.',
                ]);
            }
        }
    }

    /**
     * upsertDetails() — business_logic step 3 + 8: for each valid detail
     * row, snapshot uom from the selected GradingParameter and compute
     * percentage (netto-based for uom=kg, quantity-based for uom=bunch),
     * then insert rows without an id, update rows whose id still appears
     * in $details, and delete any existing row whose id is no longer
     * present.
     */
    protected function upsertDetails(GradingRecord $record, array $details, float $netto, float $quantity): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => filled($row['grading_parameter_id']) && $row['quantity'] !== null && $row['quantity'] !== ''
        );

        $keptIds = [];

        foreach ($validRows as $row) {
            $parameter = GradingParameter::findOrFail($row['grading_parameter_id']);
            $rowQuantity = (float) $row['quantity'];

            $percentage = match ($parameter->uom) {
                Uom::Kg => $netto > 0 ? ($rowQuantity / $netto) * 100 : 0,
                Uom::Bunch => $quantity > 0 ? ($rowQuantity / $quantity) * 100 : 0,
            };

            $detailAttributes = [
                'grading_record_id' => $record->id,
                'grading_parameter_id' => $parameter->id,
                'quantity' => $rowQuantity,
                'uom' => $parameter->uom,
                'percentage' => round($percentage, 2),
            ];

            if (! empty($row['id']) && GradingDetail::where('id', $row['id'])->where('grading_record_id', $record->id)->exists()) {
                GradingDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = GradingDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        GradingDetail::where('grading_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — only Acknowledged By is implemented on this
     * screen (self-attestation checkbox, Mill Management only) — Checked
     * By is never shown/collected here, consistent with Form Grading
     * mobile (screen-011) and Detail Grading Web (screen-020).
     */
    protected function applyVerification(array &$attributes, array $data, User $actor): void
    {
        if ($actor->role === UserRole::MillManagement) {
            $attributes['acknowledged_by'] = ! empty($data['acknowledged']) ? $actor->id : null;
        }
    }

    /**
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query, paginate via the shared Pagination helper,
     * and return the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (GradingRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic step 5-6: re-run the same filter query
     * (no pagination), enforce the row limit, generate a CSV (or
     * CSV-served-as-xlsx fallback — see implementation_notes) body, and
     * return it as a StreamedResponse for download.
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

                // Header row. Explicit $separator/$enclosure/$escape (PHP
                // 8.4 deprecates relying on fputcsv()'s default $escape).
                fputcsv($handle, [
                    'Grading Number',
                    'Date',
                    'Vehicle Number',
                    'Driver Name',
                    'Estate/Supplier',
                    'Division',
                    'Block',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var GradingRecord $record */
                    fputcsv($handle, [
                        $record->grading_number,
                        optional($record->date)->toDateString(),
                        $record->vehicle_number,
                        $record->driver_name,
                        $record->estate_supplier,
                        $record->division,
                        $record->block,
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

    protected function exportRowLimitLabel(): string
    {
        return number_format(self::EXPORT_ROW_LIMIT);
    }

    /**
     * Resolves the Content-Type + filename for the requested export format.
     *
     * No XLSX writer package is present in composer.json (spatie/laravel-excel
     * or maatwebsite/excel), and the tech-spec explicitly says not to add
     * one unless strictly necessary — so format=excel falls back to a CSV
     * body served with the xlsx mimetype/extension (pragmatic MVP; opens
     * correctly in Excel/most spreadsheet tools since they sniff CSV
     * content, though it is not a real OOXML file). Same approach as
     * WeighbridgeRecordService::fileMetaFor() — see implementation_notes.
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "grading-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "grading-records_{$timestamp}.csv",
        ];
    }

    /**
     * buildFilteredQuery() — business_logic step 1-2: validate the date
     * range (date_from > date_to → INVALID_DATE_RANGE) then apply the
     * business_unit_id (via station->business_unit_id) and `date` BETWEEN
     * filters (GradingRecord's `date` column is a plain date, not a
     * datetime — whereDate() still applies cleanly since it truncates both
     * sides to the date portion).
     *
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

        $query = GradingRecord::query();

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
     * Maps a GradingRecord to the list endpoint's success_schema row
     * shape: { id, grading_number, date, vehicle_number, driver_name,
     * status }.
     */
    protected function toListRow(GradingRecord $record): array
    {
        return [
            'id' => $record->id,
            'grading_number' => $record->grading_number,
            'date' => optional($record->date)->toDateString(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-020--detail-grading-web business_logic
     * steps 1-4: findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/weighbridge
     * record/acknowledged_by to display names/values and the
     * grading-detail grid (with each row's Quality Parameter name
     * resolved), mirroring DataPreviewGradingView.vue (mobile)'s detail
     * mode field set/order — Checked By intentionally NOT resolved/exposed
     * here, consistent with that mobile screen.
     */
    public function getDetail(string $id): array
    {
        $record = GradingRecord::with([
            'station',
            'weighbridgeRecord',
            'acknowledgedBy',
            'gradingDetails.gradingParameter',
        ])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a GradingRecord to the detail endpoint's success_schema —
     * every header field plus station_name/wb_card_number/
     * acknowledged_by_name resolved via the relations eager-loaded in
     * getDetail(), plus the `details` array (each grading-detail row with
     * its Quality Parameter name resolved instead of a raw uuid).
     */
    protected function toDetailRow(GradingRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'grading_number' => $record->grading_number,
            'date' => optional($record->date)->toIso8601String(),
            'weighbridge_record_id' => $record->weighbridge_record_id,
            'wb_card_number' => $record->weighbridgeRecord?->wb_card_number,
            'license_plate_no' => $record->license_plate_no,
            'vehicle_code' => $record->vehicle_code,
            'estate_supplier' => $record->estate_supplier,
            'division' => $record->division,
            'netto' => $record->netto,
            'quantity' => $record->quantity,
            'note' => $record->note,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'details' => $record->gradingDetails->map(fn ($detail) => [
                'id' => $detail->id,
                'grading_parameter_id' => $detail->grading_parameter_id,
                'grading_parameter_name' => $detail->gradingParameter?->name,
                'quantity' => $detail->quantity,
                'uom' => $detail->uom?->value,
                'percentage' => $detail->percentage,
            ])->all(),
        ];
    }
}
