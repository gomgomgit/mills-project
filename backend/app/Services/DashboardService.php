<?php

namespace App\Services;

use App\Exceptions\InvalidDateRangeException;
use App\Models\CagesTrackRecord;
use App\Models\CagesTippedTime;
use App\Models\GradingRecord;
use App\Models\WeighbridgeRecord;
use App\Support\Concerns\ScopesToActorMill;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * DashboardService — screen-025--dashboard-web / usecase-025--dashboard-web
 * (Dashboard Web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * DashboardController) and the Livewire component (App\Livewire\Dashboard\
 * DashboardHome), mirroring every other screen in this codebase (e.g.
 * WeighbridgeRecordService / DataBrowserWeighbridge).
 *
 * Filters accepted by getSummary(): 'date_from', 'date_to' (both nullable
 * date strings, default to today's date when omitted — business_logic
 * step 2), 'business_unit_id' (nullable uuid, filters via each entity's
 * station->business_unit_id relationship).
 */
class DashboardService
{
    use ScopesToActorMill;

    /**
     * getSummary() — business_logic steps 1-6: validate date range → default
     * to today if not provided → aggregate weighbridge-record, grading-record,
     * and cages-track-record (+ cages-tipped-time) with the same filter →
     * return the combined {weighbridge, grading, cages_track} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function getSummary(array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        // Sampai 2026-09-28 `business_unit_id` diambil mentah dari
        // DashboardController::summary() ($request->only([...])), sehingga
        // Supervisor mana pun bisa meminta KPI mill lain — atau menghilangkan
        // parameternya dan mendapat agregat SELURUH mill. resolveReadMillId()
        // membuang nilai kiriman klien untuk aktor terikat mill dan
        // menggantinya dengan mill aktor; hanya Admin yang nilainya dipakai
        // apa adanya (kosong = semua mill, perilaku lama dipertahankan).
        //
        // Aturan, pesan, dan mode gagal-tertutupnya sama persis dengan jalur
        // baca 18 *RecordService — satu trait, bukan mekanisme kedua.
        $businessUnitId = $this->resolveReadMillId(
            ($filters['business_unit_id'] ?? null) === null ? null : (string) $filters['business_unit_id']
        );

        return [
            'weighbridge' => $this->weighbridgeSummary($dateFrom, $dateTo, $businessUnitId),
            'grading' => $this->gradingSummary($dateFrom, $dateTo, $businessUnitId),
            'cages_track' => $this->cagesTrackSummary($dateFrom, $dateTo, $businessUnitId),
        ];
    }

    /**
     * Validates date_from <= date_to (step 1) and defaults both to today's
     * date when not provided (step 2).
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidDateRangeException
     */
    protected function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom !== null && $dateTo !== null && Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            throw new InvalidDateRangeException();
        }

        $today = Carbon::today()->toDateString();

        return [$dateFrom ?? $today, $dateTo ?? $today];
    }

    /**
     * @return array{count: int, total_net_weight: float}
     */
    protected function weighbridgeSummary(string $dateFrom, string $dateTo, ?string $businessUnitId): array
    {
        $query = WeighbridgeRecord::query()
            ->whereDate('record_datetime', '>=', $dateFrom)
            ->whereDate('record_datetime', '<=', $dateTo);

        $this->scopeByBusinessUnit($query, $businessUnitId);

        return [
            'count' => (int) $query->count(),
            'total_net_weight' => (float) ($query->sum('net_weight') ?? 0),
        ];
    }

    /**
     * @return array{count: int, total_netto: float, total_quantity: float}
     */
    protected function gradingSummary(string $dateFrom, string $dateTo, ?string $businessUnitId): array
    {
        $query = GradingRecord::query()
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo);

        $this->scopeByBusinessUnit($query, $businessUnitId);

        return [
            'count' => (int) $query->count(),
            'total_netto' => (float) ($query->sum('netto') ?? 0),
            'total_quantity' => (float) ($query->sum('quantity') ?? 0),
        ];
    }

    /**
     * total_cages_tipped = SUM(cages-tipped-time.total_cages) across all
     * rows belonging to cages-track-record headers within the filter — NOT
     * cages_out/cages_tipped on the header itself (see entity-catalog v7's
     * mill-setting.jumlah_cages constraint note this session).
     *
     * @return array{count: int, total_cages_tipped: int}
     */
    protected function cagesTrackSummary(string $dateFrom, string $dateTo, ?string $businessUnitId): array
    {
        $headerQuery = CagesTrackRecord::query()
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo);

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

    protected function scopeByBusinessUnit(Builder $query, ?string $businessUnitId): void
    {
        if ($businessUnitId === null) {
            return;
        }

        $query->whereHas('station', fn (Builder $stationQuery) => $stationQuery->where('business_unit_id', $businessUnitId));
    }
}
