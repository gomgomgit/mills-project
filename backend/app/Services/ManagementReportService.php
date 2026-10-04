<?php

namespace App\Services;

use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\ProductionLine;
use App\Models\WeighbridgeRecord;
use App\Support\SheetWriter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    /** Judul kolom ekspor (setelah kolom konteks Production Line). */
    public const EXPORT_HEADER = [
        'Tanggal',
        'Weighbridge Masuk — Jumlah Trip', 'Weighbridge Masuk — Berat Bersih (kg)',
        'Weighbridge Keluar — Jumlah Trip', 'Weighbridge Keluar — Berat Bersih (kg)',
        'Grading — Jumlah Record', 'Grading — Netto (kg)', 'Grading — Jumlah Tandan',
        'Cages Track — Jumlah Record', 'Cages Track — Lori Ditumpahkan',
    ];

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
    public function getBreakdown(string $businessUnitId, ?string $dateFrom, ?string $dateTo, ?string $productionLineId = null): array
    {
        [$from, $to] = $this->resolveDateRange($dateFrom, $dateTo);

        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();

            $rows[] = [
                'date' => $date,
                'weighbridge' => $this->weighbridgeSummary($date, $businessUnitId, $productionLineId),
                'grading' => $this->gradingSummary($date, $businessUnitId, $productionLineId),
                'cages_track' => $this->cagesTrackSummary($date, $businessUnitId, $productionLineId),
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
     * range) + generate a CSV or real .xlsx body (App\Support\SheetWriter)
     * with one row per date plus a final Total row, returned as a
     * StreamedResponse.
     *
     * @throws InvalidDateRangeException
     * @throws ExportFailedException
     */
    public function export(string $businessUnitId, ?string $dateFrom, ?string $dateTo, string $format, ?string $productionLineId = null): StreamedResponse
    {
        $breakdown = $this->getBreakdown($businessUnitId, $dateFrom, $dateTo, $productionLineId);
        $lineName = $productionLineId !== null
            ? (string) ProductionLine::query()->whereKey($productionLineId)->value('name')
            : '';

        try {
            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($breakdown, $format, $lineName) {
                $handle = SheetWriter::open($format);

                // Judul kolom Bahasa Indonesia, sama dengan layar. Arus masuk
                // dan arus keluar Weighbridge DIPISAH (tidak pernah
                // dijumlahkan) — sama seperti Laporan Weighbridge.
                $handle->row(array_merge(['Production Line'], self::EXPORT_HEADER));

                foreach ($breakdown['rows'] as $row) {
                    $handle->row(array_merge([$lineName], $this->rowToCsvLine($row)));
                }

                $handle->row(array_merge([$lineName], $this->rowToCsvLine(['date' => 'Total'] + $breakdown['total'])));

                $handle->close();
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (Throwable $e) {
            throw new ExportFailedException;
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
            $row['weighbridge']['receive']['count'],
            $row['weighbridge']['receive']['total_net_weight'],
            $row['weighbridge']['dispatch']['count'],
            $row['weighbridge']['dispatch']['total_net_weight'],
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
            'weighbridge' => self::emptyWeighbridge(),
            'grading' => ['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0],
            'cages_track' => ['count' => 0, 'total_cages_tipped' => 0],
        ];

        foreach ($rows as $row) {
            foreach (['receive', 'dispatch'] as $flow) {
                $total['weighbridge'][$flow]['count'] += $row['weighbridge'][$flow]['count'];
                $total['weighbridge'][$flow]['total_net_weight'] += $row['weighbridge'][$flow]['total_net_weight'];
            }
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
            throw new InvalidDateRangeException;
        }

        $from = $dateFrom !== null ? Carbon::parse($dateFrom) : Carbon::today()->startOfMonth();
        $to = $dateTo !== null ? Carbon::parse($dateTo) : Carbon::today();

        return [$from, $to];
    }

    /**
     * Breakdown Weighbridge kosong — bentuk yang sama dengan
     * weighbridgeSummary(), dipakai untuk baris Total dan keadaan galat.
     *
     * @return array{receive: array{count: int, total_net_weight: float}, dispatch: array{count: int, total_net_weight: float}}
     */
    public static function emptyWeighbridge(): array
    {
        return [
            'receive' => ['count' => 0, 'total_net_weight' => 0.0],
            'dispatch' => ['count' => 0, 'total_net_weight' => 0.0],
        ];
    }

    /**
     * ARUS MASUK DAN ARUS KELUAR DIPISAH, TIDAK PERNAH DIJUMLAHKAN (temuan
     * audit 2026-10-04 #2a). Sebelumnya satu "Count"/"Net Weight"
     * menjumlahkan trip TBS masuk dengan pengiriman CPO/kernel keluar —
     * dua satuan muatan yang tidak sebanding (lihat
     * WeighbridgeReportService). `weighbridge_type` di luar receive/dispatch
     * diperlakukan sebagai receive, default kolomnya sendiri — sama dengan
     * WeighbridgeReportService::flowOf().
     *
     * @return array{receive: array{count: int, total_net_weight: float}, dispatch: array{count: int, total_net_weight: float}}
     */
    protected function weighbridgeSummary(string $date, string $businessUnitId, ?string $productionLineId = null): array
    {
        $query = WeighbridgeRecord::query()->whereDate('record_datetime', $date);
        $this->scopeByBusinessUnit($query, $businessUnitId, $productionLineId);

        $dispatch = (clone $query)->where('weighbridge_type', 'dispatch');
        $receive = (clone $query)->where(fn (Builder $q) => $q->where('weighbridge_type', '!=', 'dispatch')->orWhereNull('weighbridge_type'));

        return [
            'receive' => [
                'count' => (int) $receive->count(),
                'total_net_weight' => (float) ($receive->sum('net_weight') ?? 0),
            ],
            'dispatch' => [
                'count' => (int) $dispatch->count(),
                'total_net_weight' => (float) ($dispatch->sum('net_weight') ?? 0),
            ],
        ];
    }

    /**
     * @return array{count: int, total_netto: float, total_quantity: float}
     */
    protected function gradingSummary(string $date, string $businessUnitId, ?string $productionLineId = null): array
    {
        $query = GradingRecord::query()->whereDate('date', $date);
        $this->scopeByBusinessUnit($query, $businessUnitId, $productionLineId);

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
    protected function cagesTrackSummary(string $date, string $businessUnitId, ?string $productionLineId = null): array
    {
        $headerQuery = CagesTrackRecord::query()->whereDate('date', $date);
        $this->scopeByBusinessUnit($headerQuery, $businessUnitId, $productionLineId);

        $headerIds = (clone $headerQuery)->pluck('id');

        $totalCagesTipped = CagesTippedTime::query()
            ->whereIn('cages_track_record_id', $headerIds)
            ->sum('total_cages');

        return [
            'count' => $headerIds->count(),
            'total_cages_tipped' => (int) ($totalCagesTipped ?? 0),
        ];
    }

    /**
     * Cakupan mill, lalu (bila dipilih) cakupan PRODUCTION LINE. Line dibaca
     * dari kolom `production_line_id` milik RECORD sendiri — sumber
     * kebenaran yang sama dengan laporan stasiun dan Data Browser, bukan
     * line stasiun hari ini.
     */
    protected function scopeByBusinessUnit(Builder $query, string $businessUnitId, ?string $productionLineId = null): void
    {
        $query->whereHas('station', fn (Builder $stationQuery) => $stationQuery->where('business_unit_id', $businessUnitId));

        if ($productionLineId !== null) {
            $query->where($query->getModel()->getTable().'.production_line_id', $productionLineId);
        }
    }

    /**
     * Opsi Production Line untuk pemilih di layar — SELALU dibatasi mill
     * yang berlaku, jadi line mill lain tidak pernah menjadi opsi.
     *
     * @return list<array{id: string, name: string}>
     */
    public function productionLineOptions(string $businessUnitId): array
    {
        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => ['id' => (string) $line->id, 'name' => (string) $line->name])
            ->all();
    }

    /**
     * Line yang sah untuk mill ini, atau null (kosong / milik mill lain).
     */
    public function resolveProductionLineOrNull(string $businessUnitId, ?string $productionLineId): ?string
    {
        // Str::isUuid() DULU: nilai bukan-UUID tidak boleh sampai ke SQL
        // (PostgreSQL: SQLSTATE 22P02 → 500).
        if ($productionLineId === null || $productionLineId === '' || ! Str::isUuid($productionLineId)) {
            return null;
        }

        return ProductionLine::query()
            ->whereKey($productionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists() ? $productionLineId : null;
    }

    /**
     * Jalur API: line WAJIB dan harus milik mill pemanggil — laporan
     * menghasilkan angka gabungan, dan total yang mencampur semua line bukan
     * angka yang bisa ditindaklanjuti (aturan yang sama dengan laporan
     * stasiun). 422 untuk kosong maupun line mill lain — tidak pernah
     * mengonfirmasi bahwa line mill lain itu ada.
     *
     * @throws ValidationException
     */
    public function resolveProductionLine(string $businessUnitId, ?string $productionLineId): string
    {
        $resolved = $this->resolveProductionLineOrNull($businessUnitId, $productionLineId);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'production_line_id' => [$productionLineId === null || $productionLineId === ''
                    ? 'Production Line wajib dipilih untuk menampilkan laporan.'
                    : 'Production Line yang dipilih tidak valid.'],
            ]);
        }

        return $resolved;
    }

    /**
     * Resolves the Content-Type + filename for the requested export
     * format (format=excel → real .xlsx via App\Support\SheetWriter).
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
