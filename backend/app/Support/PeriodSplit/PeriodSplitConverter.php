<?php

namespace App\Support\PeriodSplit;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Konversi `periods` model LAMA (satu baris per mill+jenis stasiun, dengan
 * status tutup/bukanya sendiri) menjadi model BARU (satu baris `periods` per
 * mill+rentang tanggal, dengan satu baris `period_stations` per jenis
 * stasiun). Dipakai oleh migrasi 2026_09_26_000035 dan hanya oleh itu.
 *
 * MENGAPA LOGIKANYA DI SINI, BUKAN DI DALAM BERKAS MIGRASINYA
 * Aturan konversinya bercabang enam (gabung, warisi penutupan, mekarkan
 * cakupan NULL, pilih nama induk, de-duplikasi nama, tolak irisan parsial) dan
 * masing-masing punya kasus gagal yang mahal. Menguji cabang-cabang itu lewat
 * migrasi mustahil: di DB test tabel `periods` kosong saat migrasi berjalan,
 * dan begitu seluruh rangkaian selesai kolom `station_type/status/closed_by/
 * closed_at` sudah dibuang migrasi 000040, sehingga baris berbentuk lama tidak
 * bisa dibuat lagi untuk diuji. Dengan {@see plan()} yang MURNI — masuk array
 * baris, keluar {@see PeriodSplitPlan}, tanpa satu pun kueri — seluruh aturan
 * dapat diuji sebagai fungsi biasa, dan {@see apply()} yang menulis tinggal
 * memuat INSERT/UPDATE/DELETE tanpa keputusan apa pun di dalamnya.
 *
 * KELAS SEKALI PAKAI. Setelah setiap DB (dev, staging, produksi) melewati
 * migrasi 000035, kelas ini, testnya, dan migrasinya boleh dihapus bersama.
 *
 * ATURAN KONVERSI (keputusan user 2026-09-26, final)
 *  1. Baris lama digabung menurut (business_unit_id, start_date, end_date)
 *     menjadi SATU induk + N baris `period_stations`.
 *  2. Tiap baris lama menjadi satu baris anak yang mempertahankan
 *     station_type, status, closed_by, closed_at-nya — catatan penutupan tidak
 *     boleh hilang.
 *  3. Baris lama ber-station_type NULL ("berlaku semua jenis") mekar menjadi
 *     satu baris anak untuk SETIAP jenis stasiun aktif di mill itu, mewarisi
 *     statusnya. Daftar jenis aktif TIDAK dihitung di sini: ia disuntikkan
 *     lewat $activeStationTypesByMill, yang migrasinya isi dari
 *     PeriodService::activeStationTypesForMill() — satu-satunya tempat aturan
 *     "aktif di mill ini" boleh tinggal.
 *  4. Induk hasil gabungan adalah BARIS TERTUA di grupnya (created_at, lalu id
 *     sebagai pemecah seri), sehingga name/created_by/updated_by/created_at/
 *     updated_at terwarisi apa adanya tanpa penyalinan. Nama baris lain yang
 *     berbeda dicatat di notes sebagai nama yang dibuang.
 *  5. Setelah penggabungan, dua induk di mill yang sama dengan nama sama
 *     diselesaikan dengan mengimbuhi rentang tanggal pada yang belakangan
 *     (urutan start_date, end_date, id) dan dicatat — kalau tidak, migrasi
 *     000040 gagal saat membuat unique (business_unit_id, name), di tengah
 *     rangkaian.
 *  6. Rentang yang beririsan SEBAGIAN di mill yang sama (sah di model lama
 *     karena jenis stasiunnya beda, dilarang di model baru) tidak punya
 *     jawaban otomatis yang benar: plan() MELEMPAR dan menyebut pasangannya.
 *     Tidak menebak, tidak menggabung paksa.
 */
final class PeriodSplitConverter
{
    /** Lebar kolom `periods`.`name` — batas hasil de-duplikasi nama. */
    public const NAME_MAX_LENGTH = 255;

    /**
     * Urutan kekuatan status saat dua baris lama bertabrakan pada jenis
     * stasiun yang sama di dalam satu grup (mis. satu baris eksplisit
     * 'sterilizer' dan satu baris NULL yang mekar ke 'sterilizer' juga).
     * Yang paling tertutup menang, karena aturan 2 melarang catatan penutupan
     * hilang — menurunkan 'closed' jadi 'draft' akan membuka kembali periode
     * yang sudah dikunci tanpa ada yang tahu.
     */
    private const STATUS_RANK = ['draft' => 1, 'open' => 2, 'closed' => 3];

    /**
     * Hitung seluruh perubahan tanpa menyentuh DB.
     *
     * @param  list<array<string, mixed>|object>  $periods  isi tabel `periods` berbentuk lama
     * @param  array<string, list<string>>  $activeStationTypesByMill  business_unit_id => list kode jenis stasiun aktif
     *
     * @throws RuntimeException bila ada irisan rentang parsial, atau bila sebuah grup
     *                          tidak menghasilkan satu pun baris anak
     */
    public function plan(array $periods, array $activeStationTypesByMill = []): PeriodSplitPlan
    {
        if ($periods === []) {
            return new PeriodSplitPlan;
        }

        $rows = array_map(fn ($row) => $this->normalise($row), array_values($periods));

        $this->guardPartialOverlaps($rows);

        $notes = [];
        $stationRows = [];
        $deletedPeriodIds = [];
        $survivors = [];
        $childless = [];

        foreach ($this->groupByRange($rows) as $group) {
            usort($group, fn (array $a, array $b) => $this->compareByAge($a, $b));
            $survivor = $group[0];
            $merged = array_slice($group, 1);

            if ($merged !== []) {
                $notes[] = sprintf(
                    'GABUNG: %d baris periods pada mill %s rentang %s..%s menjadi satu induk %s "%s"; baris yang dilebur: %s.',
                    count($group),
                    $survivor['business_unit_id'],
                    $survivor['start_date'],
                    $survivor['end_date'],
                    $survivor['id'],
                    $survivor['name'],
                    implode(', ', array_map(
                        fn (array $r) => $r['id'].' ('.($r['station_type'] ?? 'SEMUA STASIUN').')',
                        $merged
                    ))
                );

                $droppedNames = [];
                foreach ($merged as $row) {
                    if ($row['name'] !== $survivor['name']) {
                        $droppedNames[$row['name']] = $row['id'];
                    }
                }

                if ($droppedNames !== []) {
                    $notes[] = sprintf(
                        'NAMA DIBUANG: induk %s memakai nama baris tertua "%s"; nama berikut ikut lebur dan tidak terpakai lagi: %s.',
                        $survivor['id'],
                        $survivor['name'],
                        implode(', ', array_map(
                            fn (string $name, string $id) => '"'.$name.'" (baris '.$id.')',
                            array_keys($droppedNames),
                            array_values($droppedNames)
                        ))
                    );
                }

                foreach ($merged as $row) {
                    $deletedPeriodIds[] = $row['id'];
                }
            }

            $children = [];

            foreach ($group as $row) {
                $expanded = $row['station_type'] === null;
                $types = $expanded
                    ? ($activeStationTypesByMill[$row['business_unit_id']] ?? [])
                    : [$row['station_type']];

                if ($expanded) {
                    $notes[] = sprintf(
                        'MEKAR: baris %s "%s" bercakupan SEMUA STASIUN (station_type NULL) menjadi %d baris period_stations berstatus %s: %s.',
                        $row['id'],
                        $row['name'],
                        count($types),
                        $row['status'],
                        $types === [] ? '(tidak ada jenis stasiun aktif di mill ini)' : implode(', ', $types)
                    );
                }

                foreach ($types as $type) {
                    $candidate = [
                        'period_id' => $survivor['id'],
                        'station_type' => $type,
                        'status' => $row['status'],
                        'closed_by' => $row['closed_by'],
                        'closed_at' => $row['closed_at'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ];

                    if (! isset($children[$type])) {
                        $children[$type] = ['row' => $candidate, 'explicit' => ! $expanded, 'source' => $row['id']];

                        continue;
                    }

                    $held = $children[$type];
                    $winner = $this->pickStrongest($held, ['row' => $candidate, 'explicit' => ! $expanded, 'source' => $row['id']]);

                    $notes[] = sprintf(
                        'TABRAKAN: jenis stasiun "%s" pada induk %s berasal dari dua baris lama (%s status %s, dan %s status %s); dipakai status %s dari baris %s.',
                        $type,
                        $survivor['id'],
                        $held['source'],
                        $held['row']['status'],
                        $row['id'],
                        $row['status'],
                        $winner['row']['status'],
                        $winner['source']
                    );

                    $children[$type] = $winner;
                }
            }

            if ($children === []) {
                $childless[] = $group;

                continue;
            }

            $survivors[] = $survivor;

            foreach ($children as $child) {
                $stationRows[] = $child['row'];
            }
        }

        if ($childless !== []) {
            throw new RuntimeException($this->childlessMessage($childless));
        }

        [$renames, $renameNotes] = $this->resolveNameClashes($survivors);

        return new PeriodSplitPlan(
            stationRows: $stationRows,
            renames: $renames,
            deletedPeriodIds: $deletedPeriodIds,
            survivorIds: array_map(fn (array $r) => $r['id'], $survivors),
            notes: array_merge($notes, $renameNotes),
        );
    }

    /**
     * Tulis rencana ke DB dalam satu transaksi. Tidak ada keputusan di sini —
     * semuanya sudah diputuskan {@see plan()}.
     *
     * Urutannya wajib: INSERT anak dulu (anak menempel pada induk yang
     * bertahan), baru DELETE induk yang lebur — membalik urutannya tidak
     * mengubah hasil, tetapi menaruh DELETE lebih dulu membuat jendela di mana
     * catatan penutupan hanya ada di memori proses ini.
     */
    public function apply(PeriodSplitPlan $plan): void
    {
        if ($plan->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($plan) {
            $now = now()->format('Y-m-d H:i:s');

            foreach (array_chunk($plan->stationRows, 200) as $chunk) {
                DB::table('period_stations')->insert(array_map(fn (array $row) => [
                    'id' => (string) Str::orderedUuid(),
                    'period_id' => $row['period_id'],
                    'station_type' => $row['station_type'],
                    'status' => $row['status'],
                    'closed_by' => $row['closed_by'],
                    'closed_at' => $row['closed_at'],
                    // Timestamp baris lamanya, bukan waktu migrasi: baris anak
                    // ini adalah baris lama itu, dalam bentuk baru.
                    'created_at' => $row['created_at'] ?? $now,
                    'updated_at' => $row['updated_at'] ?? $now,
                ], $chunk));
            }

            foreach ($plan->renames as $rename) {
                DB::table('periods')->where('id', $rename['id'])->update(['name' => $rename['to']]);
            }

            if ($plan->deletedPeriodIds !== []) {
                DB::table('periods')->whereIn('id', $plan->deletedPeriodIds)->delete();
            }
        });
    }

    /**
     * Aturan 6. Dua rentang yang TIDAK identik tetapi bersinggungan di mill
     * yang sama tidak dapat digabung (rentangnya beda) maupun dibiarkan
     * (model baru melarang dua periode mill yang sama saling menimpa, karena
     * satu tanggal record akan cocok dengan dua periode dan tidak ada jawaban
     * atas "periode mana yang menguncinya"). Seluruh pasangan dikumpulkan
     * dulu, lalu dilempar sekaligus — operator yang harus membereskannya
     * manual lebih butuh daftar lengkap daripada satu pasang per percobaan.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function guardPartialOverlaps(array $rows): void
    {
        $conflicts = [];

        foreach ($this->groupByMill($rows) as $millId => $millRows) {
            $ranges = [];
            foreach ($millRows as $row) {
                $ranges[$row['start_date'].'|'.$row['end_date']][] = $row;
            }

            $keys = array_keys($ranges);
            sort($keys);

            for ($i = 0; $i < count($keys); $i++) {
                for ($j = $i + 1; $j < count($keys); $j++) {
                    [$aStart, $aEnd] = explode('|', $keys[$i]);
                    [$bStart, $bEnd] = explode('|', $keys[$j]);

                    if ($aStart > $bEnd || $bStart > $aEnd) {
                        continue;
                    }

                    $conflicts[] = sprintf(
                        'mill %s: %s..%s [%s] beririsan dengan %s..%s [%s]',
                        $millId,
                        $aStart, $aEnd, $this->describeRows($ranges[$keys[$i]]),
                        $bStart, $bEnd, $this->describeRows($ranges[$keys[$j]])
                    );
                }
            }
        }

        if ($conflicts === []) {
            return;
        }

        throw new RuntimeException(
            'Konversi periods -> period_stations dihentikan: ditemukan '.count($conflicts)
            .' pasang rentang periode yang beririsan sebagian di mill yang sama. Di model lama '
            .'ini sah karena jenis stasiunnya berbeda; di model baru satu periode mencakup '
            .'seluruh mill, sehingga dua rentang yang bersinggungan akan mengunci tanggal yang '
            .'sama dua kali dan tidak ada penggabungan yang benar secara otomatis. Selesaikan '
            .'manual (samakan rentangnya, atau hapus/persempit salah satunya) lalu ulangi migrasi ['
            .implode('; ', $conflicts).'].'
        );
    }

    /**
     * Aturan 5. Nama induk yang bentrok di mill yang sama diimbuhi rentang
     * tanggalnya. Urutan pemrosesan (start_date, end_date, id) membuat hasilnya
     * sama di DB mana pun, apa pun urutan baris yang dikembalikan SELECT.
     *
     * @param  list<array<string, mixed>>  $survivors
     * @return array{0: list<array{id: string, from: string, to: string}>, 1: list<string>}
     */
    private function resolveNameClashes(array $survivors): array
    {
        $renames = [];
        $notes = [];

        foreach ($this->groupByMill($survivors) as $millId => $millRows) {
            usort($millRows, function (array $a, array $b) {
                return [$a['start_date'], $a['end_date'], $a['id']]
                    <=> [$b['start_date'], $b['end_date'], $b['id']];
            });

            $taken = [];

            foreach ($millRows as $row) {
                if (! isset($taken[$row['name']])) {
                    $taken[$row['name']] = $row['id'];

                    continue;
                }

                $newName = $this->uniqueName($row, $taken);
                $taken[$newName] = $row['id'];

                $renames[] = ['id' => $row['id'], 'from' => $row['name'], 'to' => $newName];
                $notes[] = sprintf(
                    'NAMA DIUBAH: mill %s sudah punya periode bernama "%s" (baris %s), sehingga periode %s (%s..%s) diubah menjadi "%s" agar unique (business_unit_id, name) dapat dibuat.',
                    $millId,
                    $row['name'],
                    $taken[$row['name']],
                    $row['id'],
                    $row['start_date'],
                    $row['end_date'],
                    $newName
                );
            }
        }

        return [$renames, $notes];
    }

    /**
     * @param  array<string, string>  $taken
     * @param  array<string, mixed>  $row
     */
    private function uniqueName(array $row, array $taken): string
    {
        $suffix = ' ('.$row['start_date'].' s.d. '.$row['end_date'].')';
        $candidate = $this->fit($row['name'], $suffix);

        for ($n = 2; isset($taken[$candidate]); $n++) {
            $candidate = $this->fit($row['name'], $suffix.' #'.$n);
        }

        return $candidate;
    }

    /** Potong nama dasar supaya nama + imbuhan tetap muat di varchar(255). */
    private function fit(string $base, string $suffix): string
    {
        $room = max(1, self::NAME_MAX_LENGTH - mb_strlen($suffix));

        return mb_substr($base, 0, $room).$suffix;
    }

    /**
     * @param  array{row: array<string, mixed>, explicit: bool, source: string}  $a
     * @param  array{row: array<string, mixed>, explicit: bool, source: string}  $b
     * @return array{row: array<string, mixed>, explicit: bool, source: string}
     */
    private function pickStrongest(array $a, array $b): array
    {
        $rankA = self::STATUS_RANK[$a['row']['status']] ?? 0;
        $rankB = self::STATUS_RANK[$b['row']['status']] ?? 0;

        if ($rankA !== $rankB) {
            return $rankA > $rankB ? $a : $b;
        }

        // Status sama kuat: baris yang menyebut jenis stasiunnya secara
        // eksplisit lebih spesifik daripada hasil pemekaran cakupan NULL.
        if ($a['explicit'] !== $b['explicit']) {
            return $a['explicit'] ? $a : $b;
        }

        // Seri penuh: pertahankan yang lebih dulu diproses (baris tertua).
        return $a;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $childlessGroups
     */
    private function childlessMessage(array $childlessGroups): string
    {
        $lines = array_map(function (array $group) {
            return sprintf(
                'mill %s rentang %s..%s [%s]',
                $group[0]['business_unit_id'],
                $group[0]['start_date'],
                $group[0]['end_date'],
                $this->describeRows($group)
            );
        }, $childlessGroups);

        return 'Konversi periods -> period_stations dihentikan: '.count($childlessGroups)
            .' periode bercakupan SEMUA STASIUN berada di mill yang tidak punya satu pun jenis '
            .'stasiun aktif, sehingga tidak ada baris period_stations yang bisa dibuat untuknya. '
            .'Dibiarkan lewat, periode itu akan menjadi induk tanpa anak dan migrasi 000040 '
            .'menolak seluruh rangkaian di tengah jalan. Aktifkan/daftarkan stasiun mill '
            .'tersebut, atau hapus periodenya, lalu ulangi migrasi ['.implode('; ', $lines).'].';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function describeRows(array $rows): string
    {
        return implode(', ', array_map(
            fn (array $r) => $r['id'].' "'.$r['name'].'"',
            $rows
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByMill(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['business_unit_id']][] = $row;
        }
        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    private function groupByRange(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['business_unit_id']."\0".$row['start_date']."\0".$row['end_date'];
            $grouped[$key][] = $row;
        }
        ksort($grouped);

        return array_values($grouped);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareByAge(array $a, array $b): int
    {
        if ($a['created_at'] !== $b['created_at']) {
            // created_at nullable di skema: baris tanpa created_at tidak boleh
            // merebut peran induk dari baris yang punya jejak waktu.
            if ($a['created_at'] === null) {
                return 1;
            }
            if ($b['created_at'] === null) {
                return -1;
            }

            return $a['created_at'] <=> $b['created_at'];
        }

        return $a['id'] <=> $b['id'];
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @return array<string, mixed>
     */
    private function normalise(array|object $row): array
    {
        $r = (array) $row;

        return [
            'id' => (string) $r['id'],
            'business_unit_id' => (string) $r['business_unit_id'],
            'station_type' => isset($r['station_type']) ? (string) $r['station_type'] : null,
            'name' => (string) ($r['name'] ?? ''),
            'start_date' => $this->dateKey($r['start_date'] ?? null),
            'end_date' => $this->dateKey($r['end_date'] ?? null),
            'status' => (string) ($r['status'] ?? 'draft'),
            'closed_by' => isset($r['closed_by']) ? (string) $r['closed_by'] : null,
            'closed_at' => $this->timestamp($r['closed_at'] ?? null),
            'created_at' => $this->timestamp($r['created_at'] ?? null),
            'updated_at' => $this->timestamp($r['updated_at'] ?? null),
        ];
    }

    private function dateKey(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return mb_substr((string) $value, 0, 10);
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
