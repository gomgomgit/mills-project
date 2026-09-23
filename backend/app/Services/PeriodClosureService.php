<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodNotClosedException;
use App\Models\Period;
use App\Models\StationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * PeriodClosureService — screen-128--kelola-periode-pelaporan /
 * usecase-140--tutup-buka-periode-pelaporan (Tutup & Buka Kembali Periode
 * Pelaporan).
 *
 * Split out of PeriodService on purpose. Closing a period is not CRUD: it
 * has its own concurrency contract (a conditional UPDATE, see close()) and
 * it is the only part of this screen that has to know about the 18 station
 * record tables. Keeping it here leaves PeriodService a plain master-data
 * service.
 *
 * WHAT CLOSING MEANS — this service only sets `periods.status`. The actual
 * lock (refusing to insert/update/verify a station record whose event date
 * falls inside a closed period) is NOT implemented here and NOT anywhere
 * on screen-128: it belongs in each of the 18 *RecordService classes and
 * in the mobile sync path, tracked by
 * usecase-141--kunci-input-periode-tertutup. See the screen tech-spec's
 * implementation_notes — that separation is deliberate, not an oversight.
 *
 * TODO: Penegakan kunci periode tertutup (422 code=PERIOD_CLOSED pada
 * create/update/verifikasi record stasiun, dan pada jalur sync mobile) —
 * DEFERRED: berada di luar screen-128. Layar ini hanya menetapkan status
 * periode; penolakannya harus ditambahkan ke ke-18 *RecordService dan ke
 * jalur sync mobile, dilacak oleh usecase-141--kunci-input-periode-tertutup
 * (Phase 2 mencatat 15 layar Form & Data Preview yang terdampak untuk tahap
 * pertama).
 */
class PeriodClosureService
{
    /**
     * The event-date column per station type. Every station record table
     * uses `date` EXCEPT weighbridge, whose event timestamp is
     * `record_datetime` (added by
     * 2026_08_19_000010_add_weighbridge_type_and_record_datetime_to_weighbridge_records_table).
     * Period membership always uses the event date — never created_at,
     * never the sync time — so a whereDate() comparison against this
     * column is the whole rule.
     *
     * @var array<string, string>
     */
    protected const RECORD_DATE_COLUMN_OVERRIDES = [
        'weighbridge' => 'record_datetime',
    ];

    protected const DEFAULT_RECORD_DATE_COLUMN = 'date';

    /**
     * RecordVerificationService already owns the ONLY station-type =>
     * record-model map in this codebase (an explicit whitelist, never a
     * concatenated class name). Reused rather than duplicated: a 19th
     * station type gains an unverified-count the moment it is added there.
     */
    public function __construct(protected RecordVerificationService $verification) {}

    /**
     * unverifiedCount() — business_logic steps "unverifiedCount":
     * findOrFail → 404 → resolve the covered station types ([the period's
     * own type] when set, every ACTIVE type from the `station_types`
     * master table when NULL) → per type, COUNT records joined to
     * `stations` filtered by the period's mill and that type, whose event
     * date falls inside [start_date, end_date] INCLUSIVE and whose
     * verification is incomplete → breakdown of the non-zero types plus
     * the total.
     *
     * "Unverified" is `checked_by IS NULL OR acknowledged_by IS NULL` —
     * either verification stage still empty (tech-spec implementation_note:
     * an agent-derived definition, not stated in the business spec).
     *
     * Computed on demand, only when the Admin opens the close-confirmation
     * dialog — it touches up to 18 tables, so the list endpoint
     * deliberately does NOT carry this figure.
     *
     * @return array{unverified_count: int, breakdown: list<array{station_type: string, count: int}>}
     *
     * @throws ModelNotFoundException
     */
    public function unverifiedCount(string $id): array
    {
        $period = Period::findOrFail($id);

        $breakdown = [];
        $total = 0;

        foreach ($this->coveredStationTypes($period) as $stationType) {
            $count = $this->countUnverifiedForStationType($period, $stationType);

            if ($count > 0) {
                $breakdown[] = [
                    'station_type' => $stationType,
                    'count' => $count,
                ];
                $total += $count;
            }
        }

        return [
            'unverified_count' => $total,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * close() — business_logic steps "close".
     *
     * THE CONDITIONAL UPDATE IS THE ENTIRE CONCURRENCY STORY:
     *
     *   UPDATE periods
     *      SET status='closed', closed_by=?, closed_at=?
     *    WHERE id=? AND status <> 'closed'
     *
     * and then a check of the affected row count. This is deliberately NOT
     * read-then-write: with two Admins confirming the same closure at the
     * same moment, exactly one statement matches a non-closed row, and the
     * loser gets affected=0 → 409 PERIOD_ALREADY_CLOSED. The first
     * closer's closed_by/closed_at are therefore never overwritten,
     * without any explicit locking or transaction.
     *
     * The findOrFail() above it exists only to tell 404 (no such period)
     * apart from 409 (exists, already closed) — it is not the guard.
     *
     * @return array{id: string, status: string, closed_by: string|null, closed_by_name: string|null, closed_at: string|null}
     *
     * @throws ModelNotFoundException
     * @throws PeriodAlreadyClosedException
     */
    public function close(string $id): array
    {
        Period::query()->findOrFail($id);

        $actorId = auth()->id();
        $closedAt = now();

        $affected = Period::query()
            ->where('id', $id)
            ->where('status', '<>', PeriodStatus::Closed->value)
            ->update([
                'status' => PeriodStatus::Closed->value,
                'closed_by' => $actorId,
                'closed_at' => $closedAt,
                'updated_by' => $actorId,
            ]);

        if ($affected === 0) {
            throw new PeriodAlreadyClosedException($this->alreadyClosedMessage($id));
        }

        $period = Period::query()->with('closedBy')->findOrFail($id);

        return [
            'id' => $period->id,
            'status' => $this->statusValue($period),
            'closed_by' => $period->closed_by,
            'closed_by_name' => optional($period->closedBy)->name,
            'closed_at' => optional($period->closed_at)->toIso8601String(),
        ];
    }

    /**
     * reopen() — business_logic steps "reopen": findOrFail → 404 → refuse
     * with 409 PERIOD_NOT_CLOSED unless the period is actually closed →
     * UPDATE status='open' and clear BOTH closure columns, so the record
     * of who closed it and when is dropped exactly as the business spec
     * requires.
     *
     * @return array{id: string, status: string, closed_by: null, closed_at: null}
     *
     * @throws ModelNotFoundException
     * @throws PeriodNotClosedException
     */
    public function reopen(string $id): array
    {
        $period = Period::findOrFail($id);

        if ($this->statusValue($period) !== PeriodStatus::Closed->value) {
            throw new PeriodNotClosedException;
        }

        $period->forceFill([
            'status' => PeriodStatus::Open->value,
            'closed_by' => null,
            'closed_at' => null,
            'updated_by' => auth()->id(),
        ])->save();

        $period->refresh();

        return [
            'id' => $period->id,
            'status' => $this->statusValue($period),
            'closed_by' => $period->closed_by,
            'closed_at' => optional($period->closed_at)->toIso8601String(),
        ];
    }

    /**
     * The station types a period covers: its own when set, otherwise every
     * ACTIVE row of the `station_types` master table in process order.
     * Read from the table, never from App\Enums\StationType — adding a
     * station type is an INSERT, and this list must follow it.
     *
     * @return list<string>
     */
    public function coveredStationTypes(Period $period): array
    {
        if ($period->station_type !== null) {
            return [$period->station_type];
        }

        return StationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    /**
     * COUNT of unverified records of one station type inside the period's
     * range. Returns 0 for a station type that has no record table at all
     * (e.g. the historical `other` type) — such a type simply contributes
     * nothing to the total instead of blowing up.
     */
    protected function countUnverifiedForStationType(Period $period, string $stationType): int
    {
        /** @var class-string<Model>|null $modelClass */
        $modelClass = $this->verification->modelForStationType($stationType);

        if ($modelClass === null) {
            return 0;
        }

        $table = (new $modelClass)->getTable();
        $dateColumn = self::RECORD_DATE_COLUMN_OVERRIDES[$stationType] ?? self::DEFAULT_RECORD_DATE_COLUMN;

        return $modelClass::query()
            ->join('stations', 'stations.id', '=', $table.'.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', $stationType)
            // Inclusive on both bounds — a record dated exactly on
            // start_date or end_date is inside the period.
            ->whereDate($table.'.'.$dateColumn, '>=', $period->start_date->toDateString())
            ->whereDate($table.'.'.$dateColumn, '<=', $period->end_date->toDateString())
            ->where(function ($query) use ($table) {
                $query->whereNull($table.'.checked_by')
                    ->orWhereNull($table.'.acknowledged_by');
            })
            ->count($table.'.id');
    }

    /**
     * The loser of a close race needs to know their action did not take
     * effect AND who actually closed the period — so the message names the
     * first closer and the time, falling back to the exception's own
     * default when that row is no longer readable.
     */
    protected function alreadyClosedMessage(string $id): string
    {
        $period = Period::query()->with('closedBy')->find($id);

        if ($period === null || $period->closed_at === null) {
            return 'Periode ini sudah ditutup.';
        }

        $closerName = optional($period->closedBy)->name;

        return $closerName === null
            ? sprintf('Periode ini sudah ditutup pada %s.', $period->closed_at->format('d/m/Y H:i'))
            : sprintf(
                'Periode ini sudah ditutup oleh %s pada %s.',
                $closerName,
                $period->closed_at->format('d/m/Y H:i')
            );
    }

    protected function statusValue(Period $period): ?string
    {
        return $period->status instanceof PeriodStatus
            ? $period->status->value
            : $period->status;
    }
}
