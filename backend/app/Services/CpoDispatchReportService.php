<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Support\ExportValue;
use App\Support\ReportPeriodDays;
use App\Support\SheetWriter;
use DateTimeInterface;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CpoDispatchReportService — screen-166--laporan-cpo-dispatch-web /
 * usecase-172 (Laporan Periode CPO Dispatch), dan pasangan mobile-nya
 * screen-167 yang memakai KETIGA rute data yang sama.
 *
 * Dipakai bersama oleh controller API (App\Http\Controllers\Api\
 * CpoDispatchReportController) dan komponen Livewire (App\Livewire\Dashboard\
 * LaporanCpoDispatch), sehingga halaman web dan API tidak pernah dapat
 * menyebut angka yang berbeda. Kerangka akses, resolusi mill/line/periode,
 * agregasi per-transaksi, dan ekspor disalin dari KernelDispatchReportService
 * (screen-164); yang BARU di sini hanya yang diuraikan di bawah.
 *
 * BACA SAJA SECARA KONSTRUKSI: setiap metode publik adalah SELECT, dan prefix
 * /api/cpo-dispatch-reports tidak membawa satu pun POST/PUT/PATCH/DELETE.
 *
 * ---------------------------------------------------------------------
 * SATU-SATUNYA LAPORAN PENGIRIMAN YANG DAPAT MENJAWAB PERTANYAAN WAKTU
 * ---------------------------------------------------------------------
 * Hanya `cpo_dispatch_details` yang membawa `time_in` dan `time_out`. Dari
 * keduanya lahir dua blok yang tidak dimiliki laporan pengiriman lain:
 * `hourly` (kapan tanker DATANG) dan `turnaround` (berapa lama tanker berada
 * di dalam pabrik).
 *
 * JEBAKAN TERBESAR: kedua kolom bertipe `time` TANPA TANGGAL (migrasi
 * 2026_08_31_000009: `time('time_in')->nullable()`). Tanker yang masuk 23:30 dan
 * keluar 00:45 secara mentah memberi selisih −1365 menit. Rumusnya: menit =
 * minuteOfDay(keluar) − minuteOfDay(masuk); bila NEGATIF tambahkan 1440 →
 * 75 menit. Bentuk masalahnya SAMA dengan shift melewati tengah malam di
 * ShiftService, dan pembacaan menit-dalam-hari memakai satu-satunya fungsinya
 * (ShiftService::minuteOfDayOf) — bukan salinan kedua. ShiftService::
 * durationMinutes() sengaja TIDAK dipakai: ia membaca jam berimpit sebagai 1440
 * (shift 24 jam), sedangkan di sini jam berimpit adalah NOL menit, dan ia juga
 * tidak memberi tahu apakah sebuah pengiriman menyeberang tengah malam —
 * padahal angka itu diterbitkan.
 *
 * TIGA KASUS YANG TIDAK BOLEH DICAMPUR:
 *   keluar <  masuk → +1440, dibaca hari berikutnya, masuk crossed_midnight_count;
 *   keluar == masuk → NOL menit, apa adanya, TIDAK masuk crossed_midnight_count
 *                     (`<`, bukan `<=`: kesalahan satu karakter yang hasilnya —
 *                     waktu putar 24 jam — tampak masuk akal);
 *   keluar >  masuk → selisih biasa.
 *
 * PENAFSIRAN ITU TIDAK BOLEH MENGGESER SEBARAN JAM. Kelompok jam ditentukan
 * HANYA oleh jam MASUK, tidak pernah oleh jam keluar atau hasil penafsiran;
 * pengiriman 23:30 tetap di kelompok 23. Karena itu dispatchesOf() menyimpan
 * kedua menit-dalam-hari MENTAH dan KEDUA perhitungan membacanya terpisah —
 * tidak ada baris yang 'dinormalkan' lebih dulu.
 *
 * TIDAK ADA BATAS KEWAJARAN. Stasiun ini tidak punya master batas waktu putar,
 * jadi memilih batas berarti mengarangnya: angka 23 jam dilaporkan apa adanya,
 * tetap menjadi max dan ikut rata-rata, tanpa warna, lencana, maupun hitungan
 * pelanggaran. turnaround._note hanya MENYATAKAN bahwa angka semacam itu patut
 * diperiksa pembacanya.
 *
 * ---------------------------------------------------------------------
 * EMPAT PENYEBUT YANG BERBEDA — JANGAN DISEDERHANAKAN
 * ---------------------------------------------------------------------
 *   (a) dispatch_count                — seluruh pengiriman periode,
 *   (b) net_weight_dispatch_count     — yang berat bersihnya terisi,
 *   (c) hourly.recorded_count         — yang JAM MASUK-nya terisi,
 *   (d) turnaround.measured_count     — yang jam masuk DAN jam keluarnya terisi,
 * ditambah penyebut masing-masing keempat ukuran mutu. Kolom-kolomnya terisi
 * SALING BEBAS, sehingga satu angka 'jumlah pengiriman' sebagai penyebut semuanya
 * tampak rapi dan salah secara sistematis. Keempatnya diterbitkan dan dinamai di
 * `_note_four_denominators`.
 *
 * ---------------------------------------------------------------------
 * NOL KOLOM BERPILIHAN TERTUTUP => NOL REKAP BERLABEL
 * ---------------------------------------------------------------------
 * Satu-satunya <select> di form CPO Dispatch adalah pemilih production line.
 * Karena itu tidak ada rekap berlabel dan service ini tidak memakai satu pun
 * pemetaan label enum bersama. Ketiadaannya dinyatakan lewat
 * `_note_no_labeled_recap`, karena pembaca yang datang dari Laporan Kernel
 * Dispatch akan mencarinya. Gantinya TIGA rekap teks bebas — by_transport_company,
 * by_storage_tank_source, by_destination_buyer — masing-masing dengan baris
 * `value = null` ('tidak tercatat') sendiri.
 *
 * ---------------------------------------------------------------------
 * SATUAN HITUNG, LINGKUP BARIS, ISOLASI — identik dengan screen-164
 * ---------------------------------------------------------------------
 * SATU BARIS `cpo_dispatch_details` = SATU PENGIRIMAN (satu tanker). Record
 * induk disaring menurut kolom SNAPSHOT `production_line_id` miliknya, jenis
 * stasiun 'cpo-dispatch', dan `date` INDUK di dalam periode (inklusif,
 * whereDate()). Status TIDAK menyaring; draft ikut terhitung dan
 * draft_dispatch_count menyatakannya. `net_weight_mt` dibaca apa adanya, tidak
 * pernah dihitung ulang dari bruto − tara. NULL bukan nol.
 *
 * production_line_id WAJIB (tanpa 'semua line', tanpa pilih-otomatis); line mill
 * lain → 403. business_unit_id DIABAIKAN untuk peran terikat mill termasuk
 * Operator dan WAJIB bagi Admin. Akun terikat dengan business_unit_id NULL →
 * 422, bukan 403, dan daftar seluruh mill tidak pernah dibaca. Operator diterima
 * pada ketiga rute data sejak commit pertama (screen-167), dan admisinya SATU
 * perubahan di TIGA tempat: daftar peran di routes/api.php, guardAccess(), dan
 * cabang terikat-mill di resolveBusinessUnit(). businessUnitOptions() tetap
 * Admin saja, ditegakkan di sini bukan oleh middleware.
 *
 * ---------------------------------------------------------------------
 * SELURUH AGREGASI DI PHP, NOL DI SQL
 * ---------------------------------------------------------------------
 * Suite berjalan di SQLite dan produksi di PostgreSQL. Agregat SQL atas kolom
 * nullable, aritmetika kolom TIME, dan pembulatan berbeda antar-mesin, dan
 * justru aturan penyebut-tersendiri dan null-bukan-nol yang hilang dalam
 * perbedaan itu. Kueri di bawah hanya memilih dan menyaring dengan helper
 * portabel (whereDate / whereKey); min/avg/max mutu, sebaran jam, dan waktu putar
 * dihitung di PHP. Urutan SELALU dinyatakan eksplisit.
 */
class CpoDispatchReportService
{
    /**
     * Batas baris ekspor, dihitung atas BARIS DETAIL yang akan diterbitkan
     * (satu pengiriman = satu baris berkas), bukan record induk. Dibaca lewat
     * `static::` supaya uji unit dapat menurunkannya di subclass.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini, diambil dari App\Enums\StationType
     * dan tidak ditulis sebagai literal. Nilainya 'cpo-dispatch' BERTANDA HUBUNG;
     * kunci snake_case akan ter-compile dan tidak pernah cocok dengan satu baris
     * master pun.
     */
    protected const STATION_TYPE = StationTypeEnum::CpoDispatch->value;

    /**
     * Format ekspor: daftar TERTUTUP {csv, xlsx}, PEKA HURUF (controller hanya
     * trim, bukan lowercase). Penerjemahan ke SheetWriter::FORMAT_EXCEL terjadi
     * di sheetFormatOf() — SheetWriter::open('xlsx') akan diam-diam menghasilkan
     * CSV berekstensi .xlsx.
     */
    public const SUPPORTED_FORMATS = ['csv', 'xlsx'];

    /** Status yang dihitung sebagai log sheet belum selesai (draft). */
    public const DRAFT_STATUSES = [
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ];

    /**
     * KEEMPAT UKURAN MUTU, masing-masing dengan PENYEBUTNYA SENDIRI. Didaftar
     * sebagai konstanta supaya qualityOf() me-loop-nya dan tak ada tempat yang
     * dapat memakai penyebut bersama. Urutannya mengikuti kolom pada layar Detail
     * stasiun ini (FFA → Moisture → Impurities → DOBI).
     *
     * `dobi` BUKAN persen: ia indeks tanpa satuan (kolom float, form
     * `<input type="number" step="0.01">`). Hanya tiga yang pertama berupa persen.
     *
     * @var array<int, string>
     */
    public const QUALITY_MEASURES = [
        'ffa_percent',
        'moisture_percent',
        'impurities_percent',
        'dobi',
    ];

    /**
     * Desimal ukuran mutu: TIGA — kadar kotoran bergerak di orde perseratus
     * sehingga dua desimal melipat dua nilai berbeda menjadi satu. Pembulatan
     * terjadi di sini saja, tidak di blade, API, maupun ekspor.
     */
    public const QUALITY_DECIMALS = 3;

    /** Desimal rata-rata waktu putar (menit). Pembulatan hanya terjadi di service. */
    public const TURNAROUND_DECIMALS = 2;

    /** Menit dalam sehari — dasar penambahan hari berikutnya pada waktu putar. */
    public const DAY_MINUTES = 1440;

    /**
     * Kolom teks bebas yang direkap, dalam urutan tampil: kunci payload =>
     * atribut pada objek pengiriman. Satu konstanta, bukan tiga salinan kode.
     *
     * @var array<string, string>
     */
    public const TEXT_RECAPS = [
        'by_transport_company' => 'transport_company',
        'by_storage_tank_source' => 'storage_tank_source',
        'by_destination_buyer' => 'destination_buyer',
    ];

    /**
     * Berapa kolom pertama EXPORT_HEADER yang merupakan KONTEKS, diulang
     * VERBATIM di setiap baris: Periode, Rentang Periode, Mill, Production Line,
     * Tanggal Record, Status. Dua terakhir berbeda antar-record (tanggal dan
     * status RECORD INDUK), dan itu berlaku identik di laporan lain.
     */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 6;

    /**
     * DUA PULUH SATU kolom detail ekspor, urut berkas (business_logic 24).
     * `shift`, `time_in` dan `time_out` ikut meski shift tidak ada di layar:
     * ekspor adalah rincian MENTAH. Jam diekspor APA ADANYA — tanpa penafsiran
     * tengah malam dan tanpa kolom waktu putar — supaya tidak ada dua sumber
     * angka yang dapat menyimpang tanpa ada yang membandingkannya.
     *
     * @var array<int, string>
     */
    public const DETAIL_EXPORT_FIELDS = [
        'event_date',
        'shift',
        'time_in',
        'time_out',
        'waybill_number',
        'tanker_plate_no',
        'transport_company',
        'driver_name',
        'storage_tank_source',
        'seal_no_top',
        'seal_no_bottom',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'ffa_percent',
        'moisture_percent',
        'impurities_percent',
        'dobi',
        'destination_buyer',
        'weighbridge_operator',
        'findings',
    ];

    /**
     * Label kolom ekspor: 6 konteks + 21 detail = 27. LABEL DETAIL MENGIKUTI
     * LAYAR DETAIL stasiun ini (resources/views/livewire/data/
     * detail-cpo-dispatch.blade.php) VERBATIM, bukan nama kolom database.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Rentang Periode',
        'Mill',
        'Production Line',
        'Tanggal Record',
        'Status',
        'Tanggal Kejadian',
        'Shift',
        'Time In',
        'Time Out',
        'Waybill Number',
        'Tanker Plate No',
        'Transport Company',
        'Driver Name',
        'Storage Tank Source',
        'Seal No (Top)',
        'Seal No (Bottom)',
        'Gross Weight (MT)',
        'Tare Weight (MT)',
        'Net Weight (MT)',
        'FFA (%)',
        'Moisture (%)',
        'Impurities (%)',
        'DOBI',
        'Destination/Buyer',
        'Weighbridge Operator',
        'Findings',
    ];

    /** Prosa `quality._note` — dirender UTUH di layar. */
    public const NOTE_QUALITY_DENOMINATORS = 'KEEMPAT PENYEBUT DI BAWAH BOLEH BERBEDA SATU DENGAN '
        .'LAINNYA, dan boleh berbeda dari jumlah pengiriman periode ini. Sebabnya bukan cacat data: '
        .'FFA, kadar air, kadar kotoran, dan DOBI adalah EMPAT UJI YANG BERDIRI SENDIRI, dan satu '
        .'pengiriman boleh punya sebagian hasil uji saja. Karena itu setiap ukuran menyebut penyebutnya '
        .'SENDIRI — berapa pengiriman yang kolom ITU-nya benar-benar terisi — dan tidak ada yang '
        .'meminjam penyebut dari yang lain. Pembaca yang melihat FFA dihitung atas 12 pengiriman '
        .'sementara DOBI atas 7 sedang melihat dua uji yang tidak selalu dilakukan bersama, BUKAN lima '
        .'hasil uji yang hilang. Satu penyebut tunggal akan tampak lebih rapi dan salah untuk '
        .'setidaknya tiga dari empat. Ketika sebuah ukuran tidak punya satu hasil uji pun, hanya ukuran '
        .'ITU yang ditulis sebagai tanda pisah; ketiga ukuran lain tetap menampilkan angkanya.';

    /** Prosa `quality.dobi._note` — diterbitkan DI DALAM blok dobi agar tak terpisah dari angkanya. */
    public const NOTE_DOBI_UNITLESS = 'DOBI adalah INDEKS TANPA SATUAN, bukan persen. Ketiga ukuran '
        .'lain di kotak ini (FFA, kadar air, kadar kotoran) berupa persen, sedangkan DOBI duduk di '
        .'sebelah mereka sebagai angka murni: ia dicetak tanpa tanda % dan tidak dibandingkan dengan '
        .'ketiganya. Satuan yang ditempelkan pada DOBI karena kebiasaan akan salah.';

    /** Prosa `_note_four_denominators` — dirender UTUH di layar. */
    public const NOTE_FOUR_DENOMINATORS = 'Laporan ini memakai EMPAT PENYEBUT YANG BERBEDA, dan '
        .'keempatnya dicetak di kartunya masing-masing: (a) dispatch_count — seluruh pengiriman periode; '
        .'(b) net_weight_dispatch_count — pengiriman yang berat bersihnya terisi; (c) '
        .'hourly.recorded_count — pengiriman yang JAM MASUK-nya terisi; dan (d) turnaround.measured_count '
        .'— pengiriman yang jam masuk DAN jam keluarnya KEDUANYA terisi. Ditambah penyebut masing-masing '
        .'keempat ukuran mutu (FFA, kadar air, kadar kotoran, DOBI). Sebabnya: berat, jam masuk, jam keluar, '
        .'dan tiap kolom mutu diisi SALING BEBAS — satu pengiriman boleh punya jam masuk tanpa jam keluar, '
        .'atau berat tanpa jam sama sekali. Akibatnya: satu angka \'jumlah pengiriman\' yang dipakai '
        .'sebagai penyebut semuanya akan tampak rapi dan SALAH SECARA SISTEMATIS — ia mengempiskan setiap '
        .'rata-rata yang kolomnya jarang terisi tanpa tanda apa pun. Keempatnya dicetak berdampingan, '
        .'termasuk ketika kebetulan bernilai sama.';

    /** Prosa `_note_no_labeled_recap` — dirender UTUH di layar. */
    public const NOTE_NO_LABELED_RECAP = 'Layar ini TIDAK punya rekap berlabel, dan ketiadaannya '
        .'disengaja: stasiun CPO Dispatch tidak punya satu pun kolom berpilihan tertutup — satu-satunya '
        .'daftar pilihan pada formulirnya adalah pemilih production line. Berbeda dari Laporan Kernel '
        .'Dispatch, yang punya satu rekap berlabel (status pelepasan gerbang), seluruh kolom kategoris di '
        .'sini adalah teks yang diketik, sehingga direkap per nilai apa adanya (pengangkut, tangki '
        .'sumber, pembeli tujuan) tanpa pelabelan dan tanpa pemetaan.';

    /** Prosa `_note_no_quality_thresholds` — dirender UTUH di layar. */
    public const NOTE_NO_QUALITY_THRESHOLDS = 'Tidak ada satu nilai mutu pun di layar ini yang ditandai '
        .'sebagai di luar batas, dan ketiadaan penandaan itu disengaja: stasiun CPO Dispatch tidak punya '
        .'tabel master ambang mutu apa pun — tidak ada baris acuan untuk FFA, kadar air, kadar kotoran, '
        .'maupun DOBI. Menurunkan ambang dari data periode ini sendiri akan menghasilkan garis yang '
        .'terlihat berwibawa lalu dibaca sebagai batas resmi padahal bukan. Keempat ukuran karena itu '
        .'ditampilkan apa adanya, tanpa warna peringatan; penilaian layak atau tidak layak tetap di '
        .'tangan pembacanya, yang memegang spesifikasi pembelinya.';

    /** Prosa `_note_shift_not_published` — dirender UTUH di layar. */
    public const NOTE_SHIFT_NOT_PUBLISHED = 'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan '
        .'daftar nilai tertutup dan bukan relasi ke master, sehingga menyebarnya berarti mengelompokkan '
        .'EJAAN, bukan giliran kerja. Kolom itu karena itu TIDAK diterbitkan di payload maupun di layar '
        .'ini. Ia TETAP ikut di berkas ekspor pada setiap baris pengiriman, bersama jam masuk dan jam '
        .'keluar apa adanya, nomor surat jalan, nomor kendaraan, nama pengemudi, kedua nomor segel, dan '
        .'operator timbang, karena ekspor adalah rincian mentah.';

    /** Prosa `compliance._note`. */
    public const NOTE_COMPLIANCE = 'Nomor surat jalan dan kedua nomor segel adalah JEJAK KEPATUHAN, '
        .'bukan ukuran produksi: ketiganya menyatakan dokumen angkut yang tidak terbit. Karena itu '
        .'ketiganya diterbitkan HANYA sebagai penghitung kehadiran dan tidak pernah dirata-rata, '
        .'diurutkan, atau digrafikkan. SEGEL ATAS DAN SEGEL BAWAH DIHITUNG TERPISAH DAN TIDAK PERNAH '
        .'DIJUMLAHKAN MENJADI SATU ANGKA: satu tanker yang kehilangan satu segel adalah keadaan yang '
        .'berbeda dari dua tanker yang masing-masing kehilangan satu. Satu pengiriman yang kehilangan '
        .'KEDUA segelnya terhitung di KEDUA penghitung — itu dua fakta, bukan penghitungan ganda. Bila '
        .'seluruh pengiriman periode lengkap, ketiga penghitung menunjukkan nol dan nol itu TETAP '
        .'diterbitkan: nol memberi tahu pembaca bahwa pemeriksaannya memang berjalan. Nomor-nomornya '
        .'sendiri tidak diterbitkan di payload layar; ia hanya ada di ekspor.';

    /** Prosa `hourly._note`. */
    public const NOTE_HOURLY = 'Sebaran dihitung menurut JAM MASUK tanker, BUKAN jam keluar: yang '
        .'ditanyakan adalah kapan tanker datang, karena itulah yang membebani jembatan timbang. Jam '
        .'keluar tidak pernah dipakai sebagai pengganti ketika jam masuk kosong; pengiriman semacam itu '
        .'masuk kelompok \'jam tidak tercatat\'. Penafsiran menyeberang tengah malam pada WAKTU PUTAR '
        .'TIDAK menggeser kelompok jam: pengiriman yang masuk 23:30 tetap berada di kelompok 23. Jumlah '
        .'ke-24 kelompok ditambah kelompok jam tidak tercatat sama dengan jumlah pengiriman periode.';

    /** Prosa `turnaround._note`. */
    public const NOTE_TURNAROUND = 'Jam masuk dan jam keluar dicatat TANPA TANGGAL. Jam keluar yang '
        .'LEBIH KECIL daripada jam masuk dibaca sebagai HARI BERIKUTNYA (23:30 lalu 00:45 = 75 menit), '
        .'bukan sebagai data salah, dan jumlah pengiriman yang dibaca demikian diterbitkan tersendiri '
        .'(crossed_midnight_count) karena angkanya lahir dari penafsiran, bukan pembacaan langsung. Jam '
        .'yang sama persis menghasilkan NOL menit, dilaporkan apa adanya. Waktu putar yang tampak tidak '
        .'wajar TIDAK dipotong dan TIDAK dibuang — stasiun ini tidak punya master batas kewajaran, jadi '
        .'memilih batas berarti mengarangnya — melainkan tetap menjadi nilai tertinggi dan ikut rata-rata; '
        .'angka seperti itu patut DIPERIKSA PEMBACA. Waktu putar hanya dihitung bila kedua jam tercatat.';

    /**
     * code => name dari tabel master `station_types`, dimemoisasi per instance.
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    // ------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------

    /**
     * business_logic 1 — mill mana yang boleh dilihat pemanggil.
     *
     * Operator / Supervisor / Mill Management: SELALU business_unit_id akunnya;
     * argumen `$requestedBusinessUnitId` dibuang begitu saja, sehingga menyebut
     * id mill lain adalah tindakan tanpa efek yang tetap menjawab data pemanggil
     * sendiri dengan HTTP 200. Membandingkannya lalu menolak akan membocorkan
     * keberadaan mill lain lewat perbedaan 403 dan 200.
     *
     * OPERATOR BERADA DI CABANG INI DAN TIDAK DI TEMPAT LAIN: cabang di bawah
     * adalah cabang ADMIN, tempat argumennya JUSTRU dihormati.
     *
     * Admin: nilainya WAJIB datang dari pemanggil. Absen → 422
     * VALIDATION_ERROR pada field business_unit_id. Tidak ada mill default dan
     * tidak ada 'mill pertama'.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Supervisor->value
            || $role === UserRole::MillManagement->value
            // screen-167 (laporan CPO Dispatch mobile). Operator WAJIB di
            // sini: cabang di bawah memperlakukan apa pun yang sampai padanya
            // sebagai Admin, dan di sana business_unit_id kiriman DIHORMATI.
            || $role === UserRole::Operator->value) {
            // business_unit_id kiriman klien sengaja DIBUANG — tidak
            // divalidasi, tidak dibandingkan, dibuang.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — satu-satunya peran yang tidak terikat satu mill, dan
        // satu-satunya yang dapat mencapai titik ini.
        if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
            throw ValidationException::withMessages([
                'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
            ]);
        }

        return $requestedBusinessUnitId;
    }

    /**
     * Opsi pemilih Mill — ADMIN SAJA.
     *
     * Supervisor, Mill Management dan Operator masing-masing terikat satu mill
     * dan tidak punya pemilih sama sekali, jadi meminta daftar ini adalah 403,
     * bukan daftar tersaring berisi satu entri. Penolakan diangkat DI SINI dan
     * bukan oleh middleware rute: middleware prefix ini menerima keempat peran
     * demi ketiga rute data, sehingga penjaga di sana tidak akan menutup rute
     * ini sama sekali — dan dengan begini membersihkan middleware tidak pernah
     * cukup dengan sendirinya.
     *
     * Master kosong adalah jawaban SAH: [] dengan HTTP 200, bukan 404.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $this->allBusinessUnits()
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * SATU-SATUNYA TEMPAT SERVICE INI MEMBACA DAFTAR SELURUH MILL.
     *
     * Publik dan sengaja sederhana supaya dapat di-spy: aturan 'gagal tertutup'
     * untuk akun terikat tanpa business_unit_id hanya bermakna bila dapat
     * DIBUKTIKAN daftar seluruh mill tidak pernah dibangun, dan sebuah spy yang
     * mencatat nol panggilan ke metode ini adalah buktinya.
     *
     * @return Collection<int, BusinessUnit>
     */
    public function allBusinessUnits(): Collection
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Opsi Production Line — line DI DALAM mill yang berlaku.
     *
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions(): production
     * line BUKAN ikatan akun (tidak ada `users.production_line_id`, dan tidak
     * boleh ada) melainkan KONTEKS YANG DIPILIH. Supervisor pun memilih line,
     * karena satu mill di lapangan punya belasan production line dengan jenis
     * stasiun yang sama berulang di tiap line.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi — itu separuh pertama dari jaminan 'line mill
     * lain ditolak'; separuhnya lagi ada di resolveProductionLine().
     *
     * @return list<array{id: string, name: string}>
     */
    public function productionLineOptions(string $businessUnitId): array
    {
        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => [
                'id' => (string) $line->id,
                'name' => (string) $line->name,
            ])
            ->all();
    }

    /**
     * business_logic 1 — Production Line yang angka-angkanya dilaporkan.
     *
     * Tiga jawaban yang SENGAJA berbeda:
     *   - tidak dikirim  → 422 VALIDATION_ERROR pada production_line_id, dan
     *     NOL kueri cpo_dispatch_records dijalankan. TIDAK ADA fallback
     *     'line pertama' dan TIDAK ADA mode semua-line: angka laporan ini
     *     AGREGAT, dan menjumlahkan pengiriman dua line menghasilkan bilangan
     *     yang tidak dimiliki siapa pun. Ini kebocoran cakupan yang sudah tiga
     *     kali terjadi di proyek ini.
     *   - milik mill lain → 403 FORBIDDEN, nol kueri summary. Di sini DITOLAK,
     *     bukan diabaikan seperti business_unit_id, karena line adalah parameter
     *     yang pengguna PILIH secara sadar; mengembalikan null akan tidak
     *     terbedakan dari 'tidak dikirim' dan probe-nya justru mendapat ajakan
     *     memilih line.
     *   - milik mill yang berlaku → id-nya dipulangkan.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function resolveProductionLine(string $businessUnitId, ?string $requestedProductionLineId): string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        if (! $belongsToMill) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $requestedProductionLineId;
    }

    /**
     * Varian resolveProductionLine() yang MEMULANGKAN null alih-alih melempar,
     * untuk jalur RENDER layar web.
     *
     * Di layar, 'belum memilih line' adalah keadaan AWAL yang normal dan harus
     * merender ajakan memilih, bukan halaman galat; dan line mill lain yang
     * menyelip lewat query string harus jatuh ke keadaan yang SAMA, bukan
     * memamerkan bahwa line itu ada. Jalur API dan jalur EKSPOR tetap memakai
     * resolveProductionLine() yang melempar, sehingga satu permintaan yang
     * menyebut line mill lain tetap 403 dan satu berkas yang diunduh tetap
     * tidak pernah memuat line asing.
     */
    public function resolveProductionLineOrNull(string $businessUnitId, ?string $requestedProductionLineId): ?string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            return null;
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        return $belongsToMill ? $requestedProductionLineId : null;
    }

    /**
     * business_logic 1 — memuat periode dan membuktikan pemanggil boleh
     * membacanya.
     *
     * 404 ketika id-nya tidak ada. 403 ketika ia milik mill lain dan pemanggil
     * terikat mill. Admin lolos untuk mill mana pun.
     *
     * PENJAGA PERAN BERJALAN SEBELUM PENCARIAN, dengan sengaja dan terbukti
     * dari TIPE exception-nya: pemanggil yang ditolak sejak awal tidak boleh
     * dapat mengetahui apakah sebuah id periode ada dengan membandingkan 403
     * terhadap 404. Oracle LINTAS MILL ditutup dengan cara yang sama — periode
     * mill lain menjawab 403, tidak pernah 404.
     *
     * STATUS PERIODE TIDAK DIPERIKSA DI MANA PUN: laporan ini menerima draft,
     * open maupun closed. Kunci periode mengatur PENULISAN data, bukan
     * PEMBACAAN laporan.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function authorizePeriod(string $periodId): Period
    {
        $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        return $this->authorizePeriodModel($period);
    }

    // ------------------------------------------------------------------
    // Pembacaan
    // ------------------------------------------------------------------

    /**
     * Periode yang dapat dipilih untuk mill ini.
     *
     * Sebuah periode mencakup CPO Dispatch ketika ia PUNYA baris
     * `period_stations` untuk station_type 'cpo-dispatch'. Terbaru lebih
     * dulu. Array kosong adalah jawaban sah — mill yang belum punya periode
     * mendapat [] dengan HTTP 200 dan arahan ke Kelola Periode Pelaporan,
     * bukan 404.
     *
     * STATUS TIDAK PERNAH MENYARING DAFTAR INI: periode TERTUTUP tercantum
     * persis seperti periode terbuka.
     *
     * Mill diselesaikan di sini, bukan oleh pemanggil, supaya memanggil
     * listPeriods() sebagai Admin tanpa mill adalah 422 yang terdokumentasi,
     * dan memanggilnya sebagai peran terikat dengan id orang lain tetap membaca
     * mill pemanggil sendiri.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', self::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue() tidak
            // menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', self::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic 2-23 — SELURUH angka layar ini untuk satu periode pada
     * satu Production Line.
     *
     * Urutan penjaga disengaja: peran (403), mill (422), production line (422 /
     * 403), id periode (422), lalu periodenya sendiri (404 / 403). Pemanggil
     * yang tidak boleh berada di sini tidak pernah mengetahui id periode mana
     * yang ada.
     *
     * ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA, diterbitkan sebagai kunci
     * tersendiri:
     *   dispatch_count = net_weight_dispatch_count + missing_net_weight_dispatch_count;
     *   penjumlahan dispatch_count tiap dari TIGA rekap = dispatch_count;
     *   penjumlahan daily[].dispatch_count = dispatch_count;
     *   penjumlahan 24 kelompok hourly + hourly.not_recorded_count = dispatch_count;
     *   turnaround.measured_count + turnaround.uncomputable_count = dispatch_count.
     *
     * @param  Period|string|null  $period  model atau id
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        // 422 ketika absen, 403 ketika milik mill lain — pada KEDUA keadaan itu
        // nol kueri cpo_dispatch_records sudah dijalankan.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // SATU kueri untuk seluruh periode+line; detail DAN station di-eager-load
        // sekaligus. Semua di bawah ini PHP.
        $dispatches = $this->dispatchesFor($period, $productionLineId);

        $daily = $this->dailyOf($dispatches);

        $summary = [
            'business_unit' => $this->businessUnitInfo($businessUnitId),
            'production_line' => $this->productionLineInfo($productionLineId, (string) $period->business_unit_id),
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
            ],
            'dispatches' => $this->dispatchesBlockOf($dispatches),
            // EMPAT UKURAN, EMPAT PENYEBUT. Lihat qualityOf().
            'quality' => $this->qualityOf($dispatches),
            // BARU DI LAYAR INI. Dua perhitungan atas dua kolom berbeda: sebaran
            // jam membaca JAM MASUK saja; waktu putar membaca KEDUA jam. Keduanya
            // membaca menit-dalam-hari MENTAH dari objek pengiriman, bukan hasil
            // normalisasi satu sama lain.
            'turnaround' => $this->turnaroundOf($dispatches),
            'hourly' => $this->hourlyOf($dispatches),
            '_note_four_denominators' => self::NOTE_FOUR_DENOMINATORS,
        ];

        // TIGA REKAP TEKS BEBAS, bentuk identik, NOL join dan NOL pemetaan.
        foreach (self::TEXT_RECAPS as $key => $attribute) {
            $summary[$key] = $this->breakdownOf($dispatches, $attribute);
        }

        return $summary + [
            // JEJAK KEPATUHAN, blok tersendiri. KEDUA PENGHITUNG SEGEL TIDAK
            // PERNAH DIJUMLAHKAN menjadi satu angka.
            'compliance' => [
                'missing_waybill_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->waybill_number === null)
                    ->count(),
                'missing_seal_top_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->seal_no_top === null)
                    ->count(),
                'missing_seal_bottom_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->seal_no_bottom === null)
                    ->count(),
                '_note' => self::NOTE_COMPLIANCE,
            ],
            // Pengiriman draft IKUT di seluruh angka di atas, termasuk waktu putar
            // dan sebaran jam; ini pernyataannya, bukan penyaringnya.
            'draft_dispatch_count' => $dispatches
                ->filter(fn ($dispatch) => in_array($dispatch->status, self::DRAFT_STATUSES, true))
                ->count(),
            'daily' => $daily,
            'daily_total' => $this->dailyTotalOf($daily),
            'completeness' => $this->completenessOf($period, $daily),
            // SATU DAFTAR, BUKAN DUA: stasiun ini tidak punya kolom `remarks`.
            // Kunci `findings` TETAP diterbitkan meski kosong supaya layar dapat
            // mencetak pernyataan 'tidak ada' alih-alih menghilangkan bagiannya.
            'text_entries' => [
                'findings' => $this->textEntriesOf($dispatches, 'findings'),
            ],
            '_note_no_labeled_recap' => self::NOTE_NO_LABELED_RECAP,
            '_note_no_quality_thresholds' => self::NOTE_NO_QUALITY_THRESHOLDS,
            '_note_shift_not_published' => self::NOTE_SHIFT_NOT_PUBLISHED,
        ];
    }

    /**
     * Alias berkonvensi repo untuk buildSummary(). Satu implementasi, dua nama.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }


    /**
     * business_logic 24 — SATU BARIS EKSPOR PER PENGIRIMAN, dengan konteks
     * periode / rentang / mill / production line / tanggal record / status
     * diulang VERBATIM di setiap baris supaya berkasnya dapat langsung di-pivot
     * di spreadsheet.
     *
     * Kueri-nya SAMA dengan kueri summary(): rentang periode yang sama pada
     * kolom `date` INDUK, kolom line yang sama, dan ketiadaan penyaring status
     * yang sama.
     *
     * Pengiriman ber-netto NULL TETAP SEBUAH BARIS, dengan sel KOSONG — tidak
     * pernah dibuang dan tidak pernah ditulis 0. Membuangnya membuat berkas
     * tidak sepakat dengan dispatch_count di layar; menuliskannya 0
     * mengaku-akui pengukuran yang tidak pernah dilakukan siapa pun.
     *
     * PENJAGA DAN PEMERIKSAAN BATAS BARIS BERJALAN EAGER, pada saat pemanggilan,
     * sementara barisnya sendiri di-yield malas dari kueri berpotongan.
     * Menjadikan metode ini sendiri sebuah generator akan menunda 403/422
     * sampai iterasi pertama, sehingga ekspor yang ditolak akan terlihat sebagai
     * panggilan sukses yang tidak menghasilkan apa-apa.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function buildExportRows(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): Generator {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        // Dihitung atas BARIS YANG AKAN DITERBITKAN — baris detail, bukan
        // record induk: satu record membawa banyak pengiriman. COUNT(*) atas
        // kunci non-nullable bukan agregat atas kolom nullable, jadi ini bukan
        // jebakan SQLite/PostgreSQL yang dijaga aturan agregasi di docblock
        // kelas.
        $detailRowCount = CpoDispatchDetail::query()
            ->whereIn(
                'cpo_dispatch_record_id',
                (clone $recordQuery)->select('cpo_dispatch_records.id'),
            )
            ->count();

        // Lebih besar SECARA KETAT: tepat EXPORT_ROW_LIMIT baris masih
        // terekspor — batasnya inklusif, dan diuji pada kedua sisinya.
        // `static::` bukan `self::`: batas ini tunable, dan uji unit
        // menurunkannya di subclass untuk membuktikan pemeriksaan ini
        // menghitung BARIS YANG DITERBITKAN, bukan record.
        if ($detailRowCount > static::EXPORT_ROW_LIMIT) {
            throw ValidationException::withMessages([
                'period_id' => [
                    'Ekspor dibatasi '.number_format(static::EXPORT_ROW_LIMIT, 0, ',', '.')
                    .' baris pengiriman, sementara periode dan line ini menghasilkan '
                    .number_format($detailRowCount, 0, ',', '.')
                    .' baris. Persempit rentang periode lalu ekspor lagi.',
                ],
            ]);
        }

        // KEEMPAT NILAI KONTEKS PERIODE, dihitung SEKALI dan diulang verbatim
        // di setiap baris: nama periode, rentang tanggalnya, nama mill, nama
        // line. Rentang ikut karena nama periode adalah teks bebas yang tidak
        // dijamin memuat tanggalnya, dan sebuah berkas tanpa rentang tidak
        // dapat diperiksa ulang terhadap layarnya.
        $context = [
            (string) $period->name,
            $period->start_date->toDateString().' s/d '.$period->end_date->toDateString(),
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId, (string) $period->business_unit_id)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $context);
    }

    /**
     * business_logic 24 — berkas yang dialirkan di sekitar buildExportRows().
     *
     * SELURUH GATE BERJALAN EAGER sebelum satu byte pun dialirkan: peran,
     * resolusi mill, resolusi line, pemeriksaan format, dan batas 50.000 baris.
     * Galat yang terjadi SETELAH header terkirim mendarat sebagai berkas rusak,
     * bukan sebagai pesan.
     *
     * @throws ValidationException 422 VALIDATION_ERROR (format tidak dikenal / batas terlampaui)
     * @throws ExportFailedException 422 EXPORT_FAILED (gagal saat menulis)
     */
    public function export(
        Period|string|null $period = null,
        string $format = 'csv',
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): StreamedResponse {
        $this->guardAccess();

        // Daftar TERTUTUP {csv, xlsx}, dibandingkan SEBELUM writer dibuat.
        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau xlsx.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Menjalankan penjaga + resolusi mill + resolusi line + pemeriksaan
        // batas baris SEKARANG, sebelum satu byte respons pun dikunci.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId, $productionLineId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);
            $sheetFormat = $this->sheetFormatOf($format);

            return response()->streamDownload(function () use ($rows, $sheetFormat) {
                // Kegagalan SAAT menulis tetap EXPORT_FAILED (422), bukan
                // berkas setengah tertulis yang dilaporkan sebagai sukses.
                try {
                    $handle = SheetWriter::open($sheetFormat);
                    $handle->row(self::EXPORT_HEADER);

                    foreach ($rows as $row) {
                        $handle->row($row);
                    }

                    $handle->close();
                } catch (ExportFailedException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw new ExportFailedException;
                }
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ValidationException|ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException;
        }
    }

    // ------------------------------------------------------------------
    // Agregasi — SELURUHNYA DI PHP, nol di SQL
    // ------------------------------------------------------------------

    /**
     * SEAM UNTUK SERVICE-GANDA IN-MEMORY, dan satu-satunya tempat sumber baris
     * ditentukan. Protected dan sengaja sempit: subclass uji dapat memulangkan
     * koleksi objek pengiriman in-memory (misalnya dengan jam yang sulit disemai
     * lewat basis data) dan SELURUH agregasi di bawah berjalan apa adanya.
     *
     * @return Collection<int, object>
     */
    protected function dispatchesFor(Period $period, string $productionLineId): Collection
    {
        return $this->dispatchesOf($this->recordsFor($period, $productionLineId));
    }

    /**
     * Seluruh record induk periode+line dengan baris detailnya DAN stasiunnya
     * di-eager-load SEKALIGUS (jumlah kueri konstan, bukan satu per record).
     * Nama relasinya `cpoDispatchDetails` (bukan `details`), mengikuti
     * App\Models\CpoDispatchRecord. Detail diurutkan `event_date` lalu `id`:
     * PostgreSQL tidak menjanjikan urutan default, dan ekspor harus deterministik.
     *
     * @return Collection<int, CpoDispatchRecord>
     */
    protected function recordsFor(Period $period, string $productionLineId): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            ->with([
                'cpoDispatchDetails' => fn ($query) => $query
                    ->orderBy('event_date')
                    ->orderBy('id'),
                'station',
            ])
            ->orderBy('cpo_dispatch_records.date')
            ->orderBy('cpo_dispatch_records.id')
            ->get();
    }

    /**
     * Kueri dasar atas record induk periode ini.
     *
     *  - Join ke `stations` membatasi MILL dan JENIS STASIUN (record tidak
     *    membawa keduanya). 1:1, jadi satu line dengan dua unit stasiun CPO
     *    Dispatch tetap menghitung tiap record tepat sekali.
     *  - Line disaring pada kolom SNAPSHOT `cpo_dispatch_records.production_line_id`,
     *    bukan lewat `stations.production_line_id`: yang terakhir akan menulis
     *    ulang sejarah setiap kali stasiun dipindahkan.
     *  - Keanggotaan periode INKLUSIF pada `date` INDUK (bukan `event_date`
     *    detail, bukan created_at). whereDate(), bukan where(): suite SQLite,
     *    produksi PostgreSQL.
     *  - TIDAK ada penyaring status: baris draft adalah bagian dari setiap angka.
     */
    protected function recordQueryFor(Period $period, string $productionLineId): Builder
    {
        return CpoDispatchRecord::query()
            ->join('stations', 'stations.id', '=', 'cpo_dispatch_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', self::STATION_TYPE)
            ->where('cpo_dispatch_records.production_line_id', $productionLineId)
            ->whereDate('cpo_dispatch_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('cpo_dispatch_records.date', '<=', $period->end_date->toDateString())
            ->select('cpo_dispatch_records.*');
    }

    /**
     * SELURUH BARIS DETAIL dalam lingkup, di-flatten menjadi objek sederhana.
     * Inilah tempat SATUAN HITUNG ditetapkan: satu elemen = SATU PENGIRIMAN.
     *
     * Kolom teks di-TRIM dan string kosong disamakan dengan null SATU KALI di
     * sini; hanya spasi ujung yang dipangkas — perbedaan huruf besar/kecil tetap
     * dua nilai. Berat bersih dan keempat kolom mutu dibaca apa adanya, NULL
     * tetap NULL.
     *
     * JAM DIBACA SEBAGAI MENIT-DALAM-HARI MENTAH (`time_in_minute`,
     * `time_out_minute`, masing-masing ?int). Tidak ada penafsiran tengah malam
     * di sini: sebaran jam dan waktu putar sama-sama membaca nilai mentah ini,
     * dan itulah yang menjaga penafsiran waktu putar tidak menggeser kelompok jam.
     *
     * @param  Collection<int, CpoDispatchRecord>  $records
     * @return Collection<int, object>
     */
    protected function dispatchesOf(Collection $records): Collection
    {
        $dispatches = [];

        foreach ($records as $record) {
            $status = $this->recordStatusValue($record);

            foreach ($record->cpoDispatchDetails as $detail) {
                /** @var CpoDispatchDetail $detail */
                $quality = [];

                foreach (self::QUALITY_MEASURES as $measure) {
                    $raw = $detail->{$measure};
                    $quality[$measure] = $raw === null || $raw === '' ? null : (float) $raw;
                }

                $dispatches[] = (object) [
                    // `event_date` NOT NULL di skema, jadi selalu terisi.
                    'date' => $this->dateStringOf($detail->event_date),
                    'net_weight' => $detail->net_weight_mt === null || $detail->net_weight_mt === ''
                        ? null
                        : (float) $detail->net_weight_mt,
                    'quality' => $quality,
                    'time_in_minute' => $this->minuteOfDayOrNull($detail->time_in),
                    'time_out_minute' => $this->minuteOfDayOrNull($detail->time_out),
                    'transport_company' => $this->textOrNull($detail->transport_company),
                    // TEKS BEBAS tanpa FK. Nol join.
                    'storage_tank_source' => $this->textOrNull($detail->storage_tank_source),
                    'destination_buyer' => $this->textOrNull($detail->destination_buyer),
                    'waybill_number' => $this->textOrNull($detail->waybill_number),
                    // TANPA awalan `security_` (berbeda dari kernel_dispatch_details).
                    'seal_no_top' => $this->textOrNull($detail->seal_no_top),
                    'seal_no_bottom' => $this->textOrNull($detail->seal_no_bottom),
                    'findings' => $this->textOrNull($detail->findings),
                    // Status INDUK, dibawa turun supaya draft_dispatch_count
                    // dihitung atas satuan yang sama dengan dispatch_count.
                    'status' => $status,
                ];
            }
        }

        return collect($dispatches);
    }

    /**
     * Menit-dalam-hari dari nilai kolom `time`, atau null bila kosong / tak
     * terbaca. Pembacaan formatnya didelegasikan ke ShiftService::minuteOfDayOf()
     * — satu-satunya tempat format jam dibaca di proyek ini ('HH:MM', 'HH:MM:SS',
     * maupun datetime penuh, karena PostgreSQL dan SQLite memulangkan bentuk yang
     * berbeda). Nilai yang tak terbaca diperlakukan sebagai tidak tercatat, bukan
     * dibiarkan menjatuhkan seluruh laporan.
     */
    protected function minuteOfDayOrNull(mixed $value): ?int
    {
        $text = $this->textOrNull($value instanceof DateTimeInterface ? $value->format('H:i:s') : $value);

        if ($text === null) {
            return null;
        }

        try {
            return ShiftService::minuteOfDayOf($text);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Blok `dispatches` — angka pokok periode dengan PENYEBUTNYA DITERBITKAN.
     * dispatch_count menghitung SELURUH pengiriman, termasuk yang belum
     * ditimbang selesai dan yang milik record draft.
     *
     * @param  Collection<int, object>  $dispatches
     * @return array{dispatch_count: int, net_weight_total: float|null, net_weight_avg: float|null, net_weight_dispatch_count: int, missing_net_weight_dispatch_count: int}
     */
    protected function dispatchesBlockOf(Collection $dispatches): array
    {
        $dispatchCount = $dispatches->count();

        // PENYEBUT (b): baris yang net_weight_mt-nya NON-NULL. Null DIBUANG, tidak
        // pernah diganti nol.
        $weights = $dispatches
            ->map(fn ($dispatch) => $dispatch->net_weight)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        $netWeightDispatchCount = count($weights);

        return [
            'dispatch_count' => $dispatchCount,
            // null, BUKAN 0, ketika tak satu pengiriman pun tertimbang.
            'net_weight_total' => $netWeightDispatchCount === 0
                ? null
                : round(array_sum($weights), 2),
            // Penyebut diperiksa SEBELUM pembagian.
            'net_weight_avg' => $netWeightDispatchCount === 0
                ? null
                : round(array_sum($weights) / $netWeightDispatchCount, 2),
            'net_weight_dispatch_count' => $netWeightDispatchCount,
            'missing_net_weight_dispatch_count' => $dispatchCount - $netWeightDispatchCount,
        ];
    }

    /**
     * EMPAT UKURAN MUTU, EMPAT PENYEBUT. Setiap ukuran dihitung HANYA atas baris
     * yang kolom ITU-nya non-null. PENJAGANYA PER UKURAN: measured_count 0 pada
     * satu ukuran memberi null/null/null pada ukuran ITU saja — ketiga lainnya
     * tetap berangka. Nilai yang tidak masuk akal dilaporkan APA ADANYA.
     *
     * DOBI membawa `_note` di dalam bloknya sendiri: ia indeks tanpa satuan, dan
     * prosa itu tidak boleh terpisah dari angkanya.
     *
     * @param  Collection<int, object>  $dispatches
     * @return array<string, mixed>
     */
    protected function qualityOf(Collection $dispatches): array
    {
        $quality = [];

        foreach (self::QUALITY_MEASURES as $measure) {
            $quality[$measure] = $this->statsOf($this->measureValuesOf($dispatches, $measure));
        }

        $quality['dobi']['_note'] = self::NOTE_DOBI_UNITLESS;
        $quality['_note'] = self::NOTE_QUALITY_DENOMINATORS;

        return $quality;
    }

    /**
     * Nilai non-null SATU ukuran mutu — penyebutnya sendiri, tidak dipinjamkan.
     *
     * @param  Collection<int, object>  $dispatches
     * @return array<int, float>
     */
    protected function measureValuesOf(Collection $dispatches, string $measure): array
    {
        return $dispatches
            ->map(fn ($dispatch) => $dispatch->quality[$measure] ?? null)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    /**
     * min / avg / max / measured_count atas nilai SATU ukuran. Masukan kosong
     * adalah null/null/null/0, TIDAK PERNAH 0/0/0/0. Dihitung di PHP.
     *
     * @param  array<int, float>  $values
     * @return array{min: float|null, avg: float|null, max: float|null, measured_count: int}
     */
    protected function statsOf(array $values): array
    {
        if ($values === []) {
            return ['min' => null, 'avg' => null, 'max' => null, 'measured_count' => 0];
        }

        return [
            'min' => round(min($values), self::QUALITY_DECIMALS),
            'avg' => round(array_sum($values) / count($values), self::QUALITY_DECIMALS),
            'max' => round(max($values), self::QUALITY_DECIMALS),
            'measured_count' => count($values),
        ];
    }

    /**
     * WAKTU PUTAR TANKER — min / avg / max menit, dihitung di PHP.
     *
     * Hanya pengiriman yang KEDUA jamnya tercatat yang dihitung
     * (measured_count = PENYEBUT (d)); sisanya masuk uncomputable_count, dan
     * keduanya berjumlah dispatch_count.
     *
     * RUMUS: menit = out − in (menit-dalam-hari); bila NEGATIF tambah 1440.
     *   23:30 → 00:45 = 75 (bukan −1365), dan masuk crossed_midnight_count;
     *   09:00 → 09:00 = 0 (BUKAN 1440), tetap di measured_count, TIDAK di
     *   crossed_midnight_count — itu sebabnya perbandingannya `< 0`, bukan `<= 0`.
     *
     * Nol adalah angka, bukan ketiadaan: ia masuk penyebut dan memengaruhi min
     * serta rata-rata. Nilai tak wajar (23 jam) dilaporkan apa adanya, tanpa
     * penandaan.
     *
     * @param  Collection<int, object>  $dispatches
     * @return array<string, mixed>
     */
    protected function turnaroundOf(Collection $dispatches): array
    {
        $minutes = [];
        $crossedMidnight = 0;

        foreach ($dispatches as $dispatch) {
            if ($dispatch->time_in_minute === null || $dispatch->time_out_minute === null) {
                continue;
            }

            $diff = $dispatch->time_out_minute - $dispatch->time_in_minute;

            if ($diff < 0) {
                $diff += self::DAY_MINUTES;
                $crossedMidnight++;
            }

            $minutes[] = $diff;
        }

        $measured = count($minutes);

        return [
            'min_minutes' => $measured === 0 ? null : min($minutes),
            'avg_minutes' => $measured === 0
                ? null
                : round(array_sum($minutes) / $measured, self::TURNAROUND_DECIMALS),
            'max_minutes' => $measured === 0 ? null : max($minutes),
            'measured_count' => $measured,
            // DITERBITKAN TERSENDIRI, dan layar merendernya juga ketika nol.
            'crossed_midnight_count' => $crossedMidnight,
            'uncomputable_count' => $dispatches->count() - $measured,
            '_note' => self::NOTE_TURNAROUND,
        ];
    }

    /**
     * SEBARAN PENGIRIMAN MENURUT JAM MASUK — SELALU 24 kelompok (jam 0..23),
     * termasuk yang nol, ditambah kelompok 'jam tidak tercatat'.
     *
     * Kelompok ditentukan HANYA oleh `time_in_minute` (intdiv 60). Jam keluar
     * tidak pernah dipakai sebagai pengganti, dan penafsiran tengah malam pada
     * waktu putar tidak menyentuh blok ini: 23:30 tetap di kelompok 23.
     *
     * Penjumlahan 24 kelompok + not_recorded_count = dispatch_count.
     * busiest_hour NULL — bukan 0, yang terbaca 'tersibuk pukul 00' — ketika tak
     * ada jam tercatat; bila seri, jam yang lebih awal menang (deterministik).
     *
     * @param  Collection<int, object>  $dispatches
     * @return array<string, mixed>
     */
    protected function hourlyOf(Collection $dispatches): array
    {
        $counts = array_fill(0, 24, 0);
        $weights = array_fill(0, 24, null);
        $notRecorded = 0;

        foreach ($dispatches as $dispatch) {
            if ($dispatch->time_in_minute === null) {
                $notRecorded++;

                continue;
            }

            $hour = intdiv($dispatch->time_in_minute, 60) % 24;
            $counts[$hour]++;

            if ($dispatch->net_weight !== null) {
                $weights[$hour] = (float) ($weights[$hour] ?? 0.0) + $dispatch->net_weight;
            }
        }

        $buckets = [];
        $busiestHour = null;

        for ($hour = 0; $hour < 24; $hour++) {
            $buckets[] = [
                'hour' => $hour,
                'dispatch_count' => $counts[$hour],
                'net_weight_total' => $weights[$hour] === null ? null : round($weights[$hour], 2),
            ];

            // `>` (bukan `>=`): jam paling awal menang bila seri.
            if ($counts[$hour] > 0 && ($busiestHour === null || $counts[$hour] > $counts[$busiestHour])) {
                $busiestHour = $hour;
            }
        }

        return [
            'buckets' => $buckets,
            'not_recorded_count' => $notRecorded,
            // PENYEBUT (c).
            'recorded_count' => array_sum($counts),
            'busiest_hour' => $busiestHour,
            'empty_hour_count' => count(array_filter($counts, fn (int $count) => $count === 0)),
            '_note' => self::NOTE_HOURLY,
        ];
    }

    /**
     * Satu rekap kategoris teks bebas — bentuk yang sama untuk KETIGA rekap.
     *
     * PENGELOMPOKAN DI PHP atas nilai yang tersimpan apa adanya (nol GROUP BY,
     * nol LIKE/ILIKE). Nilai null MEMBENTUK SATU KELOMPOK TERSENDIRI dan tidak
     * pernah dibuang, sehingga penjumlahan dispatch_count tiap rekap TEPAT
     * dispatch_count; kuncinya null HARFIAH supaya layar menyebutnya 'tidak
     * tercatat' tanpa service mengarang label. Tidak pernah dipangkas dan tidak
     * ada kelompok 'lainnya'.
     *
     * Urutan: net_weight_total DESC → dispatch_count DESC → value ASC. null
     * diurutkan sebagai 0.0 UNTUK PENGURUTAN SAJA dan tetap null di payload;
     * null terakhir hanya di antara yang SERI pada kedua kunci pertama.
     *
     * @param  Collection<int, object>  $dispatches
     * @return list<array{value: string|null, dispatch_count: int, net_weight_total: float|null, net_weight_dispatch_count: int, net_weight_avg: float|null}>
     */
    protected function breakdownOf(Collection $dispatches, string $attribute): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($dispatches as $dispatch) {
            $raw = $dispatch->{$attribute};

            // Kunci ember yang tidak ambigu: null tidak pernah bertabrakan dengan
            // string "null" yang diketik operator.
            $bucket = $raw === null ? "\0null" : 'v:'.$raw;

            if (! isset($groups[$bucket])) {
                $groups[$bucket] = [
                    'value' => $raw,
                    'dispatch_count' => 0,
                    'net_weight_total' => null,
                    'net_weight_dispatch_count' => 0,
                ];
            }

            $groups[$bucket]['dispatch_count']++;

            if ($dispatch->net_weight !== null) {
                $groups[$bucket]['net_weight_total'] = (float) ($groups[$bucket]['net_weight_total'] ?? 0.0)
                    + $dispatch->net_weight;
                $groups[$bucket]['net_weight_dispatch_count']++;
            }
        }

        $rows = array_values($groups);

        usort($rows, function (array $a, array $b) {
            $byWeight = ((float) ($b['net_weight_total'] ?? 0.0)) <=> ((float) ($a['net_weight_total'] ?? 0.0));

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byDispatches = $b['dispatch_count'] <=> $a['dispatch_count'];

            if ($byDispatches !== 0) {
                return $byDispatches;
            }

            return ((string) ($a['value'] ?? "\u{FFFF}")) <=> ((string) ($b['value'] ?? "\u{FFFF}"));
        });

        return array_map(fn (array $row) => $this->withWeightFigures($row), $rows);
    }

    /**
     * Rekap harian — SATU ENTRI PER HARI YANG PUNYA PENGIRIMAN, bukan per hari
     * kalender (hari tanpa pengiriman adalah keadaan normal; baris nol akan
     * terbaca 'terukur nol'). Tanggal di luar rentang periode (induknya di
     * dalam) tetap mendapat entrinya supaya total cocok dengan rinciannya. Urut
     * tanggal NAIK lewat ksort(), tidak diwarisi dari kueri.
     *
     * @param  Collection<int, object>  $dispatches
     * @return list<array{date: string, dispatch_count: int, net_weight_total: float|null, net_weight_dispatch_count: int, net_weight_avg: float|null}>
     */
    protected function dailyOf(Collection $dispatches): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($dispatches as $dispatch) {
            if (! isset($rows[$dispatch->date])) {
                $rows[$dispatch->date] = [
                    'date' => $dispatch->date,
                    'dispatch_count' => 0,
                    'net_weight_total' => null,
                    'net_weight_dispatch_count' => 0,
                ];
            }

            $rows[$dispatch->date]['dispatch_count']++;

            if ($dispatch->net_weight !== null) {
                $rows[$dispatch->date]['net_weight_total'] = (float) ($rows[$dispatch->date]['net_weight_total'] ?? 0.0)
                    + $dispatch->net_weight;
                $rows[$dispatch->date]['net_weight_dispatch_count']++;
            }
        }

        ksort($rows);

        return array_map(fn (array $row) => $this->withWeightFigures($row), array_values($rows));
    }

    /**
     * Kaki rekap harian: DIJUMLAHKAN dari daily[], bukan disalin dari blok
     * `dispatches`. Karena `event_date` NOT NULL, hasilnya selalu sama dengan
     * blok itu — penjumlahan tetap dilakukan dari daily[] supaya kaki tabel
     * adalah jumlah baris di atasnya.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{dispatch_count: int, net_weight_total: float|null, net_weight_dispatch_count: int, net_weight_avg: float|null}
     */
    protected function dailyTotalOf(array $daily): array
    {
        $dispatchCount = 0;
        $netWeightDispatchCount = 0;
        $netWeightTotal = null;

        foreach ($daily as $row) {
            $dispatchCount += (int) $row['dispatch_count'];
            $netWeightDispatchCount += (int) $row['net_weight_dispatch_count'];

            if ($row['net_weight_total'] !== null) {
                $netWeightTotal = (float) ($netWeightTotal ?? 0.0) + (float) $row['net_weight_total'];
            }
        }

        return [
            'dispatch_count' => $dispatchCount,
            'net_weight_total' => $netWeightTotal === null ? null : round($netWeightTotal, 2),
            'net_weight_dispatch_count' => $netWeightDispatchCount,
            'net_weight_avg' => $netWeightDispatchCount === 0 || $netWeightTotal === null
                ? null
                : round($netWeightTotal / $netWeightDispatchCount, 2),
        ];
    }

    /**
     * Kelengkapan pencatatan — mengukur HARI. days_counted datang dari
     * ReportPeriodDays::counted() APA ADANYA (menangani periode berjalan dan
     * yang belum mulai). percent NULL — bukan 0.0 — ketika days_counted 0.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{days_in_period: int, days_with_dispatch: int, days_counted: int, percent: float|null}
     */
    protected function completenessOf(Period $period, array $daily): array
    {
        $daysCounted = ReportPeriodDays::counted($period);
        $daysWithDispatch = count($daily);

        return [
            'days_in_period' => ReportPeriodDays::inPeriod($period),
            'days_with_dispatch' => $daysWithDispatch,
            'days_counted' => $daysCounted,
            'percent' => $daysCounted === 0
                ? null
                : round(100 * $daysWithDispatch / $daysCounted, 1),
        ];
    }

    /**
     * Daftar kejadian bertanggal untuk kolom teks bebas `findings` — SATU
     * DAFTAR. Teks bebas tidak disebar menjadi rekap kategoris dan isi yang
     * berulang tidak dikelompokkan. Entri kosong/whitespace sudah menjadi null
     * di dispatchesOf() dan dibuang; daftar kosong tetap diterbitkan sebagai [].
     *
     * @param  Collection<int, object>  $dispatches
     * @return list<array{date: string, value: string}>
     */
    protected function textEntriesOf(Collection $dispatches, string $column): array
    {
        $entries = $dispatches
            ->filter(fn ($dispatch) => $dispatch->{$column} !== null)
            ->map(fn ($dispatch) => [
                'date' => $dispatch->date,
                'value' => (string) $dispatch->{$column},
            ])
            ->values()
            ->all();

        usort($entries, fn (array $a, array $b) => ((string) $a['date']) <=> ((string) $b['date']));

        return $entries;
    }

    // ------------------------------------------------------------------
    // Aliran ekspor
    // ------------------------------------------------------------------

    /**
     * Separuh malas buildExportRows(): satu array per PENGIRIMAN, ditarik
     * berpotongan (lazy) supaya periode panjang tidak pernah menjadi satu
     * koleksi. Keempat nilai konteks dihitung SEKALI oleh pemanggil dan diulang
     * VERBATIM, bersama tanggal dan status RECORD — enam kolom konteks.
     *
     * STATUS RECORD lewat ExportValue::status(). Stasiun ini tidak punya kolom
     * enum pengukuran, jadi tidak ada nilai lain yang diterjemahkan: seluruh
     * kolom detail dicetak apa adanya, JAM TERMASUK — 23:30 tetap 23:30, tanpa
     * penafsiran menyeberang tengah malam dan tanpa kolom waktu putar. Ekspor
     * adalah rincian MENTAH; penafsiran adalah pekerjaan laporan, dan
     * memasukkannya ke ekspor akan membuat dua sumber angka yang dapat menyimpang.
     * Sel NULL tetap KOSONG, bukan 0 dan bukan '-'.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $context
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $context): Generator
    {
        $query = (clone $recordQuery)
            ->with([
                'cpoDispatchDetails' => fn ($detailQuery) => $detailQuery
                    ->orderBy('event_date')
                    ->orderBy('id'),
            ])
            ->orderBy('cpo_dispatch_records.date')
            ->orderBy('cpo_dispatch_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var CpoDispatchRecord $record */
            $rowContext = array_merge($context, [
                optional($record->date)->toDateString(),
                ExportValue::status($this->recordStatusValue($record)),
            ]);

            foreach ($record->cpoDispatchDetails as $detail) {
                /** @var CpoDispatchDetail $detail */
                $values = [];

                // DUA PULUH SATU kolom, urutan DETAIL_EXPORT_FIELDS (dibaca dari
                // konstanta supaya daftarnya dapat diasersikan).
                foreach (self::DETAIL_EXPORT_FIELDS as $field) {
                    $value = $detail->{$field};

                    if ($field === 'event_date') {
                        $value = $value === null ? null : $this->dateStringOf($value);
                    }

                    $values[] = $value;
                }

                yield array_merge($rowContext, $values);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Penjaga sesi + peran yang dipakai setiap titik masuk.
     *
     * Penjaga ini duduk dua lapis lebih dalam daripada middleware rute dengan
     * sengaja: membersihkan middleware tidak boleh pernah cukup dengan
     * sendirinya. Karena itu melebarkan peran di sini dan di routes/api.php
     * selalu SATU perubahan, bukan dua.
     *
     * OPERATOR DITERIMA SEJAK COMMIT PERTAMA — screen-167 (laporan CPO Dispatch
     * mobile) dibangun dalam seri yang sama dan merupakan pemanggil
     * sahnya. Menerima peran DI SINI hanya sepertiga dari perubahannya:
     * resolveBusinessUnit() juga HARUS menempatkan Operator di cabang TERIKAT
     * MILL, atau baris ini membuka pembacaan lintas mill. Satu-satunya titik
     * masuk yang tetap menolak Operator adalah businessUnitOptions(), yang
     * memeriksa perannya sendiri PERSIS karena penjaga ini meloloskannya.
     *
     * @return string peran pemanggil
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function guardAccess(): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $role = $this->roleOf($user);

        if (! in_array($role, [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
            // screen-167 (mobile). Lihat resolveBusinessUnit(), yang WAJIB
            // menempatkan Operator di cabang terikat mill.
            UserRole::Operator->value,
        ], true)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $role;
    }

    /**
     * Pemeriksaan mill yang dipakai bersama authorizePeriod() dan setiap titik
     * masuk yang menerima Period yang sudah dimuat. Menjalankannya ulang atas
     * model yang baru saja diotorisasi tidak berbiaya apa pun dan menutup celah
     * ketika pemanggil menyerahkan Period yang tidak pernah ia periksa.
     *
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function authorizePeriodModel(Period $period): Period
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ((string) $period->business_unit_id !== (string) (auth()->user()->business_unit_id ?? '')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    /**
     * period_id yang absen adalah 422 VALIDATION_ERROR — masukan yang belum
     * lengkap, bukan 404 untuk string kosong dan bukan laporan kosong
     * diam-diam.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function requirePeriod(Period|string|null $period): Period
    {
        if ($period === null || $period === '') {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        if ($period instanceof Period) {
            $period = $period->relationLoaded('businessUnit') ? $period : $period->load('businessUnit');

            return $this->authorizePeriodModel($period);
        }

        return $this->authorizePeriod($period);
    }

    /**
     * Mill yang angka-angkanya dilaporkan — yang BERLAKU hasil
     * resolveBusinessUnit(), bukan yang diminta klien. Untuk peran terikat mill
     * itu selalu mill akunnya sendiri, dan itulah yang membuat probe dengan id
     * mill lain menjawab 200 berisi nama mill pemanggil sendiri.
     *
     * @return array{id: string, name: string}
     */
    protected function businessUnitInfo(string $businessUnitId): array
    {
        /** @var BusinessUnit|null $businessUnit */
        $businessUnit = BusinessUnit::query()->find($businessUnitId, ['id', 'name']);

        return [
            'id' => $businessUnitId,
            'name' => (string) ($businessUnit?->name ?? ''),
        ];
    }

    /**
     * Production line yang angka-angkanya dilaporkan, dengan NAMANYA. Angka
     * tidak berarti apa pun tanpa menyebut line mana yang menghasilkannya —
     * yang justru alasan memilihnya dijadikan wajib di layar ini.
     *
     * DIBATASI KE MILL-NYA, dan itu bukan hiasan: tanpa klausa business_unit_id
     * di bawah, memanggil /summary dengan production_line_id milik mill LAIN
     * menjawab 200 berisi id DAN NAMA MANUSIAWI line itu. Ditemukan 2026-10-07
     * saat menulis uji salah satu report service, dibuktikan lewat permintaan
     * HTTP sungguhan, dan diperbaiki IDENTIK di kesebelas report service
     * sekaligus karena bentuk tanpa scope itu ada identik di semuanya.
     *
     * @return array{id: string, name: string}
     */
    protected function productionLineInfo(string $productionLineId, string $businessUnitId): array
    {
        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->find($productionLineId, ['id', 'name']);

        return [
            'id' => $productionLineId,
            'name' => (string) ($line?->name ?? ''),
        ];
    }

    /**
     * Membulatkan total berat sebuah baris rekap dan menurunkan rata-ratanya
     * dari PENYEBUTNYA SENDIRI.
     *
     * Dipakai KEEMPAT rekap kategoris DAN daily[], supaya aturan 'rata-rata
     * selalu bersama penyebutnya' berlaku SAMA di tiap tingkat — bukan hanya di
     * blok `dispatches`. Konsekuensinya net_weight_avg sebuah baris adalah null
     * ketika baris itu tidak punya satu pun pengiriman bertimbang, sekalipun
     * baris lain punya.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function withWeightFigures(array $row): array
    {
        $count = (int) $row['net_weight_dispatch_count'];
        $total = $row['net_weight_total'];

        $row['net_weight_total'] = $total === null ? null : round((float) $total, 2);
        $row['net_weight_avg'] = $count === 0 || $total === null
            ? null
            : round((float) $total / $count, 2);

        return $row;
    }

    /**
     * Nilai teks yang sudah di-TRIM, atau null ketika kosong.
     *
     * String kosong dan whitespace diperlakukan SAMA DENGAN null, satu kali, di
     * satu tempat. Yang di-trim HANYA spasi ujung — besar-kecil huruf TIDAK
     * dinormalkan, karena menyatukan 'PT Pembeli' dengan 'pt pembeli' berarti
     * memutuskan atas nama orang bahwa keduanya pembeli yang sama.
     */
    protected function textOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya DATAR, persis seperti
     * ketujuh belas laporan stasiun sebelumnya, sehingga pemisahan
     * periods/period_stations tidak merembes ke kontrak API.
     *
     * @return array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}
     */
    protected function periodOption(Period $period): array
    {
        return [
            'id' => (string) $period->id,
            'name' => (string) $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $this->statusValue($period),
            'station_type' => self::STATION_TYPE,
            'station_type_label' => $this->stationTypeLabel(self::STATION_TYPE),
        ];
    }

    /**
     * Status yang dilaporkan layar ini adalah status BARIS period_stations untuk
     * jenis stasiun layar ini, bukan status periode: sejak 2026-09-25 periode
     * tidak punya status sendiri (Period::$status melempar LogicException),
     * karena stasiun tidak ditutup serentak.
     *
     * Tanpa baris untuk jenis ini jawabannya 'draft' — arti draft memang
     * 'stasiun ini belum dipakai di periode ini'. Sengaja bukan string kosong:
     * nilai status harus tetap salah satu dari draft/open/closed karena blade
     * mencocokkannya.
     *
     * Status TIDAK pernah membatasi pembacaan maupun ekspor di layar ini.
     */
    protected function statusValue(Period $period): string
    {
        $status = $this->stationRowOf($period)?->status;

        if ($status === null) {
            return PeriodStatus::Draft->value;
        }

        return is_object($status) ? $status->value : (string) $status;
    }

    /**
     * Baris period_stations untuk jenis stasiun layar ini. Memakai relasi yang
     * sudah di-eager-load bila ada — listPeriods() memuatnya terbatas pada
     * jenis ini — dan baru menembak kueri sendiri bila belum.
     */
    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', self::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', self::STATION_TYPE)
            ->first();
    }

    protected function recordStatusValue(CpoDispatchRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /**
     * Label dari tabel master `station_types`, bukan dari App\Enums\StationType
     * — jenis stasiun adalah DATA sejak 2026-09-22, jadi jenis yang ditambahkan
     * lewat INSERT harus merender nama sungguhannya tanpa perubahan kode.
     */
    protected function stationTypeLabel(string $code): string
    {
        if ($this->stationTypeNames === null) {
            $this->stationTypeNames = StationType::query()
                ->get(['code', 'name'])
                ->pluck('name', 'code')
                ->all();
        }

        return $this->stationTypeNames[$code] ?? $code;
    }

    protected function dateStringOf(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Menerjemahkan format layar ini ke format yang DIKENAL SheetWriter.
     *
     * SheetWriter hanya mengenal 'excel' untuk .xlsx — memanggil
     * SheetWriter::open('xlsx') akan diam-diam jatuh ke CSV, yaitu PERSIS cacat
     * yang SheetWriter dibuat untuk memperbaikinya. Kontrak API layar ini
     * memakai 'xlsx', jadi penerjemahannya terjadi DI SATU TEMPAT, di sini.
     */
    protected function sheetFormatOf(string $format): string
    {
        return $format === 'xlsx' ? SheetWriter::FORMAT_EXCEL : SheetWriter::FORMAT_CSV;
    }

    /**
     * Konvensi Content-Type/nama berkas yang sama dengan setiap ekspor lain di
     * basis kode ini. format=xlsx adalah .xlsx SUNGGUHAN lewat
     * App\Support\SheetWriter, bukan CSV yang diganti namanya.
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format, Period $period): array
    {
        $slug = str($period->name !== '' ? $period->name : 'periode')->slug()->value();
        $timestamp = now()->format('Ymd_His');

        if ($format === 'xlsx') {
            return [
                SheetWriter::XLSX_CONTENT_TYPE,
                "laporan-cpo-dispatch_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-cpo-dispatch_{$slug}_{$timestamp}.csv",
        ];
    }
}
