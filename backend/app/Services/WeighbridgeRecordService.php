<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveWeighbridgeStationException;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * WeighbridgeRecordService — screen-016--data-browser-weighbridge-web /
 * usecase (Data Browser Weighbridge, web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * WeighbridgeRecordController) and the Livewire component (App\Livewire\
 * Data\DataBrowserWeighbridge), so filtering/pagination/export rules stay
 * identical between the two entry points (mirrors the AuthService pattern
 * used by screen-001/002/003/004).
 *
 * Filters accepted by both listRecords() and export(): 'date_from',
 * 'date_to' (both nullable date strings, filtered against
 * record_datetime), 'weighbridge_type' (nullable enum receive/dispatch),
 * 'business_unit_id' (nullable uuid, filtered via the
 * station->business_unit_id relationship).
 */
class WeighbridgeRecordService
{
    /**
     * Row limit enforced on export() (business_logic step 5) — protects
     * against unbounded memory/time usage when streaming a CSV/XLSX for a
     * very large filtered dataset. Not specified as an exact number in the
     * tech-spec ("e.g. 50000 rows"); 50000 is used as a pragmatic MVP
     * ceiling — see implementation_notes.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query, paginate via the shared Pagination helper,
     * and return the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('record_datetime');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (WeighbridgeRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic step 5-6: re-run the same filter query
     * (no pagination), enforce the row limit, generate a CSV (or
     * CSV-served-as-xlsx fallback — see implementation_notes) body, and
     * return it as a StreamedResponse for download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('record_datetime');

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
                    'WB Card Number',
                    'Type',
                    'Record Datetime',
                    'Vehicle Number',
                    'Driver Name',
                    'Estate/Supplier',
                    'Destination',
                    'Division',
                    'Block',
                    'Gross Weight',
                    'Tare Weight',
                    'Net Weight',
                    'Quantity',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var WeighbridgeRecord $record */
                    fputcsv($handle, [
                        $record->wb_card_number,
                        $record->weighbridge_type,
                        optional($record->record_datetime)->toDateTimeString(),
                        $record->vehicle_number,
                        $record->driver_name,
                        $record->estate_supplier,
                        $record->destination,
                        $record->division,
                        $record->block,
                        $record->gross_weight,
                        $record->tare_weight,
                        $record->net_weight,
                        $record->quantity,
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
     * content, though it is not a real OOXML file). See implementation_notes.
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "weighbridge-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "weighbridge-records_{$timestamp}.csv",
        ];
    }

    /**
     * buildFilteredQuery() — business_logic step 1-2: validate the date
     * range (date_from > date_to → INVALID_DATE_RANGE) then apply the
     * business_unit_id (via station->business_unit_id), weighbridge_type,
     * and record_datetime BETWEEN filters.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, weighbridge_type?: ?string, business_unit_id?: ?string}  $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $weighbridgeType = $filters['weighbridge_type'] ?? null;
        $businessUnitId = $filters['business_unit_id'] ?? null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw new InvalidDateRangeException();
        }

        $query = WeighbridgeRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        if ($weighbridgeType) {
            $query->where('weighbridge_type', $weighbridgeType);
        }

        if ($dateFrom) {
            $query->whereDate('record_datetime', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('record_datetime', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Fields accepted by both create() and update() request bodies —
     * everything except business_unit_id (create-only, resolves station_id,
     * never accepted on update per screen-022 business_rules: "Business
     * Unit tidak dapat diubah setelah record dibuat") and checked/
     * acknowledged (role-gated booleans, handled separately below since
     * they map to checked_by/acknowledged_by, not stored verbatim).
     */
    protected const FORM_FIELDS = [
        'wb_card_number',
        'weighbridge_type',
        'record_datetime',
        'vehicle_number',
        'driver_name',
        'estate_supplier',
        'destination',
        'division',
        'block',
        'gross_weight',
        'tare_weight',
        'quantity',
    ];

    /**
     * create() — screen-022--form-weighbridge-web business_logic steps
     * 1-4: validate required fields (destination required iff
     * weighbridge_type=dispatch) → resolve the sole active weighbridge
     * Station for production_line_id (2026-08-20: was business_unit_id —
     * Production Line inserted into the hierarchy between Business Unit
     * and Station, see entity-catalog v9) (422
     * NO_ACTIVE_WEIGHBRIDGE_STATION if none) → apply role-gated
     * checked/acknowledged → insert with
     * status=saved. net_weight is never accepted from $data — the
     * WeighbridgeRecord model's `saving` event always recomputes it from
     * gross/tare (see that model's docblock); this is why Net Weight
     * stays a disabled field in the form despite the general
     * "web inputs are never disabled" convention (uiux-spec
     * component_patterns 'web-form-input') — the value would be silently
     * overwritten on save regardless of what the UI sent, so exposing it
     * as editable would be misleading.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws NoActiveWeighbridgeStationException
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);

        $this->validateForm($attributes);

        $station = Station::query()
            ->where('production_line_id', $data['production_line_id'] ?? null)
            ->where('type', 'weighbridge')
            ->where('is_active', true)
            ->first();

        if ($station === null) {
            throw new NoActiveWeighbridgeStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['status'] = 'saved';
        $attributes['created_by'] = $actor->id;
        $this->applyVerification($attributes, $data, $actor);

        $record = WeighbridgeRecord::create($attributes);
        $record->load(['station', 'checkedBy', 'acknowledgedBy']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-022--form-weighbridge-web business_logic steps
     * 5-7: validate id exists (404 if not) → validate required fields →
     * apply role-gated checked/acknowledged → update. business_unit_id/
     * station_id are never accepted here.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = WeighbridgeRecord::findOrFail($id);

        $attributes = $this->normalizeFormFields($data);

        $this->validateForm($attributes);

        $this->applyVerification($attributes, $data, $actor);

        $record->update($attributes);
        $record->load(['station', 'checkedBy', 'acknowledgedBy']);

        return $this->toDetailRow($record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFormFields(array $data): array
    {
        $attributes = [];

        foreach (self::FORM_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        if ($attributes['weighbridge_type'] !== 'dispatch') {
            $attributes['destination'] = null;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'wb_card_number' => ['required', 'string'],
            'weighbridge_type' => ['required', 'in:receive,dispatch'],
            'record_datetime' => ['required', 'date'],
            'vehicle_number' => ['required', 'string'],
            'driver_name' => ['required', 'string'],
            'estate_supplier' => ['required', 'string'],
            'destination' => [$attributes['weighbridge_type'] === 'dispatch' ? 'required' : 'nullable', 'string'],
            'division' => ['nullable', 'string'],
            'block' => ['nullable', 'string'],
            'gross_weight' => ['required', 'numeric'],
            'tare_weight' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'numeric'],
        ], [
            'wb_card_number.required' => 'WB Card Number wajib diisi.',
            'weighbridge_type.required' => 'Tipe Weighbridge wajib dipilih.',
            'record_datetime.required' => 'Tanggal & waktu wajib diisi.',
            'vehicle_number.required' => 'No. Kendaraan wajib diisi.',
            'driver_name.required' => 'Nama Supir wajib diisi.',
            'estate_supplier.required' => 'Estate/Supplier wajib diisi.',
            'destination.required' => 'Tujuan Muatan wajib diisi untuk tipe Dispatch.',
            'gross_weight.required' => 'Gross Weight wajib diisi.',
        ])->validate();
    }

    /**
     * Applies the role-gated checked/acknowledged self-attestation
     * checkboxes onto $attributes (by reference) — `checked=true` sets
     * checked_by to $actor->id ONLY when $actor is a Supervisor;
     * `acknowledged=true` sets acknowledged_by to $actor->id ONLY when
     * $actor is Mill Management. Any other role sending these booleans
     * is silently ignored (not an error — the fields simply are not
     * rendered for that role in the FE, per component_patterns 'form').
     * Unchecked ($false or absent) clears the corresponding *_by column.
     *
     * @param  array<string, mixed>  $attributes  by reference
     * @param  array<string, mixed>  $data  raw request payload
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
     * getDetail() — screen-019--detail-weighbridge-web business_logic
     * steps 1-3: findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/checked_by/
     * acknowledged_by to display names, mirroring
     * DataPreviewWeighbridgeView.vue (mobile)'s field set/order so the
     * web detail screen presents the exact same record shape.
     */
    public function getDetail(string $id): array
    {
        $record = WeighbridgeRecord::with(['station', 'checkedBy', 'acknowledgedBy'])->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a WeighbridgeRecord to the detail endpoint's success_schema —
     * every field, plus station_name/checked_by_name/acknowledged_by_name
     * resolved via the relations eager-loaded in getDetail() (raw uuids
     * are not useful on a human-facing read-only detail screen).
     */
    protected function toDetailRow(WeighbridgeRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'wb_card_number' => $record->wb_card_number,
            'weighbridge_type' => $record->weighbridge_type,
            'record_datetime' => optional($record->record_datetime)->toIso8601String(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'estate_supplier' => $record->estate_supplier,
            'destination' => $record->destination,
            'division' => $record->division,
            'block' => $record->block,
            'gross_weight' => $record->gross_weight,
            'tare_weight' => $record->tare_weight,
            'net_weight' => $record->net_weight,
            'quantity' => $record->quantity,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Maps a WeighbridgeRecord to the list endpoint's success_schema row
     * shape: { id, wb_card_number, weighbridge_type, record_datetime,
     * vehicle_number, driver_name, destination, net_weight, status }.
     */
    protected function toListRow(WeighbridgeRecord $record): array
    {
        return [
            'id' => $record->id,
            'wb_card_number' => $record->wb_card_number,
            'weighbridge_type' => $record->weighbridge_type,
            'record_datetime' => optional($record->record_datetime)->toIso8601String(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'destination' => $record->destination,
            'net_weight' => $record->net_weight,
            'status' => $record->status?->value,
        ];
    }
}
