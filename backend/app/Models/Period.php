<?php

namespace App\Models;

use App\Models\Builders\PeriodQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Period (Periode Pelaporan) — screen-128--kelola-periode-pelaporan /
 * usecase-128, usecase-140--tutup-buka-periode-pelaporan.
 *
 * Rentang tanggal pelaporan resmi per mill (Business Unit) — satuan periode
 * untuk seluruh laporan Full Cycle per Stasiun (screen-128 s/d screen-139).
 *
 * CAKUPANNYA MILL SAJA (keputusan user 2026-09-22 dan 2026-09-25): satu
 * periode berlaku untuk SELURUH Production Line DAN SELURUH jenis stasiun di
 * mill itu. Tidak ada FK ke `production_lines` maupun `stations`, dan sejak
 * 2026-09-25 tidak ada lagi kolom `station_type`. Entitas ini berdiri di luar
 * hierarki Corporate -> Company -> Business Unit -> Production Line -> Station.
 *
 * INDUK YANG TIDAK PUNYA STATUS SENDIRI. Status tutup/buka ada PER JENIS
 * STASIUN di {@see PeriodStation} (tabel `period_stations`), karena stasiun
 * tidak selesai serentak: Admin dapat menutup Sterilizer sementara
 * Clarification masih terbuka. Daftar baris period_stations bukan penyaring
 * cakupan — cakupannya tetap seluruh mill — melainkan penentu apa yang dapat
 * ditutup terpisah. Saat periode dibuat, satu baris dibuat otomatis untuk
 * setiap jenis stasiun yang aktif di mill itu, sehingga tidak ada record
 * stasiun yang lolos dari kunci periode karena stasiunnya terlewat didaftarkan.
 *
 * Periode tidak boleh diubah maupun dihapus bila ADA SATU SAJA baris
 * period_stations miliknya yang berstatus closed (409
 * PERIOD_CLOSED_IMMUTABLE). Menghapus periode menghapus seluruh barisnya
 * (cascade), jadi aturan itulah yang mencegah penghapusan diam-diam atas
 * stasiun yang sudah dikunci.
 *
 * KUNCI PERIODE TERTUTUP (bentuk kuerinya kini JOIN induk-anak): record
 * stasiun mana pun (18 entitas *-record) yang kolom `date`-nya jatuh di dalam
 * [start_date, end_date] periode INI, dan yang jenis stasiunnya cocok dengan
 * baris period_stations berstatus closed, tidak boleh di-INSERT, di-UPDATE,
 * maupun diubah kolom checked_by/acknowledged_by-nya:
 *
 *   periods p JOIN period_stations ps ON ps.period_id = p.id
 *   WHERE p.business_unit_id = ? AND ps.station_type = ?
 *     AND ps.status = 'closed'
 *     AND <record.date> BETWEEN p.start_date AND p.end_date
 *
 * Penentuan periode memakai `record.date` (tanggal kejadian di pabrik), BUKAN
 * created_at atau waktu sync. Penegakannya WAJIB di layer service backend —
 * aplikasi mobile mengirim record langsung ke POST /api/{stasiun}-records
 * sehingga kunci di layar bisa dilewati.
 *
 * PENJAGA ATRIBUT — lihat {@see Period::MOVED_TO_PERIOD_STATIONS} dan
 * {@see Period::guardMovedAttribute()} di bawah. Membaca atau menulis
 * `status`, `station_type`, `closed_by`, `closed_at` di sini MELEMPAR
 * LogicException, bukan mengembalikan null.
 */
class Period extends Model
{
    use HasFactory, HasUuids;

    /**
     * Kolom yang PINDAH ke `period_stations` pada 2026-09-25.
     *
     * Ini bukan dokumentasi, ini penjaga yang berjalan. Eloquent
     * mengembalikan null untuk atribut yang tidak ada dan tidak melempar apa
     * pun, sehingga delapan penolakan aksi yang berbentuk
     * `$period->status === 'closed'` (PeriodService::update/delete,
     * PeriodClosureService, KelolaPeriodePelaporan, statusValue() di 5
     * ReportService) akan diam-diam menjadi false begitu kolomnya pindah:
     * kompilasi hijau, sebagian test hijau, penolakannya berhenti bekerja.
     * Penjaga ini mengubah bug diam itu menjadi kegagalan berisik.
     *
     * @var list<string>
     */
    public const MOVED_TO_PERIOD_STATIONS = [
        'station_type',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $fillable = [
        'business_unit_id',
        'name',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Jenis stasiun yang dikelola periode ini, satu baris per jenis, beserta
     * status tutup/bukanya. DI SINILAH status berada — induk tidak punya.
     */
    public function stations(): HasMany
    {
        return $this->hasMany(PeriodStation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Penjaga atribut di atas tidak menutup jalur query builder — sebuah
     * `where('status', 'closed')` tidak pernah melewati getAttribute(), dan di
     * SQLite ia bahkan tidak gagal (lihat PeriodQueryBuilder untuk buktinya).
     * Karena itu kueri Period memakai builder yang menolak kolom-kolom itu.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): Builder
    {
        return new PeriodQueryBuilder($query);
    }

    public function getAttribute($key)
    {
        $this->guardMovedAttribute($key, 'dibaca dari');

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        $this->guardMovedAttribute($key, 'ditulis ke');

        return parent::setAttribute($key, $value);
    }

    /**
     * fill() dijaga terpisah dari setAttribute() karena Eloquent MEMBUANG
     * kunci yang tidak fillable tanpa bersuara — `$period->forceFill(['status'
     * => 'open'])` dan `Period::create([... 'status' => ...])` tidak akan
     * pernah sampai ke setAttribute().
     */
    public function fill(array $attributes)
    {
        foreach (array_keys($attributes) as $key) {
            $this->guardMovedAttribute($key, 'diisi massal ke');
        }

        return parent::fill($attributes);
    }

    /**
     * @throws LogicException bila $key adalah kolom yang pindah ke period_stations
     */
    protected function guardMovedAttribute(string $key, string $verb): void
    {
        if (! in_array($key, self::MOVED_TO_PERIOD_STATIONS, true)) {
            return;
        }

        throw new LogicException(sprintf(
            'Period::$%s tidak ada lagi dan tidak boleh %s Period — kolom itu pindah ke '
            .'period_stations.%s pada 2026-09-25. Status tutup/buka ada PER JENIS STASIUN: '
            .'pakai $period->stations (relasi PeriodStation), mis. '
            ."\$period->stations()->where('station_type', \$type)->value('status'), atau "
            .'$period->stations->contains(fn ($s) => $s->status === PeriodStatus::Closed) '
            .'untuk "ada stasiun yang tertutup". Tanpa penjaga ini, perbandingan lama seperti '
            ."\$period->status === 'closed' akan selalu false dan penolakannya berhenti bekerja "
            .'tanpa satu baris pun gagal kompilasi.',
            $key,
            $verb,
            $key,
        ));
    }
}
