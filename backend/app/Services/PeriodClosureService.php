<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodNotClosedException;
use App\Exceptions\PeriodNotDraftException;
use App\Models\Period;
use App\Models\PeriodStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * PeriodClosureService — screen-128--kelola-periode-pelaporan /
 * usecase-140--tutup-buka-periode-pelaporan (Tutup & Buka Kembali Periode
 * Pelaporan).
 *
 * Split out of PeriodService on purpose. Closing is not CRUD: it has its own
 * concurrency contract (a conditional UPDATE, see close()) and it is the only
 * part of this screen that has to know about the 18 station record tables.
 * Keeping it here leaves PeriodService a plain master-data service.
 *
 * EVERY ACTION HERE TARGETS ONE (PERIOD, STATION TYPE) PAIR, NOT A PERIOD
 * (keputusan user 2026-09-25). Status moved out of `periods` into
 * `period_stations` because stations do not finish at the same time: an Admin
 * closes Sterilizer while Clarification is still open. So close(), reopen(),
 * open() and unverifiedCount() all act on ONE row of `period_stations`.
 *
 * WHY THEY ALL TAKE A SINGLE `$periodStationId` AND NOT ($periodId,
 * $stationType) — the signature was picked to make the step-4 caller hard to
 * get wrong:
 *
 *  1. Two same-typed string parameters can be swapped silently; one opaque id
 *     cannot. A swapped (type, id) pair would look like a plain 404, which is
 *     also what a genuinely missing period looks like.
 *  2. A pair can point at a combination that does not exist (a station type
 *     the period has no row for), which would need its own third error case
 *     on top of 404/409. An id either resolves to a row or it does not.
 *  3. The confirm dialog and the action it confirms now share ONE identifier:
 *     the screen reads the count with unverifiedCount($id) and then posts
 *     close($id) with the very same value, so the warning can never be about
 *     a different station than the one being closed.
 *
 * The id itself comes straight out of PeriodService::toRow()'s
 * `stations[].id` — see that method's contract.
 *
 * WHAT CLOSING MEANS — this service only sets `period_stations.status`. The
 * actual lock (refusing to insert/update/verify a station record whose event
 * date falls inside a closed period AND whose station type is the closed one)
 * is NOT implemented here and NOT anywhere on screen-128: it belongs in each
 * of the 18 *RecordService classes and in the mobile sync path, tracked by
 * usecase-141--kunci-input-periode-tertutup. See the screen tech-spec's
 * implementation_notes — that separation is deliberate, not an oversight.
 *
 * TODO: Penegakan kunci periode tertutup (422 code=PERIOD_CLOSED pada
 * create/update/verifikasi record stasiun, dan pada jalur sync mobile) —
 * DEFERRED: berada di luar screen-128. Layar ini hanya menetapkan status per
 * jenis stasiun; penolakannya harus ditambahkan ke ke-18 *RecordService dan ke
 * jalur sync mobile, dilacak oleh usecase-141--kunci-input-periode-tertutup
 * (Phase 2 mencatat 15 layar Form & Data Preview yang terdampak untuk tahap
 * pertama). Bentuk kuerinya kini JOIN induk-anak — lihat docblock Period.
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
     * findOrFail the `period_stations` row → 404 → COUNT records of THAT
     * station type joined to `stations` filtered by the parent period's
     * mill, whose event date falls inside [start_date, end_date] INCLUSIVE
     * and whose verification is incomplete.
     *
     * SCOPED TO THE STATION TYPE BEING CLOSED, NOT TO THE WHOLE PERIOD
     * (keputusan user 2026-09-25). Closing Sterilizer does not touch
     * Clarification's records, so counting them in Sterilizer's warning
     * would tell the Admin a number that has nothing to do with the action
     * they are confirming — and would keep the warning permanently non-zero
     * on a busy mill, training them to click past it.
     *
     * The `{unverified_count, breakdown}` shape is kept even though the
     * breakdown now holds at most ONE entry: the API response and the
     * screen's dialog already read it that way, and a single-entry list
     * still carries the station type the number belongs to.
     *
     * "Unverified" is `checked_by IS NULL OR acknowledged_by IS NULL` —
     * either verification stage still empty (tech-spec implementation_note:
     * an agent-derived definition, not stated in the business spec).
     *
     * Computed on demand, only when the Admin opens the close-confirmation
     * dialog.
     *
     * @return array{unverified_count: int, breakdown: list<array{station_type: string, count: int}>}
     *
     * @throws ModelNotFoundException
     */
    public function unverifiedCount(string $periodStationId): array
    {
        $station = $this->findStation($periodStationId);

        $count = $this->countUnverifiedForStationType($station->period, $station->station_type);

        return [
            'unverified_count' => $count,
            'breakdown' => $count > 0
                ? [['station_type' => $station->station_type, 'count' => $count]]
                : [],
        ];
    }

    /**
     * close() — business_logic steps "close", for ONE station type of one
     * period.
     *
     * THE CONDITIONAL UPDATE IS THE ENTIRE CONCURRENCY STORY, now on the
     * child table:
     *
     *   UPDATE period_stations
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
     * The findOrFail() above it exists only to tell 404 (no such station
     * row) apart from 409 (exists, already closed) — it is not the guard.
     *
     * `periods.updated_by` is stamped AFTER a winning UPDATE, never before:
     * the loser of a race must leave no trace at all. The audit of WHO
     * closed WHAT lives in `period_stations.closed_by`; the parent stamp
     * only answers "who last touched this period".
     *
     * @return array{period_station_id: string, period_id: string, station_type: string, station_type_label: string, status: string, closed_by: string|null, closed_by_name: string|null, closed_at: string|null}
     *
     * @throws ModelNotFoundException
     * @throws PeriodAlreadyClosedException
     */
    public function close(string $periodStationId): array
    {
        $station = $this->findStation($periodStationId);

        $actorId = auth()->id();
        $closedAt = now();

        $affected = PeriodStation::query()
            ->where('id', $periodStationId)
            ->where('status', '<>', PeriodStatus::Closed->value)
            ->update([
                'status' => PeriodStatus::Closed->value,
                'closed_by' => $actorId,
                'closed_at' => $closedAt,
            ]);

        if ($affected === 0) {
            throw new PeriodAlreadyClosedException($this->alreadyClosedMessage($periodStationId));
        }

        $this->touchPeriod($station->period_id, $actorId);

        return $this->toStatusRow($periodStationId);
    }

    /**
     * reopen() — business_logic steps "reopen", for ONE station type:
     * findOrFail → 404 → refuse with 409 PERIOD_NOT_CLOSED unless THAT
     * station row is actually closed → UPDATE status='open' and clear BOTH
     * closure columns, so the record of who closed it and when is dropped
     * exactly as the business spec requires.
     *
     * Reopening Sterilizer leaves every other station row of the period
     * untouched — that is the whole point of the split.
     *
     * @return array{period_station_id: string, period_id: string, station_type: string, station_type_label: string, status: string, closed_by: string|null, closed_by_name: string|null, closed_at: string|null}
     *
     * @throws ModelNotFoundException
     * @throws PeriodNotClosedException
     */
    public function reopen(string $periodStationId): array
    {
        $station = $this->findStation($periodStationId);

        if ($this->statusValue($station) !== PeriodStatus::Closed->value) {
            throw new PeriodNotClosedException;
        }

        $station->forceFill([
            'status' => PeriodStatus::Open->value,
            'closed_by' => null,
            'closed_at' => null,
        ])->save();

        $this->touchPeriod($station->period_id, auth()->id());

        return $this->toStatusRow($periodStationId);
    }

    /**
     * open() — business_logic steps "open" (usecase-144). Draft → open for
     * ONE station type: the one conscious step that declares this station
     * officially running inside the period.
     *
     * SAME CONDITIONAL-UPDATE CONTRACT AS close(), on the child table:
     *
     *   UPDATE period_stations
     *      SET status='open'
     *    WHERE id=? AND status='draft'
     *
     * plus a check of the affected row count. The WHERE clause IS the
     * guard — no lockForUpdate(), no extra transaction, and deliberately
     * not a save() on the already-loaded model. With two Admins confirming
     * the same opening at the same moment, exactly one statement matches a
     * still-draft row; the loser gets affected=0 → 409 PERIOD_NOT_DRAFT
     * rather than a silent overwrite.
     *
     * The findOrFail() + status check above it exist only to tell 404 (no
     * such station row) and a plainly wrong status apart from the race —
     * they are not the guard.
     *
     * NOT SYMMETRIC WITH close(): close() counts unverified station
     * records, so it is tempting to give open() a matching sweep. It has
     * none. Opening reads, validates and mutates NOTHING in any
     * `*_records` table.
     *
     * A station left 'open' also keeps its period fully editable and
     * deletable: PeriodService::update()/delete() refuse only when at least
     * one station row is 'closed', and that is intentional — do not extend
     * the lock to 'open'.
     *
     * @return array{period_station_id: string, period_id: string, station_type: string, station_type_label: string, status: string, closed_by: string|null, closed_by_name: string|null, closed_at: string|null}
     *
     * @throws ModelNotFoundException
     * @throws PeriodNotDraftException
     */
    public function open(string $periodStationId): array
    {
        $station = $this->findStation($periodStationId);

        $status = $this->statusValue($station);

        if ($status !== PeriodStatus::Draft->value) {
            throw new PeriodNotDraftException($this->notDraftMessage($status));
        }

        $actorId = auth()->id();

        $affected = PeriodStation::query()
            ->where('id', $periodStationId)
            ->where('status', PeriodStatus::Draft->value)
            ->update(['status' => PeriodStatus::Open->value]);

        if ($affected === 0) {
            // Another Admin got there first between the read above and
            // this statement. Re-read the row so the message names the
            // status it actually has now.
            $current = PeriodStation::query()->find($periodStationId);

            throw new PeriodNotDraftException(
                $this->notDraftMessage($current !== null ? $this->statusValue($current) : null)
            );
        }

        $this->touchPeriod($station->period_id, $actorId);

        return $this->toStatusRow($periodStationId);
    }

    /**
     * The shared response shape of close()/reopen()/open() — identical for
     * all three on purpose, so the three step-4 handlers differ only in the
     * method they call. It names the child row explicitly
     * (`period_station_id`) and carries `period_id` alongside: a bare `id`
     * key here would be read as the period's id by the first caller that
     * copies an old snippet.
     *
     * @return array{period_station_id: string, period_id: string, station_type: string, station_type_label: string, status: string, closed_by: string|null, closed_by_name: string|null, closed_at: string|null}
     */
    protected function toStatusRow(string $periodStationId): array
    {
        /** @var PeriodStation $station */
        $station = PeriodStation::query()
            ->with(['closedBy', 'stationTypeRef'])
            ->findOrFail($periodStationId);

        return [
            'period_station_id' => $station->id,
            'period_id' => $station->period_id,
            'station_type' => $station->station_type,
            'station_type_label' => optional($station->stationTypeRef)->name ?? $station->station_type,
            'status' => $this->statusValue($station),
            'closed_by' => $station->closed_by,
            'closed_by_name' => optional($station->closedBy)->name,
            'closed_at' => optional($station->closed_at)->toIso8601String(),
        ];
    }

    /**
     * The station row plus its parent period — the period is always needed
     * (mill + date range for the count, id for the parent stamp), so it is
     * eager-loaded rather than lazily hit per call.
     *
     * @throws ModelNotFoundException
     */
    protected function findStation(string $periodStationId): PeriodStation
    {
        /** @var PeriodStation $station */
        $station = PeriodStation::query()
            ->with('period')
            ->findOrFail($periodStationId);

        return $station;
    }

    /**
     * Stamps `periods.updated_by` (and updated_at) after a successful
     * status change on one of its station rows. `period_stations` has no
     * updated_by column of its own, and dropping this stamp would lose the
     * "who last touched this period" audit the screen already showed.
     */
    protected function touchPeriod(string $periodId, ?string $actorId): void
    {
        Period::query()
            ->where('id', $periodId)
            ->update(['updated_by' => $actorId]);
    }

    /**
     * COUNT of unverified records of one station type inside the period's
     * range. Returns 0 for a station type that has no record table at all
     * (e.g. the historical `other` type) — such a type simply contributes
     * nothing instead of blowing up.
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
     * The refusal message for open() on a non-draft station row.
     *
     * "Buka Stasiun" (this action) and "Buka Kembali" (reopen()) are easy
     * to mix up, so the two cases must NOT share a message: an already-open
     * station is simply told so, while a closed one is pointed at the other
     * action by name — refusing a closed station without that pointer makes
     * Admins think the period is broken.
     */
    protected function notDraftMessage(?string $status): string
    {
        return match ($status) {
            PeriodStatus::Open->value => 'Stasiun ini sudah terbuka pada periode ini, sehingga tidak perlu dibuka lagi.',
            PeriodStatus::Closed->value => 'Stasiun ini sudah tertutup pada periode ini, sehingga tidak dapat dibuka dengan aksi ini. Gunakan aksi "Buka Kembali Periode" bila ingin membukanya lagi.',
            default => 'Stasiun ini tidak berstatus Draft pada periode ini, sehingga tidak dapat dibuka.',
        };
    }

    /**
     * The loser of a close race needs to know their action did not take
     * effect AND who actually closed this station — so the message names the
     * first closer and the time, falling back to the exception's own
     * default when that row is no longer readable.
     */
    protected function alreadyClosedMessage(string $periodStationId): string
    {
        $station = PeriodStation::query()->with('closedBy')->find($periodStationId);

        if ($station === null || $station->closed_at === null) {
            return 'Stasiun ini sudah ditutup pada periode ini.';
        }

        $closerName = optional($station->closedBy)->name;

        return $closerName === null
            ? sprintf('Stasiun ini sudah ditutup pada %s.', $station->closed_at->format('d/m/Y H:i'))
            : sprintf(
                'Stasiun ini sudah ditutup oleh %s pada %s.',
                $closerName,
                $station->closed_at->format('d/m/Y H:i')
            );
    }

    protected function statusValue(PeriodStation $station): ?string
    {
        return $station->status instanceof PeriodStatus
            ? $station->status->value
            : $station->status;
    }
}
