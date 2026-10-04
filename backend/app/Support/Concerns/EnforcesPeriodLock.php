<?php

namespace App\Support\Concerns;

use App\Enums\PeriodStatus;
use App\Exceptions\PeriodClosedException;
use App\Models\Period;
use App\Support\AppTime;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * EnforcesPeriodLock — the Period Pelaporan write lock shared by all 18
 * station *RecordService classes (usecase-141--kunci-input-periode-tertutup).
 *
 * THE RULE IS A WHITELIST. A station write is admitted only when ALL THREE
 * hold:
 *
 *   1. the record's mill has a Periode Pelaporan,
 *   2. that period's row for THIS station type is status='open', and
 *   3. the record's EVENT DATE falls inside that period's range, inclusive at
 *      both ends.
 *
 * Anything else refuses with 422 PERIOD_CLOSED. User's wording, 2026-10-01:
 * "ketika tidak ada periode yang terbuka tidak bisa input data, dan input data
 * hanya bisa pada rentang waktu periode yang terbuka untuk stasiun tersebut".
 *
 * WHY A WHITELIST AND NOT "BLOCK DATES INSIDE A CLOSED PERIOD". The weaker
 * blacklist reading leaves the hole the rule exists to close: with no period at
 * all, or with every period still Draft, data flows in freely and a report that
 * was "closed" can still change afterwards. Note that the four contract tests in
 * tests/Feature/Api/KelolaPeriodePelaporanTest.php were written 2026-09-27
 * against the blacklist reading and one of them asserted the opposite of this
 * rule; it was rewritten when the rule was ratified, not worked around.
 *
 * STATUS IS PER STATION TYPE, NEVER PER PERIOD. Since 2026-09-25 a period holds
 * one row per station type in `period_stations`, each with its own status. One
 * station being open never opens another in the same period, which is why every
 * query below filters on BOTH the station type and the status.
 *
 * THE CHECK USES THE STATUS AT REQUEST TIME, NOT AT EVENT TIME. A record
 * captured offline while the station was open and synced after it closed is
 * refused. That is deliberate (usecase-141 alternative flow "Record tersinkron
 * terlambat dari mobile"): the alternative would be to trust a client-supplied
 * capture time to decide whether a server-side lock applies. Recovery is for an
 * Admin to reopen that station row on screen-142 and the client to resend.
 *
 * WHY whereDate() AND NOT where(). `periods.start_date`/`end_date` are real
 * `date` columns on PostgreSQL, so a bare comparison works there — but SQLite,
 * which the whole suite runs on, returns them as '2026-10-01 00:00:00', making
 * `<= '2026-10-01'` lexicographically FALSE and silently excluding a period on
 * its own first day. Same spelling PeriodService::findOverlapping() uses, and the
 * same trap that bit this project twice on 2026-10-01.
 *
 * WHY `period_stations.status` IS TABLE-QUALIFIED. `status` is not a `periods`
 * column any more and PeriodQueryBuilder refuses the unqualified spelling
 * outright — on purpose, because SQLite would otherwise read it as a
 * string-to-string comparison that is quietly always false.
 */
trait EnforcesPeriodLock
{
    /**
     * Refuses the write unless an open period for this station type admits the
     * event date. Call it BEFORE the first write of a create/update, so a
     * refusal cannot leave a parent row without its details.
     *
     * @param  string  $stationType  station_types.code, e.g. 'sterilizer'
     * @param  string  $businessUnitId  the mill the record's station belongs to
     * @param  string|null  $eventDate  the record's own event timestamp or date
     * @param  string  $action  'write' (simpan/ubah data) atau 'verify'
     *                          (Checked/Acknowledged di layar Detail) — hanya
     *                          mengubah KALIMAT penolakan, bukan aturannya
     *
     * @throws PeriodClosedException
     */
    protected function assertPeriodOpenForWrite(
        string $stationType,
        string $businessUnitId,
        ?string $eventDate,
        string $action = 'write',
    ): void {
        $date = $this->periodLockEventDate($eventDate);

        if ($date === null) {
            // A row with no event date cannot be placed in any period, so no
            // period can admit it. Reachable because `record_datetime` is
            // nullable in the database (the merge migration could not use
            // ->change()), with required-ness enforced only in the service.
            throw new PeriodClosedException(
                'Tanggal kejadian data ini kosong, sehingga tidak dapat ditempatkan pada Periode Pelaporan mana pun.'
            );
        }

        if ($this->openPeriodQuery($stationType, $businessUnitId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists()
        ) {
            return;
        }

        throw new PeriodClosedException(
            $this->periodLockReason($stationType, $businessUnitId, $date, $action)
        );
    }

    /**
     * KUNCI PERIODE UNTUK TANGGAL KEJADIAN PER BARIS DETAIL (CPO Dispatch,
     * Kernel Dispatch, Solid Waste Disposal — tiga stasiun log-kejadian yang
     * barisnya membawa `event_date` sendiri).
     *
     * Sampai 2026-10-04 hanya tanggal HEADER yang diperiksa, sehingga baris
     * bertanggal 15/09/2026 diterima ke record Oktober walau tidak ada periode
     * September yang terbuka. Aturan usecase-141 berlaku untuk SETIAP tanggal
     * kejadian yang ditulis, jadi:
     *
     *  - $newDates: tanggal setiap baris yang dikirim (create dan update);
     *  - $oldDates: tanggal setiap baris yang SUDAH tersimpan (update saja).
     *    upsertDetails() menulis ulang / menghapus semua baris lama, jadi
     *    semuanya ikut diperiksa — sama dengan header: record yang tanggalnya
     *    jatuh di periode tertutup terkunci sebagai satu kesatuan.
     *
     * Pesan penolakannya sama dengan header (422 PERIOD_CLOSED) dan menyebut
     * tanggal baris yang menyebabkan penolakan.
     *
     * @param  iterable<mixed>  $newDates
     * @param  iterable<mixed>  $oldDates
     *
     * @throws PeriodClosedException
     */
    protected function assertDetailEventDatesWritable(
        string $stationType,
        string $businessUnitId,
        iterable $newDates,
        iterable $oldDates = [],
        string $action = 'write',
    ): void {
        $dates = [];

        foreach ([$oldDates, $newDates] as $list) {
            foreach ($list as $value) {
                $date = $value instanceof \DateTimeInterface
                    ? Carbon::instance($value)->toDateString()
                    : $this->periodLockEventDate($value === null ? null : (string) $value);

                if ($date !== null) {
                    $dates[$date] = true;
                }
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        foreach ($dates as $date) {
            try {
                $this->assertPeriodOpenForWrite($stationType, $businessUnitId, $date, $action);
            } catch (PeriodClosedException $e) {
                throw new PeriodClosedException('Baris log bertanggal kejadian '.Carbon::parse($date)->format('d/m/Y').' ditolak. '.$e->getMessage());
            }
        }
    }

    /**
     * Relasi detail yang barisnya membawa `event_date` sendiri, per jenis
     * stasiun. Hanya tiga stasiun log-kejadian ini; stasiun lain memakai
     * tanggal header untuk semua barisnya.
     */
    protected function detailEventDateRelation(string $stationType): ?string
    {
        return match ($stationType) {
            'cpo-dispatch' => 'cpoDispatchDetails',
            'kernel-dispatch' => 'kernelDispatchDetails',
            'solid-waste-disposal' => 'solidWasteDisposalDetails',
            default => null,
        };
    }

    /**
     * BATAS ATAS TANGGAL KEJADIAN (2026-10-04). Tanggal kejadian tidak boleh
     * melewati BESOK di zona aplikasi (lihat AppTime::latestEventDate()).
     * Ditemukan 282 record Weighbridge bertahun 7278 hasil input salah ketik;
     * baris lama itu dibiarkan, hanya tulis BARU yang ditolak — 422 per field,
     * karena ini kesalahan isian, bukan soal periode.
     *
     * @throws ValidationException
     */
    protected function assertEventDateNotTooFarAhead(mixed $eventDate, string $field, string $label = 'Tanggal'): void
    {
        if ($eventDate instanceof \DateTimeInterface) {
            $date = Carbon::instance($eventDate)->toDateString();
        } else {
            $date = $this->periodLockEventDate($eventDate === null ? null : (string) $eventDate);
        }

        if ($date === null) {
            return;
        }

        $limit = AppTime::latestEventDate();

        if ($limit !== null && $date > $limit->toDateString()) {
            throw ValidationException::withMessages([
                $field => sprintf(
                    '%s %s tidak masuk akal: tidak boleh melewati %s.',
                    $label,
                    Carbon::parse($date)->format('d/m/Y'),
                    $limit->format('d/m/Y'),
                ),
            ]);
        }
    }

    /**
     * Normalises whatever the service calls its event field — `date` in 17
     * services, `record_datetime` in Weighbridge — down to Y-m-d. Returns null
     * for an absent or unparseable value rather than guessing a date.
     */
    protected function periodLockEventDate(?string $eventDate): ?string
    {
        if ($eventDate === null || trim($eventDate) === '') {
            return null;
        }

        try {
            // setTimezone(): string ber-offset ("...Z") adalah instan dan
            // harus jatuh ke tanggal WIB-nya; string naif sudah jam WIB.
            return Carbon::parse($eventDate)->setTimezone(AppTime::zone())->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Periods of this mill whose row for this station type is OPEN. */
    protected function openPeriodQuery(string $stationType, string $businessUnitId)
    {
        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn ($stations) => $stations
                ->where('period_stations.station_type', $stationType)
                ->where('period_stations.status', PeriodStatus::Open->value));
    }

    /**
     * The one message that tells the user WHICH of the four reasons refused
     * them. Worth the extra queries: "ditolak" without a reason sends people to
     * the wrong screen, and three of the four reasons are fixed by an Admin on a
     * different screen than the user is standing on.
     */
    protected function periodLockReason(string $stationType, string $businessUnitId, string $date, string $action = 'write'): string
    {
        $formatted = Carbon::parse($date)->format('d/m/Y');
        $verify = $action === 'verify';

        // (a) A period covers this date and HAS a row for this station type —
        // so the refusal is about that row's status, which is the most
        // actionable thing we can say.
        $covering = Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereHas('stations', fn ($stations) => $stations->where('period_stations.station_type', $stationType))
            ->with(['stations' => fn ($stations) => $stations->where('period_stations.station_type', $stationType)])
            ->first();

        if ($covering !== null) {
            $status = $covering->stations->first()?->status;
            $statusValue = $status instanceof PeriodStatus ? $status->value : (string) $status;

            if ($statusValue === PeriodStatus::Closed->value) {
                return sprintf(
                    $verify
                        ? 'Periode "%s" untuk stasiun ini sudah ditutup, sehingga verifikasi data bertanggal %s tidak dapat diubah. Hubungi Admin bila periode itu perlu dibuka kembali.'
                        : 'Periode "%s" untuk stasiun ini sudah ditutup, sehingga data bertanggal %s tidak dapat disimpan atau diubah. Hubungi Admin bila periode itu perlu dibuka kembali.',
                    $covering->name,
                    $formatted,
                );
            }

            return sprintf(
                $verify
                    ? 'Periode "%s" untuk stasiun ini belum dibuka (masih Draft), sehingga verifikasi data bertanggal %s belum dapat diubah. Hubungi Admin untuk membukanya.'
                    : 'Periode "%s" untuk stasiun ini belum dibuka (masih Draft), sehingga data bertanggal %s belum dapat disimpan. Hubungi Admin untuk membukanya.',
                $covering->name,
                $formatted,
            );
        }

        // (b) There IS an open period for this station, just not covering this
        // date. Naming the range turns "ditolak" into something the user can
        // act on without leaving the form.
        $openRanges = $this->openPeriodQuery($stationType, $businessUnitId)
            ->orderBy('start_date')
            ->limit(3)
            ->get(['name', 'start_date', 'end_date']);

        if ($openRanges->isNotEmpty()) {
            $ranges = $openRanges
                ->map(fn (Period $period) => sprintf(
                    '"%s" (%s s/d %s)',
                    $period->name,
                    optional($period->start_date)->format('d/m/Y'),
                    optional($period->end_date)->format('d/m/Y'),
                ))
                ->implode(', ');

            return sprintf(
                $verify
                    ? 'Tanggal %s berada di luar periode yang terbuka untuk stasiun ini, sehingga verifikasinya tidak dapat diubah. Periode yang terbuka saat ini: %s.'
                    : 'Tanggal %s berada di luar periode yang terbuka untuk stasiun ini. Periode yang menerima data saat ini: %s.',
                $formatted,
                $ranges,
            );
        }

        // (c) Nothing open for this station at all.
        return sprintf(
            $verify
                ? 'Belum ada Periode Pelaporan yang terbuka untuk stasiun ini, sehingga verifikasi data bertanggal %s tidak dapat diubah. Hubungi Admin untuk membuka periodenya.'
                : 'Belum ada Periode Pelaporan yang terbuka untuk stasiun ini, sehingga data bertanggal %s belum dapat disimpan. Hubungi Admin untuk membuka periodenya.',
            $formatted,
        );
    }
}
