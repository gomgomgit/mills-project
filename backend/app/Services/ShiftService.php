<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ShiftOverlapException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ShiftService — screen-170--kelola-shift / usecase-176--kelola-shift
 * (Kelola Shift per Production Line: master admin-only atas jadwal shift
 * tiap Production Line).
 *
 * Dipakai BERSAMA oleh controller API (App\Http\Controllers\Api\
 * ShiftController) dan komponen Livewire (App\Livewire\MasterData\
 * KelolaShift) — sama seperti PeriodService/ProductionLineService — sehingga
 * halaman web dan API tidak mungkin berbeda hasil hitungan cakupannya.
 *
 * ── SUMBU MENIT 0..1440 RELATIF 07:00, BUKAN STRING JAM ──────────────────
 *
 * SELURUH perbandingan rentang di kelas ini dilakukan pada menit, dan 07:00
 * adalah titik nol. Dua alasan, keduanya mengikat:
 *
 *  1. STRING JAM MENIPU. '23:00' > '07:00' sebagai perbandingan string
 *     menyimpulkan rentang shift malam KOSONG, sehingga shift malam tidak
 *     pernah terdeteksi bertindih — dan hasil salahnya terlihat masuk akal.
 *     Ini kelas kesalahan yang sama dengan cacat pengurutan slot di laporan
 *     Engine Room. Unit test "perbandingan string jam TIDAK dipakai — kasus
 *     jebakan" ada justru untuk memerahkan implementasi seperti itu.
 *  2. HARI PRODUKSI DIMULAI 07:00. Kisi slot waktu kanonis seluruh stasiun
 *     bergrid berjalan (7+i)%24, jadi `start_offset_minutes` 0 berarti 07:00
 *     dan 1440 berarti 07:00 keesokan hari. Mengukur offset dari tengah
 *     malam akan membelah shift malam menjadi dua blok di KEDUA UJUNG
 *     batang timeline — persis yang dihindari dengan memulai batangnya 07:00.
 *
 * Shift yang melewati batas hari produksi dipecah menjadi dua segmen HANYA
 * di dalam perhitungan tumpang tindih dan cakupan ({@see segmentsFor()});
 * payload dan tampilan SELALU satu blok utuh dengan durasi penuh.
 *
 * ── TUMPANG TINDIH DITOLAK, CELAH DIIZINKAN TAPI DITANDAI ────────────────
 *
 * Tumpang tindih = irisan LEBIH BESAR dari nol menit, DI DALAM satu
 * production_line_id. Shift yang berakhir tepat pada jam mulai shift lain
 * beririsan nol menit → BUKAN tindihan, itu pergantian shift normal dan
 * harus diterima (batas inklusif, pola yang sama dengan rentang tanggal
 * Periode Pelaporan). Celah tidak pernah ditolak di lapisan data: ia
 * diterbitkan sebagai blok ber-kind 'gap' beserta durasinya, dan layarlah
 * yang membuatnya terlihat.
 *
 * ── CAKUPAN DIHITUNG DI SINI, SEKALI ────────────────────────────────────
 *
 * `coverage[]` berisi posisi dan durasi tiap blok dalam MENIT DARI 07:00,
 * sehingga blade cukup memetakan menit ke persen (left =
 * start_offset_minutes/1440, width = duration_minutes/1440) dan tidak
 * menghitung jam sama sekali. Alasannya bukan kerapian: perhitungan yang
 * sama akan dibutuhkan lagi oleh gerbang sinkronisasi dan oleh penandaan
 * shift pada ke-18 tabel record, dan tiga penghitung yang berdiri sendiri
 * akan berselisih. Karena itu rumus menitnya PUBLIC STATIC di kelas ini —
 * pemakai berikutnya memanggilnya, tidak menulisnya ulang.
 *
 * ── TIGA KEADAAN CAKUPAN, DAN 'unconfigured' BUKAN CELAH 1440 ───────────
 *
 * `state` bernilai 'covered' | 'gap' | 'unconfigured'. 'unconfigured' HANYA
 * ketika line tidak punya satu pun shift, dan ia BUKAN 'gap' dengan
 * uncovered_minutes 1440: yang pertama belum dikerjakan, yang kedua
 * keputusan yang mungkin salah. Membedakannya DI PAYLOAD adalah yang
 * membuat blade dapat membedakannya di layar (celah berarsir, belum diatur
 * polos).
 *
 * ── SHIFT TERTUTUP: SOFT DELETE, DAN EMPAT JALUR YANG MENGECUALIKANNYA ──
 *
 * Penghapusan menutup baris alih-alih membuangnya (keputusan pemilik produk
 * 2026-10-16) — lihat {@see delete()}. EMPAT jalur yang hidup WAJIB
 * mengecualikan baris tertutup, dan keempatnya memang sudah dikecualikan
 * oleh GLOBAL SCOPE SoftDeletes karena semuanya berangkat dari
 * `Shift::query()`:
 *
 *  1. DAFTAR — {@see listShifts()}
 *  2. CAKUPAN/CELAH — {@see listShifts()} memberi {@see coverageEntry()}
 *     koleksi yang sudah tersaring; coverageEntry() sendiri tidak pernah
 *     mengkueri, jadi ia tidak dapat menarik baris tertutup
 *  3. SUMBER PRATINJAU PADA FORM — {@see shiftBlocksByLine()}
 *  4. HIMPUNAN PEMBANDING TUMPANG TINDIH — {@see findOverlap()}
 *
 * YANG KEEMPAT PALING MUDAH TERLEWAT, dan kegagalannya khas: jam yang baru
 * saja dikosongkan tidak akan pernah bisa dipakai ulang oleh penggantinya —
 * padahal MEMAKAI ULANG JAM adalah justru alasan soft delete dipilih.
 *
 * SATU jalur shift di kelas ini TIDAK dijangkau global scope: Rule::unique()
 * di {@see validate()} membangun kueri Query Builder, bukan Eloquent, jadi ia
 * menambahkan whereNull('deleted_at') sendiri. Partial unique index di
 * migrasi 2026_10_16_000001 menyepakatinya di sisi basis data.
 *
 * ── JADWAL BERLAKU SAMA SETIAP HARI ─────────────────────────────────────
 *
 * Tidak ada parameter tanggal, hari, maupun masa berlaku pada satu pun
 * method di kelas ini (keputusan pemilik produk 2026-10-15). Ketiadaan itu
 * disengaja dan dicatat supaya tidak terbaca sebagai kelalaian lalu
 * 'dilengkapi' oleh orang berikutnya.
 *
 * ── GATE PERAN: ADMIN SAJA, TIGA LAPIS ──────────────────────────────────
 *
 * Lapis 1 middleware rute ('auth'/'auth:web' + 'role:admin'), lapis 2
 * abort_unless() di KelolaShift::mount(), lapis 3 guardAdmin() di SETIAP
 * method publik kelas ini — dijalankan SEBELUM kueri apa pun, sehingga
 * peran non-admin menghasilkan 403 dengan NOL kueri ke tabel `shifts`.
 */
class ShiftService
{
    /** 07:00 — awal hari produksi, titik nol seluruh offset di kelas ini. */
    public const PRODUCTION_DAY_START_MINUTE = 420;

    /** Panjang satu hari produksi dalam menit (07:00 → 07:00 keesokan hari). */
    public const DAY_MINUTES = 1440;

    /**
     * Field yang diterima create()/update(). created_by/updated_by TIDAK
     * pernah diambil dari request — aktornya selalu diturunkan dari sesi.
     */
    public const FIELDS = [
        'production_line_id',
        'name',
        'start_time',
        'end_time',
    ];

    // ───────────────────────────────────────────────────────────────────
    // Rumus menit — PUBLIC STATIC, satu-satunya sumber aritmetika jam
    // ───────────────────────────────────────────────────────────────────

    /**
     * Menit ke-berapa dalam satu hari kalender (00:00 = 0). Menerima
     * 'HH:MM' maupun 'HH:MM:SS': PostgreSQL memulangkan kolom TIME sebagai
     * bentuk kedua sementara SQLite memulangkan apa pun yang tersimpan, jadi
     * KEDUANYA harus dibaca oleh satu fungsi yang sama. Ini satu-satunya
     * tempat format jam dibaca di seluruh layar ini.
     */
    public static function minuteOfDay(string $time): int
    {
        $parts = explode(':', trim($time));

        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return (($hours % 24) * 60) + $minutes;
    }

    /**
     * Offset dari AWAL HARI PRODUKSI (07:00), 0..1439. 07:00 → 0, 15:00 →
     * 480, 23:00 → 960, 06:00 → 1380.
     */
    public static function offsetFromProductionDayStart(string $time): int
    {
        return (self::minuteOfDay($time) - self::PRODUCTION_DAY_START_MINUTE + self::DAY_MINUTES)
            % self::DAY_MINUTES;
    }

    /**
     * Durasi shift dalam menit, memperhitungkan lintasan tengah malam:
     * 23:00→07:00 menghasilkan 480, bukan negatif dan bukan nol.
     *
     * JAM YANG BERIMPIT DIBACA SEBAGAI SATU HARI PENUH (1440), bukan nol —
     * dan sejak 2026-10-16 itu BUKAN LAGI hanya pembacaan defensif atas
     * data yang ditanam factory: `07:00 → 07:00` adalah SATU-SATUNYA cara
     * mengungkapkan shift yang menutup 1440 menit, dan jalur tulis kini
     * menerimanya bila line itu belum punya shift HIDUP lain (lihat
     * validate()). Modulo 0 yang dibaca 1440 itulah yang membuat shift 24
     * jam menghasilkan satu blok berdurasi penuh di segmentsFor(),
     * coverageEntry(), shiftBlock(), dan pratinjau klien sekaligus — tanpa
     * satu pun dari keempatnya perlu mengenal kasus khususnya.
     */
    public static function durationMinutes(string $startTime, string $endTime): int
    {
        $diff = (self::minuteOfDay($endTime) - self::minuteOfDay($startTime) + self::DAY_MINUTES)
            % self::DAY_MINUTES;

        return $diff === 0 ? self::DAY_MINUTES : $diff;
    }

    /** Jam 'HH:MM' dari sebuah offset hari produksi (0 → '07:00'). */
    public static function timeFromOffset(int $offset): string
    {
        $minuteOfDay = (self::PRODUCTION_DAY_START_MINUTE + $offset) % self::DAY_MINUTES;

        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    /** Jam 'HH:MM' dari nilai kolom TIME apa pun bentuknya. */
    public static function displayTime(string $time): string
    {
        $minuteOfDay = self::minuteOfDay($time);

        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    /**
     * Segmen-segmen sebuah shift pada sumbu 0..1440 relatif 07:00 — SATU
     * segmen bila ia tidak melewati batas hari produksi, DUA bila ia
     * melewatinya (mis. 05:00–09:00 → [1320,1440] dan [0,120]).
     *
     * PEMECAHAN INI HANYA HIDUP DI DALAM PERHITUNGAN. Payload dan tampilan
     * selalu satu blok utuh: memecahnya di payload akan membuat blade
     * menggambar dua potongan di kedua ujung batang.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function segmentsFor(int $startOffset, int $durationMinutes): array
    {
        $end = $startOffset + $durationMinutes;

        if ($end <= self::DAY_MINUTES) {
            return [[$startOffset, $end]];
        }

        return [
            [$startOffset, self::DAY_MINUTES],
            [0, $end - self::DAY_MINUTES],
        ];
    }

    /**
     * Irisan dua rentang pada sumbu menit, dalam menit — nol bila mereka
     * hanya bersentuhan di ujung. BATAS INKLUSIF ada di sini dan hanya di
     * sini: pemanggil memeriksa `> 0`, bukan `>= 0`.
     */
    public static function intersectionMinutes(array $a, array $b): int
    {
        return max(0, min($a[1], $b[1]) - max($a[0], $b[0]));
    }

    // ───────────────────────────────────────────────────────────────────
    // Pemilih (options)
    // ───────────────────────────────────────────────────────────────────

    /**
     * businessUnitOptions() — GET /api/shifts/business-units/options.
     * Daftar mill tak berpaginasi { id, name } untuk pemilih Mill. Admin
     * tidak terikat satu mill, jadi ia memilih; master kosong memulangkan
     * array kosong dengan 200, bukan 404.
     *
     * @return list<array{id: string, name: string}>
     */
    public function businessUnitOptions(): array
    {
        $this->guardAdmin();

        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => $businessUnit->id,
                'name' => $businessUnit->name,
            ])
            ->all();
    }

    /**
     * productionLineOptions() — GET /api/shifts/production-lines/options.
     * Line milik SATU mill. business_unit_id WAJIB: tidak ada nilai yang
     * dapat diturunkan dari akun Admin, jadi absen → 422 dan nol kueri.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws ValidationException
     */
    public function productionLineOptions(?string $businessUnitId): array
    {
        $this->guardAdmin();

        $businessUnitId = $this->requireBusinessUnitId($businessUnitId);

        if (! Str::isUuid($businessUnitId)) {
            return [];
        }

        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
            ])
            ->all();
    }

    // ───────────────────────────────────────────────────────────────────
    // Daftar + cakupan
    // ───────────────────────────────────────────────────────────────────

    /**
     * listShifts() — GET /api/shifts dan sumber data komponen Livewire.
     *
     * Memulangkan TIGA bagian:
     *   data[]     satu entri per shift, BERPAGINASI, sudah terurut
     *              (nama line, lalu `position` yang diturunkan dari jam
     *              mulai relatif 07:00) — blade tidak mengurutkan apa pun
     *   coverage[] satu entri per Production Line dalam cakupan filter,
     *              TIDAK berpaginasi: ia merangkum cakupan line, bukan
     *              mendaftar baris, dan panel yang ikut berpindah halaman
     *              akan mengatakan hal berbeda tentang line yang sama
     *              tergantung halaman mana yang terbuka
     *   meta       page / per_page / total / total_pages (bentuk bersama
     *              App\Support\Pagination)
     *
     * business_unit_id WAJIB; absen → 422 dan NOL kueri shift. Mill yang
     * tidak dikenal (atau id yang bentuknya bukan UUID — jebakan SQLSTATE
     * 22P02 di PostgreSQL, lihat ScopesToActorMill) memulangkan data dan
     * coverage kosong dengan 200: endpoint ini MERANGKUM, ia tidak mengambil
     * satu resource.
     *
     * production_line_id OPSIONAL — absen berarti SELURUH line mill itu.
     * Berbeda dari layar laporan tempat line wajib, dan alasannya berbeda
     * pula: laporan menghasilkan angka gabungan yang tidak bermakna bila
     * mencampur line, sementara layar ini menampilkan daftar yang setiap
     * barisnya menyebut line-nya sendiri — dan 'semua line' justru pemakaian
     * audit yang disengaja. Line milik MILL LAIN tetap 422 (parameter yang
     * keliru), bukan 403.
     *
     * PENGURUTAN DILAKUKAN DI PHP, BUKAN DI SQL, dan itu disengaja: urutan
     * yang benar adalah jam mulai RELATIF 07:00, yang tidak dapat
     * diungkapkan sebagai ORDER BY portabel atas kolom TIME tanpa
     * mengandalkan aritmetika khas satu basis data. Jumlah barisnya terbatas
     * (belasan line kali segelintir shift), jadi memuat lalu mengurutnya
     * jauh lebih murah daripada dua dialek SQL yang harus dijaga sinkron.
     *
     * @return array{data: list<array<string, mixed>>, coverage: list<array<string, mixed>>, meta: array<string, int>}
     *
     * @throws ValidationException
     */
    public function listShifts(
        ?string $businessUnitId,
        ?string $productionLineId = null,
        int $page = 1,
        int $perPage = Pagination::DEFAULT_PER_PAGE
    ): array {
        $this->guardAdmin();

        $businessUnitId = $this->requireBusinessUnitId($businessUnitId);
        $productionLineId = $this->resolveLineFilter($businessUnitId, $productionLineId);

        $page = max($page, 1);
        $perPage = min(max($perPage, 1), Pagination::MAX_PER_PAGE);

        $lines = Str::isUuid($businessUnitId)
            ? ProductionLine::query()
                ->where('business_unit_id', $businessUnitId)
                ->when($productionLineId !== null, fn ($query) => $query->where('id', $productionLineId))
                ->orderBy('name')
                ->get()
            : new EloquentCollection;

        if ($lines->isEmpty()) {
            // Mill tanpa satu pun Production Line: data DAN coverage kosong
            // dengan 200 — blade-lah yang menyatakannya dan mengarahkan ke
            // Kelola Production Line, bukan timeline kosong tanpa penjelasan.
            return [
                'data' => [],
                'coverage' => [],
                'meta' => $this->meta([], 0, $perPage, $page),
            ];
        }

        // JALUR HIDUP 1 (daftar) DAN 2 (cakupan/celah). Baris tertutup
        // dikecualikan oleh global scope SoftDeletes model Shift — tidak ada
        // whereNull() di sini, dan menambahkannya hanya akan menduplikasi
        // apa yang sudah ditempelkan scope itu pada SQL-nya. coverageEntry()
        // di bawah memakai koleksi yang SAMA, jadi cakupan dan daftar tidak
        // mungkin berbeda isi.
        $shiftsByLine = Shift::query()
            ->whereIn('production_line_id', $lines->modelKeys())
            ->get()
            ->groupBy('production_line_id');

        $rows = [];
        $coverage = [];

        foreach ($lines as $line) {
            $lineShifts = $this->orderShifts($shiftsByLine->get($line->id) ?? collect());

            foreach ($lineShifts->values() as $position => $shift) {
                $rows[] = $this->toRow($shift, $line, $position);
            }

            $coverage[] = $this->coverageEntry($line, $lineShifts);
        }

        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return [
            'data' => array_values($items),
            'coverage' => $coverage,
            'meta' => $this->meta($items, $total, $perPage, $page),
        ];
    }

    /**
     * Blok shift per Production Line untuk SELURUH line sebuah mill, tanpa
     * paginasi — sumber data PRATINJAU HIDUP di dalam form.
     *
     * Dipisahkan dari listShifts() karena pratinjau membutuhkan line yang
     * sedang DIPILIH DI FORM, yang belum tentu line yang sedang difilter di
     * halaman. Bentuknya sengaja sama dengan coverage[].blocks ber-kind
     * 'shift' supaya pratinjau klien memakai angka yang sama persis dengan
     * yang nanti diterbitkan server.
     *
     * @return array<string, list<array<string, mixed>>> production_line_id => blok
     *
     * @throws ValidationException
     */
    public function shiftBlocksByLine(?string $businessUnitId): array
    {
        $this->guardAdmin();

        $businessUnitId = $this->requireBusinessUnitId($businessUnitId);

        if (! Str::isUuid($businessUnitId)) {
            return [];
        }

        $lineIds = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return [];
        }

        $blocks = [];

        foreach ($lineIds as $lineId) {
            $blocks[(string) $lineId] = [];
        }

        // JALUR HIDUP 3 (sumber pratinjau pada form). Baris tertutup
        // dikecualikan oleh global scope SoftDeletes: pratinjau tidak boleh
        // menggambar blok untuk shift yang sudah ditutup, karena jam itu
        // justru sudah bebas dipakai ulang.
        $shifts = $this->orderShifts(
            Shift::query()->whereIn('production_line_id', $lineIds)->get()
        );

        foreach ($shifts as $shift) {
            $blocks[(string) $shift->production_line_id][] = $this->shiftBlock($shift);
        }

        return $blocks;
    }

    /**
     * coverageEntry() — ringkasan cakupan 24 jam SATU Production Line.
     *
     * @param  Collection<int, Shift>  $lineShifts  sudah terurut oleh orderShifts()
     * @return array<string, mixed>
     */
    public function coverageEntry(ProductionLine $line, Collection $lineShifts): array
    {
        $shiftCount = $lineShifts->count();

        if ($shiftCount === 0) {
            // BELUM DIATUR — dan sengaja BUKAN 'gap' 1440 menit. Line yang
            // SELURUH shift-nya sudah ditutup mendarat di cabang ini juga,
            // dan itu benar: shift tertutup tidak dihitung di mana pun yang
            // hidup, jadi line itu tak dapat dibedakan dari line yang belum
            // pernah diberi shift — dan tidak perlu dibedakan. Nol blok
            // diterbitkan: blok "belum diatur" di layar adalah keputusan
            // TAMPILAN (polos, tanpa arsiran celah), bukan sebuah celah yang
            // ditemukan di data. uncovered_minutes 0 supaya tidak ada badge
            // yang menyebut angka jam untuk line yang belum dikerjakan.
            return [
                'production_line_id' => $line->id,
                'production_line_name' => $line->name,
                'shift_count' => 0,
                'state' => 'unconfigured',
                'uncovered_minutes' => 0,
                'uncovered_hours_text' => '0',
                'starts_at_production_day' => true,
                'blocks' => [],
            ];
        }

        $blocks = [];
        $segments = [];

        foreach ($lineShifts as $shift) {
            $block = $this->shiftBlock($shift);
            $blocks[] = $block;

            foreach (self::segmentsFor($block['start_offset_minutes'], $block['duration_minutes']) as $segment) {
                $segments[] = $segment;
            }
        }

        $uncoveredMinutes = 0;

        foreach ($this->complementSegments($segments) as $gap) {
            $duration = $gap[1] - $gap[0];
            $uncoveredMinutes += $duration;

            $blocks[] = [
                'kind' => 'gap',
                'shift_id' => null,
                'label' => 'Celah '.$this->hoursText($duration).' jam',
                'start_offset_minutes' => $gap[0],
                'duration_minutes' => $duration,
                'time_label' => self::timeFromOffset($gap[0]).'–'.self::timeFromOffset($gap[1]),
                'crosses_midnight' => false,
            ];
        }

        usort($blocks, fn (array $a, array $b) => $a['start_offset_minutes'] <=> $b['start_offset_minutes']);

        return [
            'production_line_id' => $line->id,
            'production_line_name' => $line->name,
            'shift_count' => $shiftCount,
            'state' => $uncoveredMinutes === 0 ? 'covered' : 'gap',
            'uncovered_minutes' => $uncoveredMinutes,
            'uncovered_hours_text' => $this->hoursText($uncoveredMinutes),
            // PERINGATAN, BUKAN PENOLAKAN: union shift yang tidak dimulai
            // 07:00 layak diperingatkan karena hari produksi dimulai jam itu,
            // tetapi menolaknya akan memaksa pabrik yang jadwalnya memang
            // tidak selaras mengarang shift yang tidak mereka jalankan.
            'starts_at_production_day' => $lineShifts->contains(
                fn (Shift $shift) => self::offsetFromProductionDayStart($shift->start_time) === 0
            ),
            'blocks' => array_values($blocks),
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Tulis
    // ───────────────────────────────────────────────────────────────────

    /**
     * create() — POST /api/shifts: validasi (422) → pemeriksaan tumpang
     * tindih pada line yang sama (422 SHIFT_OVERLAP, menyebut shift
     * pembanding dan rentang tindihannya) → INSERT.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException|ShiftOverlapException
     */
    public function create(array $data): array
    {
        $this->guardAdmin();

        $attributes = $this->validate($data, null);

        $this->guardAgainstOverlap(
            $attributes['production_line_id'],
            $attributes['start_time'],
            $attributes['end_time'],
            null
        );

        $attributes['created_by'] = auth()->id();

        $shift = Shift::create($attributes);

        return $this->toWriteRow($shift);
    }

    /**
     * update() — PATCH /api/shifts/{id}. 404 bila shift tidak ada, lalu
     * validasi + pemeriksaan tumpang tindih yang sama seperti create()
     * dengan DUA perbedaan yang menentukan:
     *
     *  - SHIFT INI DIKECUALIKAN dari himpunan pembanding. Tanpa itu setiap
     *    perubahan bertindih dengan dirinya sendiri dan tidak ada yang
     *    pernah bisa disimpan.
     *  - BILA production_line_id IKUT BERUBAH, pemeriksaan dijalankan
     *    terhadap shift di line TUJUAN, bukan di line asal — line asal tidak
     *    lagi relevan setelah shift-nya pindah.
     *
     * PATCH bersifat PARSIAL: field yang tidak dikirim mempertahankan nilai
     * yang tersimpan, bukan menjadi null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException|ValidationException|ShiftOverlapException
     */
    public function update(string $id, array $data): array
    {
        $this->guardAdmin();

        $shift = Shift::findOrFail($id);

        $merged = [];

        foreach (self::FIELDS as $field) {
            $merged[$field] = array_key_exists($field, $data)
                ? $data[$field]
                : $shift->getAttribute($field);
        }

        $attributes = $this->validate($merged, $shift->id);

        $this->guardAgainstOverlap(
            $attributes['production_line_id'],
            $attributes['start_time'],
            $attributes['end_time'],
            $shift->id
        );

        $attributes['updated_by'] = auth()->id();

        $shift->update($attributes);

        return $this->toWriteRow($shift->refresh());
    }

    /**
     * delete() — DELETE /api/shifts/{id}. 404 bila tidak ada, lalu SOFT
     * DELETE: barisnya DITUTUP, bukan dibuang (keputusan pemilik produk
     * 2026-10-16).
     *
     * ALASANNYA BUKAN KEHATI-HATIAN UMUM. `shift_id` akan mendarat di
     * kedelapan belas tabel record di putaran penandaan berikutnya,
     * bermakna "shift yang berlaku saat operator menyimpan record".
     * Membuang baris shift membuat record lama kehilangan jawaban atas
     * "record ini sebenarnya shift jam berapa", dan laporan historisnya
     * berhenti terbaca — tanpa satu pun aksi yang terlihat destruktif.
     * Dengan baris yang ditutup, rujukan itu tetap dapat diselesaikan
     * selamanya.
     *
     * TIDAK ADA PENJAGAAN "sudah dipakai record" pada putaran ini, dan
     * justru soft delete inilah yang membuat penjagaan itu tidak
     * dibutuhkan: menutup baris tidak pernah merusak rujukan, jadi tidak ada
     * yang perlu ditolak.
     *
     * `->delete()` DI SINI SUDAH BERARTI SOFT DELETE — trait SoftDeletes
     * pada model Shift mengubah maknanya menjadi mengisi `deleted_at`, tanpa
     * satu pun pemanggil perlu berubah. 404 untuk shift yang SUDAH tertutup
     * pun datang gratis dari global scope-nya: findOrFail() tidak melihat
     * baris tertutup (edge case "DELETE pada shift yang sudah ditutup").
     *
     * `updated_by` DISTEMPEL SEBELUM PENUTUPAN. Skema shift tidak punya
     * kolom `deleted_by`, sementara baris tertutup kini hidup selamanya —
     * tanpa stempel ini tidak ada jejak siapa yang menutupnya. Spec tidak
     * menyebutkannya; ini keputusan turunan, dan `updated_by` adalah
     * satu-satunya kolom yang tersedia untuk menyimpan jawabannya.
     *
     * Menghapus shift SELALU membesarkan celah; akibatnya ditampilkan di
     * timeline sesudah penghapusan, dan itu bukan alasan untuk menolak. Line
     * yang kehilangan shift TERAKHIR-nya kembali ke 'unconfigured' — bukan
     * 'gap' 1440 — karena coverageEntry() menghitung dari shift HIDUP saja.
     *
     * @throws ModelNotFoundException
     */
    public function delete(string $id): void
    {
        $this->guardAdmin();

        $shift = Shift::findOrFail($id);

        $shift->update(['updated_by' => auth()->id()]);

        $shift->delete();
    }

    /**
     * findOverlap() — shift pembanding PERTAMA (offset terkecil) yang
     * beririsan lebih dari nol menit dengan rentang yang diminta, pada
     * production_line_id yang SAMA, beserta jendela tindihannya.
     *
     * PUBLIC karena ia juga jawaban yang dibutuhkan pratinjau server-side
     * dan test: "shift mana, pada rentang jam berapa".
     *
     * Tumpang tindih diperiksa HANYA di dalam satu line — dua line boleh
     * punya shift pada jam yang sama persis, keduanya memang bekerja
     * bersamaan di line yang berbeda.
     *
     * @return array{shift: Shift, start_offset_minutes: int, end_offset_minutes: int}|null
     */
    public function findOverlap(
        string $productionLineId,
        string $startTime,
        string $endTime,
        ?string $excludeId = null
    ): ?array {
        $candidateSegments = self::segmentsFor(
            self::offsetFromProductionDayStart($startTime),
            self::durationMinutes($startTime, $endTime)
        );

        // JALUR HIDUP 4 — YANG PALING MUDAH TERLEWAT. Baris tertutup TIDAK
        // ikut dibandingkan (global scope SoftDeletes). Bila ia ikut, jam
        // yang baru saja dikosongkan tidak akan pernah bisa dipakai ulang
        // oleh shift penggantinya — dan memakai ulang jam adalah justru
        // alasan soft delete dipilih.
        $others = Shift::query()
            ->where('production_line_id', $productionLineId)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '<>', $excludeId))
            ->get();

        $found = null;

        foreach ($others as $other) {
            $otherSegments = self::segmentsFor(
                self::offsetFromProductionDayStart($other->start_time),
                self::durationMinutes($other->start_time, $other->end_time)
            );

            foreach ($otherSegments as $otherSegment) {
                foreach ($candidateSegments as $candidateSegment) {
                    // IRISAN HARUS LEBIH BESAR DARI NOL. Shift yang berakhir
                    // tepat pada jam mulai shift lain beririsan nol menit →
                    // pergantian shift normal, bukan tindihan.
                    if (self::intersectionMinutes($otherSegment, $candidateSegment) <= 0) {
                        continue;
                    }

                    $start = max($otherSegment[0], $candidateSegment[0]);
                    $end = min($otherSegment[1], $candidateSegment[1]);

                    if ($found === null || $start < $found['start_offset_minutes']) {
                        $found = [
                            'shift' => $other,
                            'start_offset_minutes' => $start,
                            'end_offset_minutes' => $end,
                        ];
                    }
                }
            }
        }

        return $found;
    }

    // ───────────────────────────────────────────────────────────────────
    // Internal
    // ───────────────────────────────────────────────────────────────────

    /**
     * LAPIS KETIGA gate peran (lihat docblock kelas). Dijalankan sebelum
     * kueri apa pun, sehingga peran non-admin menghasilkan 403 FORBIDDEN
     * dengan NOL kueri ke tabel `shifts`.
     *
     * abort(403) dan bukan exception sendiri: ApiExceptionHandler
     * menurunkan `code: FORBIDDEN` dari status 403 sebuah HttpException,
     * sehingga amplopnya { message, code: FORBIDDEN } tanpa kelas baru.
     */
    protected function guardAdmin(): void
    {
        $user = auth()->user();

        $role = $user === null
            ? null
            : ($user->role instanceof UserRole ? $user->role->value : (string) $user->role);

        abort_unless(
            $role === UserRole::Admin->value,
            403,
            'Anda tidak memiliki akses untuk aksi ini.'
        );
    }

    /**
     * Mill WAJIB dipilih. Admin tidak terikat satu mill, jadi tidak ada
     * nilai yang dapat diturunkan dari akun — absen atau kosong → 422
     * VALIDATION_ERROR, dan nol kueri shift berjalan.
     *
     * @throws ValidationException
     */
    protected function requireBusinessUnitId(?string $businessUnitId): string
    {
        $businessUnitId = $this->emptyToNull($businessUnitId);

        if ($businessUnitId === null) {
            throw ValidationException::withMessages([
                'business_unit_id' => 'Mill wajib dipilih terlebih dahulu.',
            ]);
        }

        return (string) $businessUnitId;
    }

    /**
     * Filter Production Line: null = seluruh line mill itu (pemakaian audit
     * yang disengaja). Bila diisi, ia WAJIB line milik mill itu — kalau
     * tidak 422 VALIDATION_ERROR dan bukan 403, karena dari sudut pandang
     * Admin itu parameter yang keliru dan bukan penolakan akses.
     *
     * Str::isUuid() diperiksa SEBELUM kueri: whereKey('bukan-uuid') pada
     * kolom uuid PostgreSQL melempar SQLSTATE 22P02, dan SQLite menelannya
     * diam-diam — jebakan yang sudah terdokumentasi di ScopesToActorMill.
     *
     * @throws ValidationException
     */
    protected function resolveLineFilter(string $businessUnitId, ?string $productionLineId): ?string
    {
        $productionLineId = $this->emptyToNull($productionLineId);

        if ($productionLineId === null) {
            return null;
        }

        $belongsToMill = Str::isUuid((string) $productionLineId)
            && ProductionLine::query()
                ->whereKey($productionLineId)
                ->where('business_unit_id', $businessUnitId)
                ->exists();

        if (! $belongsToMill) {
            throw ValidationException::withMessages([
                'production_line_id' => 'Production Line yang dipilih tidak ada pada mill ini.',
            ]);
        }

        return (string) $productionLineId;
    }

    /**
     * Apakah `$productionLineId` masih punya shift HIDUP selain
     * `$excludeId`? Satu-satunya pemakainya adalah pembedaan jam berimpit:
     * shift 24 jam hanya sah pada line yang kosong.
     *
     * BARIS TERTUTUP TIDAK DIHITUNG, dan itu bukan kebetulan — ia
     * dikecualikan oleh global scope SoftDeletes karena kueri ini berangkat
     * dari Shift::query(). Konsekuensinya memang yang diinginkan: line yang
     * seluruh shift-nya sudah ditutup kembali menerima shift 24 jam, sama
     * seperti line yang belum pernah diberi shift.
     *
     * `$excludeId` menjaga agar sebuah shift tidak menghitung DIRINYA
     * SENDIRI sebagai "shift lain" — tanpa itu, mengubah satu-satunya shift
     * sebuah line menjadi 24 jam tidak akan pernah bisa disimpan.
     */
    protected function lineHasOtherLiveShift(string $productionLineId, ?string $excludeId): bool
    {
        return Shift::query()
            ->where('production_line_id', $productionLineId)
            ->when($excludeId !== null, fn ($query) => $query->where('id', '<>', $excludeId))
            ->exists();
    }

    /**
     * Validasi jalur tulis. Mengembalikan atribut yang SUDAH dinormalkan ke
     * 'H:i:s' sehingga kolom TIME menerima bentuk yang sama di PostgreSQL
     * maupun SQLite.
     *
     * KEUNIKAN NAMA DIPERIKSA PER LINE, bukan global — dua line boleh punya
     * shift bernama sama. Aturannya hanya dipasang bila production_line_id
     * berbentuk UUID: Rule::unique() dengan where() berisi id yang bukan
     * UUID akan memicu SQLSTATE 22P02 di PostgreSQL, padahal id itu memang
     * sudah ditolak oleh rule-nya sendiri.
     *
     * KEUNIKAN MENGECUALIKAN BARIS TERTUTUP, DAN PENGECUALIAN ITU DITULIS
     * TANGAN. Rule::unique() membangun kueri Query Builder, bukan Eloquent,
     * sehingga GLOBAL SCOPE SoftDeletes TIDAK BERLAKU PADANYA — satu-satunya
     * jalur shift di seluruh service ini yang tidak dijangkau scope itu.
     * Tanpa whereNull('deleted_at') di bawah, sebuah 'Pagi' yang ditutup akan
     * memblokir 'Pagi' penggantinya selamanya padahal partial unique index
     * (2026_10_16_000001) mengizinkannya — dua penegak yang berselisih, dan
     * yang lebih ketatlah yang menang.
     *
     * JAM BERIMPIT BERARTI SHIFT 24 JAM (keputusan pemilik produk
     * 2026-10-16, menggantikan larangan tanpa syarat di v1 yang tidak dapat
     * benar bersamaan dengan janji "shift 24 jam itu sah"). `07:00 → 07:00`
     * adalah satu-satunya cara mengungkapkan 1440 menit, jadi ia DITERIMA —
     * tetapi HANYA pada line yang belum punya shift HIDUP lain. Pada line
     * yang sudah terisi ia ditolak sebagai VALIDATION_ERROR durasi nol, dan
     * SENGAJA BUKAN SHIFT_OVERLAP: shift 24 jam menurut definisinya tidak
     * dapat berdampingan dengan shift mana pun, jadi di situ jam berimpit
     * hanya bisa berarti salah ketik — dan salah ketik bukan tumpang tindih.
     * Pemeriksaannya dilakukan SESUDAH normalisasi, karena '07:00' dan
     * '07:00:00' adalah jam yang sama dan perbandingan string tidak
     * mengetahuinya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    protected function validate(array $data, ?string $excludeId): array
    {
        $productionLineId = $this->emptyToNull($data['production_line_id'] ?? null);

        $payload = [
            'production_line_id' => $productionLineId,
            'name' => $this->emptyToNull($data['name'] ?? null),
            'start_time' => $this->emptyToNull($data['start_time'] ?? null),
            'end_time' => $this->emptyToNull($data['end_time'] ?? null),
        ];

        $nameRules = ['required', 'string', 'max:255'];

        if (is_string($productionLineId) && Str::isUuid($productionLineId)) {
            $nameUniqueRule = Rule::unique('shifts', 'name')
                ->where(fn ($query) => $query
                    ->where('production_line_id', $productionLineId)
                    // Baris tertutup tidak memblokir nama penggantinya;
                    // Rule::unique() tidak melihat global scope SoftDeletes.
                    ->whereNull('deleted_at'));

            if ($excludeId !== null) {
                $nameUniqueRule = $nameUniqueRule->ignore($excludeId);
            }

            $nameRules[] = $nameUniqueRule;
        }

        $rules = [
            'production_line_id' => [
                'required',
                'string',
                function (string $attribute, mixed $value, callable $fail) {
                    if (! Str::isUuid((string) $value)
                        || ! ProductionLine::query()->whereKey($value)->exists()) {
                        $fail('Production Line yang dipilih tidak ditemukan.');
                    }
                },
            ],
            'name' => $nameRules,
            'start_time' => ['required', 'string', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'string', 'date_format:H:i,H:i:s'],
        ];

        $messages = [
            'production_line_id.required' => 'Production Line wajib dipilih.',
            'name.required' => 'Nama Shift wajib diisi.',
            'name.max' => 'Nama Shift maksimal 255 karakter.',
            'name.unique' => 'Nama Shift sudah dipakai pada Production Line ini.',
            'start_time.required' => 'Jam Mulai wajib diisi.',
            'start_time.date_format' => 'Jam Mulai tidak valid, gunakan format JJ:MM.',
            'end_time.required' => 'Jam Selesai wajib diisi.',
            'end_time.date_format' => 'Jam Selesai tidak valid, gunakan format JJ:MM.',
        ];

        $validated = Validator::make($payload, $rules, $messages)->validate();

        $validated['start_time'] = $this->normalizeTime($validated['start_time']);
        $validated['end_time'] = $this->normalizeTime($validated['end_time']);

        if (self::minuteOfDay($validated['start_time']) === self::minuteOfDay($validated['end_time'])
            && $this->lineHasOtherLiveShift($validated['production_line_id'], $excludeId)) {
            // Jam berimpit pada line yang SUDAH terisi: ditolak sebagai
            // durasi nol. Pada line yang masih kosong cabang ini tidak
            // dijalankan sama sekali dan shift 24 jam-nya tersimpan —
            // durationMinutes() membaca modulo 0 sebagai 1440, sehingga ia
            // menggambar satu blok penuh tanpa celah.
            throw ValidationException::withMessages([
                // Frasa "berdurasi nol" DIPERTAHANKAN: spec v2 tetap menyebut
                // "pesan durasi nol" untuk cabang ini (business_logic 17,
                // edge case "start_time == end_time"), dan itu memang yang
                // dibaca Admin — pada line yang sudah terisi, jam berimpit
                // hanya bisa berarti salah ketik.
                'end_time' => 'Jam Selesai hanya boleh sama dengan Jam Mulai bila Production Line ini '
                    .'belum punya shift lain; di sini ia terbaca sebagai shift berdurasi nol. Shift 24 '
                    .'jam tidak dapat berdampingan dengan shift mana pun.',
            ]);
        }

        return $validated;
    }

    /**
     * Pesan SHIFT_OVERLAP menyebut NAMA shift yang bertindih DAN RENTANG JAM
     * tindihannya. Pesan yang hanya berbunyi 'jam bertindih' memaksa Admin
     * mencari sendiri penyebabnya di antara belasan baris.
     *
     * @throws ShiftOverlapException
     */
    protected function guardAgainstOverlap(
        string $productionLineId,
        string $startTime,
        string $endTime,
        ?string $excludeId
    ): void {
        $conflict = $this->findOverlap($productionLineId, $startTime, $endTime, $excludeId);

        if ($conflict === null) {
            return;
        }

        /** @var Shift $other */
        $other = $conflict['shift'];

        throw new ShiftOverlapException(sprintf(
            'Jam %s–%s bertindih dengan shift "%s" (%s) pada Production Line yang sama. '
            .'Ubah jam shift ini atau sesuaikan shift tersebut terlebih dahulu.',
            self::timeFromOffset($conflict['start_offset_minutes']),
            self::timeFromOffset($conflict['end_offset_minutes']),
            $other->name,
            $this->rangeLabel($other)
        ));
    }

    /**
     * Urutan tampil SELALU jam mulai relatif awal hari produksi — bukan
     * kolom urutan manual, bukan abjad nama, bukan urutan penambahan. Satu
     * sumber kebenaran yang tidak dapat berselisih dengan jam shift-nya
     * sendiri. Nama hanya dipakai sebagai pemecah seri agar urutannya stabil
     * (dua shift tidak boleh punya jam mulai sama pada satu line, tetapi
     * pada SEMUA line sekaligus itu wajar).
     *
     * @param  Collection<int, Shift>  $shifts
     * @return Collection<int, Shift>
     */
    protected function orderShifts(Collection $shifts): Collection
    {
        return $shifts
            ->sortBy(fn (Shift $shift) => [
                self::offsetFromProductionDayStart($shift->start_time),
                $shift->name,
            ])
            ->values();
    }

    /**
     * Satu entri data[] — kontrak respons daftar. `position` diturunkan
     * SERVER dari jam mulai relatif 07:00 dan dipakai blade untuk
     * mengurutkan TABEL maupun BLOK TIMELINE: satu sumber urutan untuk dua
     * tampilan, karena dua pengurutan yang berdiri sendiri adalah cara
     * tercepat membuat keduanya berselisih.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Shift $shift, ProductionLine $line, int $position): array
    {
        return [
            'id' => $shift->id,
            'production_line_id' => $shift->production_line_id,
            'production_line_name' => $line->name,
            'name' => $shift->name,
            'start_time' => self::displayTime($shift->start_time),
            'end_time' => self::displayTime($shift->end_time),
            'crosses_midnight' => $this->crossesMidnight($shift),
            'duration_minutes' => self::durationMinutes($shift->start_time, $shift->end_time),
            // Teks jam durasi dihitung DI SINI supaya blade tidak melakukan
            // satu pun aritmetika jam (implementation_notes #6).
            'duration_hours_text' => $this->hoursText(
                self::durationMinutes($shift->start_time, $shift->end_time)
            ),
            'start_offset_minutes' => self::offsetFromProductionDayStart($shift->start_time),
            'position' => $position,
            'time_label' => $this->rangeLabel($shift),
        ];
    }

    /**
     * Bentuk respons create()/update() — satu shift, tanpa konteks line.
     *
     * @return array<string, mixed>
     */
    protected function toWriteRow(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'production_line_id' => $shift->production_line_id,
            'name' => $shift->name,
            'start_time' => self::displayTime($shift->start_time),
            'end_time' => self::displayTime($shift->end_time),
            'crosses_midnight' => $this->crossesMidnight($shift),
            'duration_minutes' => self::durationMinutes($shift->start_time, $shift->end_time),
        ];
    }

    /**
     * Satu blok 'shift' pada timeline — SATU blok dengan durasi utuh, juga
     * ketika ia melewati tengah malam. `crosses_midnight` hanya menerangkan
     * bentuknya kepada pembaca; ia tidak memecah bloknya.
     *
     * @return array<string, mixed>
     */
    protected function shiftBlock(Shift $shift): array
    {
        return [
            'kind' => 'shift',
            'shift_id' => $shift->id,
            'label' => $shift->name,
            'start_offset_minutes' => self::offsetFromProductionDayStart($shift->start_time),
            'duration_minutes' => self::durationMinutes($shift->start_time, $shift->end_time),
            'time_label' => $this->rangeLabel($shift),
            'crosses_midnight' => $this->crossesMidnight($shift),
        ];
    }

    /**
     * '23:00 — 07:00 +1' untuk shift yang melewati tengah malam, supaya jam
     * selesai yang lebih kecil tidak terbaca sebagai salah input.
     */
    protected function rangeLabel(Shift $shift): string
    {
        return sprintf(
            '%s — %s%s',
            self::displayTime($shift->start_time),
            self::displayTime($shift->end_time),
            $this->crossesMidnight($shift) ? ' +1' : ''
        );
    }

    protected function crossesMidnight(Shift $shift): bool
    {
        return self::minuteOfDay($shift->end_time) < self::minuteOfDay($shift->start_time);
    }

    /**
     * Komplemen gabungan beberapa segmen di dalam [0, 1440] — yaitu
     * CELAHNYA. Segmen digabung dulu, jadi data lama yang (karena dibuat
     * sebelum aturan ini, atau lewat factory) saling bertindih tidak membuat
     * menit terhitung dua kali.
     *
     * @param  list<array{0: int, 1: int}>  $segments
     * @return list<array{0: int, 1: int}>
     */
    protected function complementSegments(array $segments): array
    {
        usort($segments, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $gaps = [];
        $cursor = 0;

        foreach ($segments as $segment) {
            if ($segment[0] > $cursor) {
                $gaps[] = [$cursor, $segment[0]];
            }

            $cursor = max($cursor, $segment[1]);
        }

        if ($cursor < self::DAY_MINUTES) {
            $gaps[] = [$cursor, self::DAY_MINUTES];
        }

        return $gaps;
    }

    /**
     * Menit → jam sebagai ANGKA YANG DISEBUT ('4', '1,5'), bukan 'ada
     * celah' tanpa angka. Koma desimal mengikuti penulisan Indonesia.
     */
    protected function hoursText(int $minutes): string
    {
        $text = number_format($minutes / 60, 1, ',', '');

        return rtrim(rtrim($text, '0'), ',');
    }

    /** Kolom TIME menerima 'H:i:s' di PostgreSQL maupun SQLite. */
    protected function normalizeTime(string $time): string
    {
        $minuteOfDay = self::minuteOfDay($time);

        return sprintf('%02d:%02d:00', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function meta(array $items, int $total, int $perPage, int $page): array
    {
        return Pagination::format(
            new LengthAwarePaginator($items, $total, $perPage, $page)
        )['meta'];
    }

    /**
     * '' → null. Kolom uuid/nullable PostgreSQL menolak string kosong, dan
     * jebakan itu sudah terdokumentasi di proyek ini — di sini ia juga yang
     * membuat 'business_unit_id=' terbaca sebagai "mill belum dipilih"
     * alih-alih sebagai mill bernama string kosong.
     */
    protected function emptyToNull(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === '' || $value === null) ? null : $value;
    }
}
