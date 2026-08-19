<?php

namespace App\Services;

use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\WeighbridgeRecord;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
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
 * arrival_datetime), 'business_unit_id' (nullable uuid, filtered via the
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
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('arrival_datetime');

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
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $query = $this->buildFilteredQuery($filters)->orderByDesc('arrival_datetime');

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
                    'Arrival Datetime',
                    'Dispatch Datetime',
                    'Vehicle Number',
                    'Driver Name',
                    'Estate/Supplier',
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
                        optional($record->arrival_datetime)->toDateTimeString(),
                        optional($record->dispatch_datetime)->toDateTimeString(),
                        $record->vehicle_number,
                        $record->driver_name,
                        $record->estate_supplier,
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
     * business_unit_id (via station->business_unit_id) and arrival_datetime
     * BETWEEN filters.
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

        $query = WeighbridgeRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        if ($dateFrom) {
            $query->whereDate('arrival_datetime', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('arrival_datetime', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Maps a WeighbridgeRecord to the list endpoint's success_schema row
     * shape: { id, wb_card_number, arrival_datetime, vehicle_number,
     * driver_name, net_weight, status }.
     */
    protected function toListRow(WeighbridgeRecord $record): array
    {
        return [
            'id' => $record->id,
            'wb_card_number' => $record->wb_card_number,
            'arrival_datetime' => optional($record->arrival_datetime)->toIso8601String(),
            'vehicle_number' => $record->vehicle_number,
            'driver_name' => $record->driver_name,
            'net_weight' => $record->net_weight,
            'status' => $record->status?->value,
        ];
    }
}
