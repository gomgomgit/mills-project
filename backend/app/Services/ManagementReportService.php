<?php

namespace App\Services;

use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\WeighbridgeRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * ManagementReportService — screen-026--laporan-manajemen /
 * usecase-026--laporan-manajemen (Laporan Manajemen).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * ManagementReportController) and the Livewire component (App\Livewire\
 * Dashboard\ManagementReport), mirroring every other screen in this
 * codebase.
 *
 * Deliberately NOT a reuse of DashboardService (screen-025) — that
 * service's per-entity summary methods aggregate over a single date
 * range, whereas this screen needs one row PER DAY plus a Total row, and
 * is always scoped to the acting user's own business_unit_id (no
 * business_unit_id filter param — Mill Management only has one mill, per
 * actor-index). Same "one dedicated service per screen" pattern already
 * used by WeighbridgeRecordService/GradingRecordService/
 * CagesTrackRecordService rather than a shared generic aggregator.
 */
class ManagementReportService
{
    /**
     * getBreakdown() — business_logic steps 1-6: validate date range →
     * default to start-of-month..today if not provided → aggregate
     * per-day for weighbridge/grading/cages_track, scoped to
     * $businessUnitId → return {rows, total}.
     *
     * @return array{rows: array<int, array>, total: array}
     *
     * @throws InvalidDateRangeException
     */
    public function getBreakdown(string $businessUnitId, ?string $dateFrom, ?string $dateTo): array
    {
        [$from, $to] = $this->resolveDateRange($dateFrom, $dateTo);

        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();

            $rows[] = [
                'date' => $date,
                'weighbridge' => $this->weighbridgeSummary($date, $businessUnitId),
                'grading' => $this->gradingSummary($date, $businessUnitId),
                'cages_track' => $this->cagesTrackSummary($date, $businessUnitId),
            ];

            $cursor->addDay();
        }

        return [
            'rows' => $rows,
            'total' => $this->totalOf($rows),
        ];
    }

    /**
     * export() — business_logic steps 1-6 (same as getBreakdown(), no
     * pagination concern since rows are already bounded by the date
     * range) + generate a CSV (or CSV-as-xlsx fallback) body with one row
     * per date plus a final Total row, returned as a StreamedResponse.
     *
     * @throws InvalidDateRangeException
     * @throws ExportFailedException
     */
    public function export(string $businessUnitId, ?string $dateFrom, ?string $dateTo, string $format): StreamedResponse
    {
        $breakdown = $this->getBreakdown($businessUnitId, $dateFrom, $dateTo);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($breakdown) {
                $handle = fopen('php://output', 'w');

                fputcsv($handle, [
                    'Tanggal',
                    'WB Count', 'WB Total Net Weight',
                    'Grading Count', 'Grading Total Netto', 'Grading Total Quantity',
                    'Cages Track Count', 'Cages Track Total Cages Tipped',
                ], ',', '"', '\\');

                foreach ($breakdown['rows'] as $row) {
                    fputcsv($handle, $this->rowToCsvLine($row), ',', '"', '\\');
                }

                fputcsv($handle, $this->rowToCsvLine(['date' => 'TOTAL'] + $breakdown['total']), ',', '"', '\\');

                fclose($handle);
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (Throwable $e) {
            throw new ExportFailedException();
        }
    }

    /**
     * @param  array{date: string, weighbridge: array, grading: array, cages_track: array}  $row
     * @return array<int, string|int|float>
     */
    protected function rowToCsvLine(array $row): array
    {
        return [
            $row['date'],
            $row['weighbridge']['count'],
            $row['weighbridge']['total_net_weight'],
            $row['grading']['count'],
            $row['grading']['total_netto'],
            $row['grading']['total_quantity'],
            $row['cages_track']['count'],
            $row['cages_track']['total_cages_tipped'],
        ];
    }

    /**
     * @param  array<int, array{weighbridge: array, grading: array, cages_track: array}>  $rows
     */
    protected function totalOf(array $rows): array
    {
        $total = [
            'weighbridge' => ['count' => 0, 'total_net_weight' => 0.0],
            'grading' => ['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0],
            'cages_track' => ['count' => 0, 'total_cages_tipped' => 0],
        ];

        foreach ($rows as $row) {
            $total['weighbridge']['count'] += $row['weighbridge']['count'];
            $total['weighbridge']['total_net_weight'] += $row['weighbridge']['total_net_weight'];
            $total['grading']['count'] += $row['grading']['count'];
            $total['grading']['total_netto'] += $row['grading']['total_netto'];
            $total['grading']['total_quantity'] += $row['grading']['total_quantity'];
            $total['cages_track']['count'] += $row['cages_track']['count'];
            $total['cages_track']['total_cages_tipped'] += $row['cages_track']['total_cages_tipped'];
        }

        return $total;
    }

    /**
     * Validates date_from <= date_to (step 1) and defaults to start of
     * the current month / today when not provided (step 2) — deliberately
     * DIFFERENT default from DashboardService (which defaults both to
     * today), since this is a periodic report, not a single-day snapshot.
     *
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws InvalidDateRangeException
     */
    protected function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom !== null && $dateTo !== null && Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            throw new InvalidDateRangeException();
        }

        $from = $dateFrom !== null ? Carbon::parse($dateFrom) : Carbon::today()->startOfMonth();
        $to = $dateTo !== null ? Carbon::parse($dateTo) : Carbon::today();

        return [$from, $to];
    }

    /**
     * @return array{count: int, total_net_weight: float}
     */
    protected function weighbridgeSummary(string $date, string $businessUnitId): array
    {
        $query = WeighbridgeRecord::query()->whereDate('record_datetime', $date);
        $this->scopeByBusinessUnit($query, $businessUnitId);

        return [
            'count' => (int) $query->count(),
            'total_net_weight' => (float) ($query->sum('net_weight') ?? 0),
        ];
    }

    /**
     * @return array{count: int, total_netto: float, total_quantity: float}
     */
    protected function gradingSummary(string $date, string $businessUnitId): array
    {
        $query = GradingRecord::query()->whereDate('date', $date);
        $this->scopeByBusinessUnit($query, $businessUnitId);

        return [
            'count' => (int) $query->count(),
            'total_netto' => (float) ($query->sum('netto') ?? 0),
            'total_quantity' => (float) ($query->sum('quantity') ?? 0),
        ];
    }

    /**
     * total_cages_tipped = SUM(cages-tipped-time.total_cages), not
     * cages_out/cages_tipped — same convention as DashboardService.
     *
     * @return array{count: int, total_cages_tipped: int}
     */
    protected function cagesTrackSummary(string $date, string $businessUnitId): array
    {
        $headerQuery = CagesTrackRecord::query()->whereDate('date', $date);
        $this->scopeByBusinessUnit($headerQuery, $businessUnitId);

        $headerIds = (clone $headerQuery)->pluck('id');

        $totalCagesTipped = CagesTippedTime::query()
            ->whereIn('cages_track_record_id', $headerIds)
            ->sum('total_cages');

        return [
            'count' => $headerIds->count(),
            'total_cages_tipped' => (int) ($totalCagesTipped ?? 0),
        ];
    }

    protected function scopeByBusinessUnit(Builder $query, string $businessUnitId): void
    {
        $query->whereHas('station', fn (Builder $stationQuery) => $stationQuery->where('business_unit_id', $businessUnitId));
    }

    /**
     * Resolves the Content-Type + filename for the requested export
     * format — same fallback convention as WeighbridgeRecordService
     * (no XLSX writer package present; format=excel serves a CSV body
     * with the xlsx mimetype/extension).
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "laporan-manajemen_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-manajemen_{$timestamp}.csv",
        ];
    }
}
