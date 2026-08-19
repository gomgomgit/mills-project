<?php

namespace App\Services;

use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\CagesTrackRecord;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CagesTrackRecordService — screen-018--data-browser-cages-track-web /
 * usecase-018--data-browser-cages-track-web (Data Browser Cages Track, web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * CagesTrackRecordController) and the Livewire component (App\Livewire\
 * Data\DataBrowserCagesTrack), so filtering/pagination/export rules stay
 * identical between the two entry points (mirrors the
 * GradingRecordService pattern used by screen-017).
 *
 * Filters accepted by both listRecords() and export(): 'date_from',
 * 'date_to' (both nullable date strings, filtered against
 * CagesTrackRecord's `date` column — a plain date, NOT a datetime like
 * weighbridge's arrival_datetime), 'business_unit_id' (nullable uuid,
 * filtered via the station->business_unit_id relationship).
 *
 * tipped_time_count (list success_schema + implementation_notes): NOT a
 * column on cages_track_records — computed via Eloquent's withCount()
 * against CagesTrackRecord::cagesTippedTimes(), which yields a
 * `cages_tipped_times_count` attribute on each row (Laravel's default
 * withCount() column-naming convention: Str::snake(relation) . '_count').
 */
class CagesTrackRecordService
{
    /**
     * Row limit enforced on export() (business_logic step 5) — same
     * pragmatic MVP ceiling as GradingRecordService::EXPORT_ROW_LIMIT /
     * WeighbridgeRecordService::EXPORT_ROW_LIMIT (see those classes'
     * implementation_notes).
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query (with tipped_time_count computed via
     * withCount()), paginate via the shared Pagination helper, and return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount('cagesTippedTimes')
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (CagesTrackRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic step 5-6: re-run the same filter query
     * (no pagination, still with tipped_time_count computed), enforce the
     * row limit, generate a CSV (or CSV-served-as-xlsx fallback — see
     * implementation_notes) body, and return it as a StreamedResponse for
     * download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount('cagesTippedTimes')
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
                    'Cages Track Number',
                    'Date',
                    'Checked By',
                    'Acknowledged By',
                    'Jumlah Cage/Lori Tercatat',
                    'Status',
                ], ',', '"', '\\');

                foreach ($records as $record) {
                    /** @var CagesTrackRecord $record */
                    fputcsv($handle, [
                        $record->cages_track_number,
                        optional($record->date)->toDateString(),
                        $record->checkedBy?->name,
                        $record->acknowledgedBy?->name,
                        $record->cages_tipped_times_count,
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
     * GradingRecordService::fileMetaFor() / WeighbridgeRecordService::fileMetaFor()
     * — see implementation_notes.
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "cages-track-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "cages-track-records_{$timestamp}.csv",
        ];
    }

    /**
     * buildFilteredQuery() — business_logic step 1-2: validate the date
     * range (date_from > date_to → INVALID_DATE_RANGE) then apply the
     * business_unit_id (via station->business_unit_id) and `date` BETWEEN
     * filters (CagesTrackRecord's `date` column is a plain date, not a
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

        $query = CagesTrackRecord::query();

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
     * Maps a CagesTrackRecord (with tipped_time_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape:
     * { id, cages_track_number, date, tipped_time_count, status }.
     */
    protected function toListRow(CagesTrackRecord $record): array
    {
        return [
            'id' => $record->id,
            'cages_track_number' => $record->cages_track_number,
            'date' => optional($record->date)->toDateString(),
            'tipped_time_count' => (int) $record->cages_tipped_times_count,
            'status' => $record->status?->value,
        ];
    }
}
