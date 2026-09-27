<?php

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\StationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Period>
 *
 * screen-128--kelola-periode-pelaporan test infrastructure.
 *
 * Membuat satu periode (induk) DAN baris `period_stations`-nya. Sejak
 * 2026-09-25 `periods` tidak lagi punya kolom station_type/status/closed_by/
 * closed_at — semuanya pindah ke `period_stations`, satu baris per jenis
 * stasiun. Defaultnya: periode satu bulan kalender, DRAFT, dengan satu baris
 * untuk SETIAP jenis stasiun aktif di master `station_types` (19 baris).
 *
 * API-nya DIPERTAHANKAN UTUH — stationType(), draft(), open(), closed(),
 * named(), range(), forBusinessUnit() semuanya masih ada dengan tanda tangan
 * yang sama, karena 21 berkas test memanggilnya dan perubahan API akan
 * membunuh semuanya di setup sebelum satu asersi pun jalan. Yang berubah hanya
 * tempat datanya mendarat: state station/status tidak lagi menjadi kolom
 * induk, melainkan disimpan sebagai konfigurasi di instance factory lalu
 * diwujudkan menjadi baris period_stations tepat setelah induknya disimpan
 * (lihat store()).
 *
 * KARENA ITU URUTAN PEMANGGILAN TIDAK PENTING: ->stationType('x')->closed()
 * dan ->closed()->stationType('x') menghasilkan hal yang sama, berbeda dari
 * pendekatan afterCreating() berantai yang bergantung pada urutan.
 *
 * ARTI stationType(null) — KEPUTUSAN YANG PERLU DIBACA
 * Dulu `station_type = null` berarti "periode ini berlaku untuk SEMUA jenis
 * stasiun". Konsep itu hilang: cakupan semua-stasiun kini dinyatakan lewat
 * ADANYA satu baris per jenis stasiun. stationType(null) karena itu
 * diterjemahkan menjadi "satu baris untuk setiap jenis stasiun AKTIF di master
 * station_types" — terjemahan yang paling dekat dengan maksud ~19 pemanggil
 * lama (mereka menulisnya justru supaya periodenya ikut berlaku untuk stasiun
 * yang sedang diuji), dan yang menjaga kueri kunci periode tetap menemukan
 * barisnya. Alternatifnya, melempar exception, ditolak: itu mematikan ~19
 * berkas test di setup karena alasan yang bukan bug mereka.
 *
 * Perhatikan bedanya dengan produksi: PeriodService membuat baris untuk jenis
 * stasiun yang aktif DI MILL ITU (lewat production_lines/stations-nya),
 * sedangkan factory memakai seluruh master aktif — sebuah superset. Factory
 * tidak bisa memakai inventaris mill karena BusinessUnit::factory() tidak
 * membuat production line maupun station sama sekali, sehingga "jenis stasiun
 * aktif di mill ini" akan selalu kosong dan periode yang dihasilkan tidak
 * punya satu pun baris. Test yang memang menguji cakupan sempit menyebut
 * jenisnya eksplisit, dan yang butuh induk tanpa anak memakai noStations().
 *
 * CATATAN station_type: ia FK ke `station_types`.`code`, jadi nilai apa pun
 * yang diberikan ke stationType() harus ada di master itu (di-seed oleh
 * 2026_09_22_000029 dengan 18 jenis kanonik plus 'other'). Berikan string
 * code-nya, mis. 'sterilizer', bukan case App\Enums\StationType.
 *
 * make() TIDAK MEMBUAT BARIS period_stations — ia tidak menyentuh DB sama
 * sekali, dan baris anak butuh period_id yang tersimpan.
 */
class PeriodFactory extends Factory
{
    protected $model = Period::class;

    /**
     * Jenis stasiun yang akan dibuatkan baris. null = seluruh jenis aktif di
     * master station_types (lihat docblock kelas); [] = tidak ada baris.
     *
     * @var list<string>|null
     */
    protected ?array $stationTypeCodes = null;

    protected PeriodStatus $stationStatus = PeriodStatus::Draft;

    protected User|string|null $stationClosedBy = null;

    protected ?string $stationClosedAt = null;

    public function definition(): array
    {
        $start = Carbon::create(2026, 10, 1);

        return [
            'business_unit_id' => BusinessUnit::factory(),
            'name' => 'Periode '.$this->faker->unique()->numerify('####'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forBusinessUnit(BusinessUnit|string $businessUnit): self
    {
        return $this->state(fn () => [
            'business_unit_id' => $businessUnit instanceof BusinessUnit ? $businessUnit->id : $businessUnit,
        ]);
    }

    /**
     * Batasi periode ke SATU jenis stasiun — artinya: buat tepat satu baris
     * period_stations untuk jenis itu. Berikan null untuk cakupan "setiap
     * jenis stasiun aktif" (default); lihat docblock kelas untuk mengapa null
     * berarti itu dan bukan exception.
     */
    public function stationType(?string $stationType): self
    {
        return $this->withStationConfig([
            'stationTypeCodes' => $stationType === null ? null : [$stationType],
        ]);
    }

    /**
     * Beberapa jenis stasiun sekaligus — bentuk yang tidak bisa diungkapkan
     * sebelum 2026-09-25 (dulu butuh satu baris `periods` per jenis).
     *
     * @param  list<string>  $stationTypes
     */
    public function stationTypes(array $stationTypes): self
    {
        return $this->withStationConfig(['stationTypeCodes' => array_values($stationTypes)]);
    }

    /**
     * Induk tanpa satu pun baris period_stations. Dipakai oleh
     * PeriodStationFactory (yang membuat barisnya sendiri, dan akan menabrak
     * UNIQUE(period_id, station_type) bila induknya sudah punya baris untuk
     * jenis yang sama) dan oleh test yang memang menguji periode kosong.
     */
    public function noStations(): self
    {
        return $this->withStationConfig(['stationTypeCodes' => []]);
    }

    public function named(string $name): self
    {
        return $this->state(fn () => ['name' => $name]);
    }

    /**
     * Both bounds are inclusive, exactly as the overlap check and the
     * unverified-count query treat them.
     */
    public function range(string $startDate, string $endDate): self
    {
        return $this->state(fn () => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function draft(): self
    {
        return $this->withStationConfig([
            'stationStatus' => PeriodStatus::Draft,
            'stationClosedBy' => null,
            'stationClosedAt' => null,
        ]);
    }

    public function open(): self
    {
        return $this->withStationConfig([
            'stationStatus' => PeriodStatus::Open,
            'stationClosedBy' => null,
            'stationClosedAt' => null,
        ]);
    }

    /**
     * Stasiun-stasiun periode ini tertutup — closed_by/closed_at selalu
     * disetel bersama, karena jalur reopen() mengosongkan keduanya bersama dan
     * baris daftar menampilkan kedua kolomnya.
     *
     * Bila lebih dari satu jenis stasiun dibuat, SEMUANYA tertutup oleh user
     * yang sama pada waktu yang sama. Untuk periode yang separuh tertutup
     * (bentuk yang justru menjadi alasan pemisahan tabel ini), buat induknya
     * dengan noStations() lalu pasang barisnya lewat PeriodStation::factory().
     */
    public function closed(User|string|null $closedBy = null, ?string $closedAt = null): self
    {
        return $this->withStationConfig([
            'stationStatus' => PeriodStatus::Closed,
            'stationClosedBy' => $closedBy,
            'stationClosedAt' => $closedAt ?? Carbon::create(2026, 11, 1, 9, 14, 0)->toDateTimeString(),
        ]);
    }

    /**
     * Baris period_stations dibuat di sini, bukan di afterCreating(), karena
     * store() dipanggil tepat sekali per instance factory yang benar-benar
     * menyimpan — termasuk lewat create(), createMany(), createQuietly(), dan
     * saat Period menjadi factory bersarang untuk relasi model lain.
     */
    protected function store(Collection $results)
    {
        parent::store($results);

        $codes = $this->resolveStationTypeCodes();

        if ($codes === []) {
            return;
        }

        $closedBy = $this->resolveClosedById();

        $results->each(function (Period $period) use ($codes, $closedBy) {
            foreach ($codes as $code) {
                PeriodStation::create([
                    'period_id' => $period->id,
                    'station_type' => $code,
                    'status' => $this->stationStatus->value,
                    'closed_by' => $closedBy,
                    'closed_at' => $this->stationStatus === PeriodStatus::Closed
                        ? $this->stationClosedAt
                        : null,
                ]);
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function resolveStationTypeCodes(): array
    {
        if ($this->stationTypeCodes !== null) {
            return $this->stationTypeCodes;
        }

        return StationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    protected function resolveClosedById(): ?string
    {
        if ($this->stationStatus !== PeriodStatus::Closed) {
            return null;
        }

        return match (true) {
            $this->stationClosedBy instanceof User => $this->stationClosedBy->id,
            is_string($this->stationClosedBy) => $this->stationClosedBy,
            default => User::factory()->create()->id,
        };
    }

    /**
     * Konfigurasi stasiun hidup sebagai properti instance, bukan sebagai
     * atribut model, sehingga ia harus ikut terbawa setiap kali Factory
     * membuat instance baru — dan Factory membuat instance baru pada SETIAP
     * state(), count(), for(), has(), serta di dalam create($attributes).
     */
    protected function withStationConfig(array $config): self
    {
        $instance = $this->newInstance();

        foreach ($config as $property => $value) {
            $instance->{$property} = $value;
        }

        return $instance;
    }

    protected function newInstance(array $arguments = [])
    {
        $instance = parent::newInstance($arguments);

        $instance->stationTypeCodes = $this->stationTypeCodes;
        $instance->stationStatus = $this->stationStatus;
        $instance->stationClosedBy = $this->stationClosedBy;
        $instance->stationClosedAt = $this->stationClosedAt;

        return $instance;
    }
}
