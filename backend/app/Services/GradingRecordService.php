<?php

namespace App\Services;

use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\GradingRecord;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
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
}
