<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus data "lajur" browser test — record stasiun dan Periode Pelaporan
 * bertanggal 1970-01-01 s.d. 2019-12-31 — dari DATABASE E2E SAJA.
 *
 * KENAPA PERINTAH INI ADA. Aplikasi ini sengaja tidak punya jalur hapus
 * untuk record stasiun: sebuah log sheet yang sudah masuk tidak boleh
 * lenyap. Sejak 2026-10-04 periode yang sudah berisi record juga tidak bisa
 * dihapus (409 PERIOD_HAS_RECORDS). Spec laporan e2e menanam keduanya di
 * "lajur" tanggalnya sendiri (e2e-web/tests/support/period-lanes.ts), jadi
 * tanpa perintah ini tidak ada jalan membersihkannya — dan daftar periode
 * (20 baris per halaman, urut start_date DESC) akan menenggelamkan periode
 * spec berikutnya.
 *
 * KENAPA RENTANG 1970-2019 (sejak 2026-10-04; semula "tahun 2600 ke atas").
 * Aturan tanggal kejadian (App\Support\AppTime::latestEventDate()) menolak
 * tanggal lebih dari besok, jadi lajurnya dipindah ke MASA LALU. Rentang di
 * sini WAJIB sama dengan LANE_RANGE_START/LANE_RANGE_END di period-lanes.ts.
 *
 * KENAPA HANYA DI DATABASE E2E. Masa lalu, tidak seperti tahun 2600, bisa
 * saja berisi data pabrik sungguhan (impor historis). Karena itu perintah
 * ini menolak berjalan kecuali environment-nya `e2e` DAN nama database-nya
 * berakhiran `_e2e` (backend/.env.e2e → mill_smart_log_e2e). Pengecualian
 * satu-satunya environment `testing` (phpunit, SQLite in-memory).
 *
 * PENJAGA, karena perintah ini menghapus dan tidak dapat dibatalkan:
 *   1. Hanya environment e2e (database *_e2e) atau testing. Production,
 *      local, dll. ditolak — --force sekalipun tidak membukanya.
 *   2. Tanpa --force perintah ini hanya MENGHITUNG.
 *
 * Baris detail ikut terhapus dengan sendirinya (FK detail → record memakai
 * ON DELETE CASCADE), begitu juga period_stations (→ periods, CASCADE).
 * Satu-satunya FK RESTRICT ke tabel record adalah grading_records →
 * weighbridge_records: Grading dihapus lebih dulu, dan Weighbridge yang
 * masih dirujuk Grading di LUAR rentang dibiarkan (dihitung sebagai
 * "dilewati"), bukan menggagalkan seluruh transaksi.
 */
class PruneE2eRecords extends Command
{
    /** Awal rentang lajur (inklusif). Sama dengan LANE_RANGE_START. */
    public const RANGE_START = '1970-01-01';

    /** Akhir rentang lajur (EKSKLUSIF). Sama dengan LANE_RANGE_END. */
    public const RANGE_END = '2020-01-01';

    /** Tabel record yang tanggal kejadiannya bukan kolom `date`. */
    private const DATE_COLUMN_OVERRIDES = [
        'weighbridge_records' => 'record_datetime',
    ];

    protected $signature = 'e2e:prune-records
        {--force : Benar-benar menghapus; tanpa ini hanya menghitung}';

    protected $description = 'Hapus record stasiun & periode lajur browser test (1970-2019) dari database e2e';

    public function handle(): int
    {
        $refusal = $this->refusal();

        if ($refusal !== null) {
            $this->error('Ditolak: '.$refusal);

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        $affected = [];

        foreach ($this->recordTables() as $table => $column) {
            $count = $this->recordQuery($table, $column)->count();

            if ($count > 0) {
                $affected[$table] = $count;
            }
        }

        $periods = $this->periodQuery()->count();

        if ($periods > 0) {
            $affected['periods'] = $periods;
        }

        $range = sprintf('%s s.d. sebelum %s', self::RANGE_START, self::RANGE_END);

        if ($affected === []) {
            $this->info(sprintf('Tidak ada record maupun periode bertanggal %s. Tidak ada yang dihapus.', $range));

            return self::SUCCESS;
        }

        foreach ($affected as $table => $count) {
            $this->line(sprintf('  %-34s %d', $table, $count));
        }

        $total = array_sum($affected);

        if (! $force) {
            $this->warn(sprintf(
                '%d baris bertanggal %s. Jalankan ulang dengan --force untuk menghapus.',
                $total,
                $range,
            ));

            return self::SUCCESS;
        }

        // Satu transaksi: kalau satu tabel gagal, tidak ada tabel yang
        // setengah terhapus. Urutannya penting — grading sebelum
        // weighbridge (FK RESTRICT), record sebelum periode.
        $deleted = DB::transaction(function (): int {
            $deleted = 0;

            foreach ($this->recordTables() as $table => $column) {
                $deleted += $this->recordQuery($table, $column)->delete();
            }

            return $deleted + $this->periodQuery()->delete();
        });

        // Weighbridge di rentang yang MASIH tersisa = yang dirujuk grading di
        // luar rentang (sengaja dilewati recordQuery()).
        $skipped = Schema::hasTable('weighbridge_records')
            ? DB::table('weighbridge_records')
                ->where('record_datetime', '>=', self::RANGE_START)
                ->where('record_datetime', '<', self::RANGE_END)
                ->count()
            : 0;

        $this->info(sprintf(
            '%d baris dihapus (baris detail & period_stations ikut lewat ON DELETE CASCADE)%s.',
            $deleted,
            $skipped > 0 ? sprintf('; %d weighbridge dilewati karena masih dirujuk grading di luar rentang', $skipped) : '',
        ));

        return self::SUCCESS;
    }

    /** Alasan menolak, atau null bila boleh berjalan. */
    private function refusal(): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        if (! app()->environment('e2e')) {
            return sprintf(
                'perintah ini hanya berjalan di environment e2e (sekarang: %s). Pakai --env=e2e.',
                app()->environment(),
            );
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_e2e')) {
            return sprintf('database "%s" bukan database e2e (harus berakhiran _e2e).', $database);
        }

        return null;
    }

    private function recordQuery(string $table, string $column): Builder
    {
        $query = DB::table($table)
            ->where($column, '>=', self::RANGE_START)
            ->where($column, '<', self::RANGE_END);

        if ($table === 'weighbridge_records' && Schema::hasTable('grading_records')
            && Schema::hasColumn('grading_records', 'weighbridge_record_id')) {
            $query->whereNotExists(function (Builder $sub): void {
                $sub->selectRaw('1')
                    ->from('grading_records')
                    ->whereColumn('grading_records.weighbridge_record_id', 'weighbridge_records.id');
            });
        }

        return $query;
    }

    /** Periode yang SELURUH rentangnya berada di dalam rentang lajur. */
    private function periodQuery(): Builder
    {
        return DB::table('periods')
            ->where('start_date', '>=', self::RANGE_START)
            ->where('end_date', '<', self::RANGE_END);
    }

    /**
     * Tabel *_records beserta kolom tanggal kejadiannya, dibaca dari skema —
     * otomatis mencakup stasiun yang ditambahkan kelak. weighbridge_records
     * tidak punya kolom `date`; tanggalnya `record_datetime`. Weighbridge
     * selalu diletakkan TERAKHIR (sesudah grading, yang merujuknya).
     *
     * Dibaca lewat Schema builder, BUKAN information_schema: test berjalan di
     * SQLite in-memory (phpunit.xml) dan kueri information_schema akan
     * meledak di sana.
     *
     * @return array<string, string>
     */
    private function recordTables(): array
    {
        $tables = array_map(
            static fn (string $table): string => str_contains($table, '.')
                ? substr($table, strrpos($table, '.') + 1)
                : $table,
            Schema::getTableListing(),
        );

        $tables = array_filter($tables, static fn (string $table): bool => str_ends_with($table, '_records'));
        sort($tables);

        $result = [];

        foreach ($tables as $table) {
            $column = self::DATE_COLUMN_OVERRIDES[$table] ?? 'date';

            if (Schema::hasColumn($table, $column)) {
                $result[$table] = $column;
            }
        }

        // Weighbridge terakhir: grading_records merujuknya dengan FK RESTRICT.
        if (isset($result['weighbridge_records'])) {
            $column = $result['weighbridge_records'];
            unset($result['weighbridge_records']);
            $result['weighbridge_records'] = $column;
        }

        return $result;
    }
}
