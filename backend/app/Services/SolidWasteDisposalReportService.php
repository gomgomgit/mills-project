<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
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
 * SolidWasteDisposalReportService — screen-162--laporan-solid-waste-disposal-web
 * / usecase-168 (Laporan Periode Solid Waste Disposal), dan pasangan mobile-nya
 * screen-163 yang memakai KETIGA rute data yang sama.
 *
 * Dipakai bersama oleh controller API (App\Http\Controllers\Api\
 * SolidWasteDisposalReportController) dan komponen Livewire (App\Livewire\
 * Dashboard\LaporanSolidWasteDisposal), pembagian yang sama dengan keenam belas
 * laporan stasiun sebelumnya — sehingga halaman web dan API tidak pernah dapat
 * menyebut angka yang berbeda.
 *
 * BACA SAJA SECARA KONSTRUKSI: setiap metode publik adalah SELECT, dan prefix
 * /api/solid-waste-disposal-reports tidak membawa satu pun POST/PUT/PATCH/DELETE.
 *
 * =====================================================================
 * BENTUKNYA PER-TRANSAKSI, BUKAN BERGRID — DAN ITU KEPUTUSAN ARSITEKTUR
 * =====================================================================
 * Lima laporan stasiun terakhir (threshing, pressing, engine-room,
 * process-water, …) memakai GRID 24 SLOT: tabel detailnya punya READING_FIELDS
 * dan satu baris per jam, sehingga slot kosong WAJIB dimunculkan.
 * `solid_waste_disposal_details` TIDAK punya READING_FIELDS: satu barisnya
 * adalah SATU MUATAN KELUAR — satu kendaraan limbah padat yang meninggalkan
 * pabrik — berkunci `event_date`, dan jumlah baris per hari sepenuhnya bebas.
 * Presedennya karena itu screen-143 (Weighbridge) dan screen-146 (Grading),
 * BUKAN screen-160. Menyalin dari screen-160 akan memaksa transaksi ke dalam 24
 * kotak yang tidak pernah ada.
 *
 * SEPULUH ATURAN YANG MENGIKUTI DARI BENTUK ITU, dan masing-masing gagal secara
 * SENYAP (angka yang masuk akal, bukan galat) bila ditulis salah:
 *
 *  1. SATUAN HITUNGNYA BARIS DETAIL, BUKAN RECORD. Satu record induk membawa
 *     banyak muatan. `loads.load_count` menghitung baris detail yang
 *     ter-flatten dari record-record dalam lingkup, bukan jumlah record.
 *
 *  2. HARI TANPA MUATAN TIDAK DIBUATKAN BARIS NOL di `daily[]`. Pada laporan
 *     bergrid, slot kosong adalah informasi (jam yang tidak dicatat). Di sini
 *     hari tanpa pengangkutan adalah keadaan NORMAL, dan 22 baris nol akan
 *     mengubur 9 baris yang berarti. Periode 31 hari dengan muatan pada 9 hari
 *     menghasilkan TEPAT 9 entri.
 *
 *  3. `net_weight_mt` DIBACA APA ADANYA DAN TIDAK PERNAH DIHITUNG ULANG dari
 *     bruto − tara. Turunannya terjadi di SolidWasteDisposalRecordService, dan
 *     seeder/fixture menulis lewat updateOrCreate yang melewatinya: di DB e2e
 *     hari ini ada baris ber-netto 5 MT sementara bruto DAN tara KEDUANYA null.
 *     Menghitung ulang akan mengubah 5 MT menjadi null pada data yang ada.
 *
 *  4. NULL BUKAN NOL, DAN NOL PENGUKURAN MENJADI NULL. `net_weight_total` dan
 *     `net_weight_avg` dihitung HANYA atas baris ber-netto non-null; ketika tak
 *     satu pun terisi keduanya NULL — bukan 0.0. Nol muatan bertimbang berarti
 *     'tidak diketahui', sedangkan 0,00 MT berarti 'tidak ada beban terangkut'.
 *     Diputuskan DI SINI, bukan di-rescue di blade.
 *
 *  5. SETIAP TINGKAT MENERBITKAN PENYEBUTNYA SENDIRI. `net_weight_load_count`
 *     ada di blok `loads`, di SETIAP baris keempat rekap kategoris, DAN di
 *     setiap baris `daily[]`. Rata-rata atas penyebut yang tak terlihat adalah
 *     angka yang tidak dapat diperiksa siapa pun, dan aturan itu berlaku di
 *     tiap tingkat — bukan hanya di tingkat teratas.
 *
 *  6. `missing_net_weight_load_count` DITONJOLKAN TERSENDIRI, bukan catatan
 *     kaki. Netto adalah kolom TURUNAN service, jadi null-nya tidak berarti
 *     'lupa diisi' melainkan PENIMBANGAN BELUM SELESAI — kendaraan sudah
 *     ditimbang kotor tetapi belum kembali ditimbang kosong, atau sebaliknya.
 *     Itu pekerjaan yang menggantung, bukan data yang kurang rapi.
 *
 *  7. KEEMPAT REKAP KATEGORIS: NOL JOIN, NOL PEMETAAN, NOL PENERJEMAHAN.
 *     `solid_waste_type`, `source_station`, `disposal_utilization_site` dan
 *     `purpose_end_use` adalah TEKS BEBAS pada tabel detail — bukan FK, bukan
 *     enum, tanpa tabel master. Menerjemahkannya berarti mengarang pemetaan
 *     yang tidak ada di mana pun, dan pemetaan yang salah lebih buruk daripada
 *     kode mentah yang jujur. Khususnya `purpose_end_use` TIDAK dilipat menjadi
 *     dua ember 'dibuang'/'dimanfaatkan': nilai nyatanya belum pernah terlihat
 *     di basis data mana pun, jadi menebak pemetaannya sekarang berarti menanam
 *     aturan yang terlihat benar dan diam-diam salah.
 *
 *  8. URUTAN TIAP REKAP ADALAH PRESEDEN KODE, BUKAN TAFSIR:
 *     net_weight_total DESC → load_count DESC → value ASC, persis seperti
 *     WeighbridgeReportService::groupedBreakdownOf() untuk by_origin/
 *     by_destination. Null diurutkan sebagai 0.0 UNTUK PENGURUTAN SAJA — di
 *     payload ia tetap null, sebab 'belum pernah ditimbang' bukan 'ditimbang
 *     nol' — dan karenanya baris 'tidak tercatat' yang bertonase besar MUNCUL
 *     DI ATAS, tidak terkubur di baris terakhir. Ia jatuh terakhir HANYA ketika
 *     seri dengan tetangganya pada kedua kunci pertama, tempat pemutus seri
 *     value ASC menempatkan null paling belakang.
 *
 *  9. BARIS DRAFT IKUT TERHITUNG di seluruh angka — nol penyaring status di
 *     seluruh kueri di bawah — dan `draft_load_count` menyatakan berapa
 *     banyak. Pembaca diberi tahu, bukan dilindungi. Sama dengan keenam belas
 *     laporan stasiun yang sudah ada; laporan yang berhitung dengan aturan
 *     berbeda antar stasiun lebih menyesatkan daripada laporan yang memasukkan
 *     data belum selesai secara terbuka.
 *
 * 10. KEANGGOTAAN PERIODE DITENTUKAN `date` PADA INDUK, bukan `event_date` pada
 *     detail. Keduanya dapat berbeda, dan yang menentukan keanggotaan adalah
 *     induknya — itulah yang dipakai pengguna saat menyimpan. Konsekuensinya
 *     `daily[]` DAPAT memuat tanggal di luar rentang periode, dan itu DIBIARKAN
 *     APA ADANYA: menyembunyikannya akan membuat total tidak cocok dengan
 *     rincian hariannya.
 *
 * ---------------------------------------------------------------------
 * TIDAK ADA BLOK hourly SAMA SEKALI, DAN SEBABNYA DITERBITKAN DI PAYLOAD
 * ---------------------------------------------------------------------
 * Baris detail stasiun ini hanya membawa `event_date` — sebuah TANGGAL. TIDAK
 * ADA satu pun kolom waktu padanya. Sebaran per jam karena itu BUKAN fitur yang
 * ditunda melainkan BUKAN SESUATU YANG DAPAT DIHITUNG. Payload membawa
 * `_note_no_hourly` dan layar mencetaknya UTUH, supaya ketiadaannya tidak
 * dibaca sebagai blok yang lupa dibuat — dan supaya tidak ada yang
 * menambahkannya kemudian dengan menebak jam dari `created_at`, yang berarti
 * menebak dari waktu INPUT, bukan waktu kejadian. Bandingkan screen-166/167
 * (cpo-dispatch): ia PUNYA time_in/time_out, jadi di sana sebaran jam justru
 * wajib ada.
 *
 * KOLOM `shift` TIDAK DITERBITKAN di payload maupun layar — hanya di ekspor.
 * Ia teks bebas pada tabel detail (bukan FK ke `shifts`), sehingga menyebarnya
 * berarti mengelompokkan EJAAN, bukan shift. Ini berbeda dan terpisah dari
 * kolom `shift_id` pada tabel INDUK; pengelompokan per shift yang sungguhan
 * adalah pekerjaan tersendiri yang berlaku untuk 18 stasiun sekaligus.
 *
 * TANPA BLOK downtime dan TANPA penandaan nilai di luar batas: tabelnya tidak
 * punya kolom downtime dan stasiun ini tidak punya tabel master target apa pun.
 * Ambang yang diturunkan dari data periode itu sendiri hanya turunan statistik
 * sementara pembaca akan membacanya sebagai batas resmi. Kedua ketiadaan itu
 * dinyatakan lewat `_note_absences`, sebab blok yang hilang tanpa penjelasan
 * selalu dibaca sebagai blok yang belum dibuat.
 *
 * ---------------------------------------------------------------------
 * compliance BUKAN 'KUALITAS DATA'
 * ---------------------------------------------------------------------
 * `missing_gate_pass_count` dan `missing_seal_count` adalah DOKUMEN ANGKUT YANG
 * TIDAK TERBIT — jejak kepatuhan, bukan ukuran produksi. Keduanya diterbitkan
 * HANYA sebagai penghitung kehadiran: tidak pernah dirata-rata, tidak pernah
 * diurutkan, tidak pernah digrafikkan (sebuah nomor segel tidak punya besaran —
 * segel 90021 bukan 'lebih besar' daripada 90018). Blok ini membawa prosanya
 * sendiri dan TIDAK digabung ke dalam blok `loads`.
 *
 * NAMA KOLOMNYA `gate_pass_no` dan `security_seal_no` — BUKAN
 * `gate_pass_number`/`seal_number` seperti yang tertulis di versi pertama spec
 * layar ini; dibaca dari migrasi 2026_08_31_000003, bukan ditebak dari nama
 * kunci payload. Kunci payload sengaja lebih pendek daripada nama kolomnya:
 * by_waste_type → `solid_waste_type`, by_source_station → `source_station`,
 * by_disposal_site → `disposal_utilization_site`, by_purpose_end_use →
 * `purpose_end_use`.
 *
 * ---------------------------------------------------------------------
 * CAKUPAN LINTAS MILL DITUTUP DI TIGA TITIK YANG BERBEDA, DENGAN SENGAJA
 * ---------------------------------------------------------------------
 *   1. resolveBusinessUnit() MENGABAIKAN business_unit_id kiriman klien untuk
 *      Operator / Supervisor / Mill Management — tidak divalidasi, tidak
 *      dibandingkan, dibuang. Menyebut id mill lain menjawab 200 berisi data
 *      MILL SENDIRI, sengaja BUKAN 403: sebuah 403 akan memastikan mill itu ada.
 *   2. resolveProductionLine() MENOLAK line milik mill lain dengan 403. Di sini
 *      ada pegangan nyata ke data mill lain, dan line adalah parameter yang
 *      pengguna PILIH secara sadar — bukan konteks yang diturunkan dari akunnya.
 *   3. authorizePeriod() MENOLAK periode milik mill lain dengan 403.
 *
 * Akun terikat mill yang `users.business_unit_id`-nya NULL GAGAL TERTUTUP dan
 * daftar seluruh mill tidak pernah dibaca — lihat allBusinessUnits(), yang
 * publik dan sengaja sederhana supaya sebuah spy dapat MEMBUKTIKAN ia tidak
 * pernah dipanggil. Jawabannya 422, bukan 403: ini master yang rusak, bukan
 * izin yang kurang, dan pesannya mengatakan demikian supaya yang menerimanya
 * tahu harus menghubungi Admin, bukan meminta hak akses.
 *
 * OPERATOR DITERIMA SEJAK COMMIT PERTAMA pada ketiga rute data — preseden
 * Grading / Kernel Plant / Engine Room / Process Water, bukan preseden
 * Weighbridge yang harus melebarkan akses sebagai tindak lanjut. Alasannya
 * screen-163 (laporan SWD mobile) dibangun dalam seri yang sama dan merupakan
 * pemanggil sahnya. ADMISINYA SATU PERUBAHAN DI TIGA TEMPAT, BUKAN TIGA
 * PERUBAHAN: daftar peran di routes/api.php, guardAccess() di bawah, DAN cabang
 * TERIKAT-MILL di resolveBusinessUnit(). Melebarkan dua yang pertama saja akan
 * menjatuhkan Operator ke cabang Admin, tempat business_unit_id kiriman klien
 * JUSTRU dihormati — itu kebocoran lintas mill, bukan cacat tampilan.
 *
 * YANG TETAP TERTUTUP: businessUnitOptions() menolak siapa pun selain Admin
 * dengan 403, ditegakkan DI DALAM service dan bukan oleh middleware — PERSIS
 * karena middleware prefix ini menerima keempat peran demi ketiga rute data,
 * sehingga penjaga di middleware tidak akan menutup rute itu sama sekali. Rute
 * WEB /reports/solid-waste-disposal juga TIDAK dilebarkan.
 *
 * ---------------------------------------------------------------------
 * SELURUH AGREGASI DI PHP, NOL DI SQL
 * ---------------------------------------------------------------------
 * Keputusan yang sama dengan keenam belas laporan sebelumnya, dengan alasan
 * yang menentukan: perilaku agregat SQL atas kolom NULLABLE TIDAK sama di
 * SQLite (suite uji) dan PostgreSQL (produksi/e2e), dan aturan
 * penyebut-tersendiri serta null-bukan-nol adalah justru yang hilang dalam
 * perbedaan itu. `ILIKE` — jangkauan yang paling menggoda untuk pengelompokan
 * tanpa peduli besar-kecil huruf — adalah SYNTAX ERROR di SQLite sementara
 * `LIKE` telanjang case-SENSITIVE di PostgreSQL, jadi ekspresi pengelompokan
 * di sisi SQL dapat meloloskan seluruh suite dan tetap salah di produksi. Tidak
 * satu pun ekspresi SQL mentah diterbitkan di bawah: kueri hanya memilih baris
 * dan menyaringnya dengan helper portabel Laravel (whereDate / whereNull), dan
 * urutan SELALU dinyatakan eksplisit karena PostgreSQL tidak menjanjikan
 * urutan default apa pun.
 */
class SolidWasteDisposalReportService
{
    /**
     * Batas baris ekspor, dihitung atas BARIS YANG AKAN DITERBITKAN — yaitu
     * baris DETAIL (satu muatan = satu baris berkas), bukan record induk. Satu
     * record induk dapat membawa puluhan muatan, jadi menghitung record akan
     * meloloskan berkas yang jauh lebih besar daripada batasnya.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang keberadaannya menentukan apakah sebuah periode mencakup stasiun ini.
     *
     * Diambil dari App\Enums\StationType, tidak ditulis sebagai literal: kunci
     * snake_case akan ter-compile, terlihat benar, dan tidak pernah cocok
     * dengan satu baris master pun.
     */
    protected const STATION_TYPE = StationTypeEnum::SolidWasteDisposal->value;

    /**
     * Format ekspor yang dikenal layar ini — DAFTAR TERTUTUP {csv, xlsx}.
     *
     * BERBEDA DARI KEENAM BELAS LAPORAN SEBELUMNYA, yang memakai {csv, excel}:
     * spec layar ini (endpoint /export, query param `format`) dan uji unitnya
     * menyebut 'xlsx' secara harfiah. Nilai 'xlsx' DITERJEMAHKAN ke
     * SheetWriter::FORMAT_EXCEL di sheetFormatOf() — SheetWriter hanya mengenal
     * 'excel', dan memanggilnya dengan 'xlsx' akan menghasilkan CSV berekstensi
     * .xlsx, yaitu persis cacat yang SheetWriter dibuat untuk memperbaikinya.
     * Komponen Livewire menerjemahkan 'excel' dari toolbar bersama menjadi
     * 'xlsx' sebelum sampai ke sini, sehingga daftar ini tetap tertutup.
     */
    public const SUPPORTED_FORMATS = ['csv', 'xlsx'];

    /** Status yang dihitung sebagai log sheet belum selesai (draft). */
    public const DRAFT_STATUSES = [
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ];

    /**
     * Berapa kolom pertama EXPORT_HEADER yang merupakan KONTEKS, diulang
     * VERBATIM di setiap baris: periode, mill, production line, tanggal record,
     * status record. Diterbitkan sebagai konstanta supaya bentuk ekspor
     * diturunkan, tidak dihitung dengan tangan.
     */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 5;

    /**
     * KETUJUH BELAS kolom detail yang masuk ekspor, dalam urutan berkas.
     *
     * Didaftar sebagai konstanta — bukan ditulis inline di streamExportRows() —
     * supaya daftar ini dapat DIASERSIKAN, bukan diingat. Tiga di antaranya
     * (`weighbridge_ticket_no`, `vehicle_no`, `driver_name`) tidak muncul di
     * SATU ANGKA LAYAR PUN, dan justru karena itu ketiganya yang paling mudah
     * tertinggal dari ekspor. `shift` juga ada di sini meski tidak ada di
     * layar: ekspor adalah rincian MENTAH, jadi pengecualian sebuah kolom dari
     * layar tidak berarti kolom itu dibuang dari berkas.
     *
     * @var array<int, string>
     */
    public const DETAIL_EXPORT_FIELDS = [
        'event_date',
        'shift',
        'weighbridge_ticket_no',
        'vehicle_no',
        'driver_name',
        'solid_waste_type',
        'source_station',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'disposal_utilization_site',
        'purpose_end_use',
        'gate_pass_no',
        'security_seal_no',
        'operator_id',
        'remarks',
        'findings',
    ];

    /**
     * Label kolom ekspor: 5 kolom konteks + ketujuh belas kolom detail = 22.
     *
     * LABELNYA MENGIKUTI LAYAR DETAIL stasiun ini
     * (resources/views/livewire/data/detail-solid-waste-disposal.blade.php),
     * VERBATIM — bukan nama kolom database. Konvensi proyek, sudah berlaku di
     * seluruh ekspor laporan: berkas yang tidak dapat menggantikan layar Detail
     * tidak layak diekspor.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal Record',
        'Status',
        'Tanggal Kejadian',
        'Shift',
        'Weighbridge Ticket No',
        'Vehicle No',
        'Driver Name',
        'Solid Waste Type',
        'Source Station',
        'Gross Weight (MT)',
        'Tare Weight (MT)',
        'Net Weight (MT)',
        'Disposal/Utilization Site',
        'Purpose/End Use',
        'Gate Pass No',
        'Security Seal No',
        'Operator ID',
        'Remarks',
        'Findings',
    ];

    /** Prosa `_note_no_hourly` — dirender UTUH di layar. */
    public const NOTE_NO_HOURLY = 'Sebaran muatan per jam TIDAK DAPAT dibuat untuk stasiun ini, dan '
        .'ketiadaannya dinyatakan di sini alih-alih disembunyikan. Baris detail Solid Waste Disposal '
        .'hanya membawa event_date — sebuah tanggal — dan tidak punya satu pun kolom waktu. Bukan kolom '
        .'waktu yang kosong, bukan kolom yang belum diisi operator: kolomnya tidak ada. Karena itu '
        .'pertanyaan tentang kapan dalam sehari limbah paling banyak keluar tidak dapat dihitung dari '
        .'data yang ada, dalam bentuk apa pun — tidak sebagai grafik 24 kolom, tidak sebagai kartu jam '
        .'tersibuk, tidak pula sebagai perkiraan. Ini BUKAN fitur yang ditunda. Satu-satunya sumbu waktu '
        .'yang tersedia di layar ini adalah tanggal, yaitu rekap harian. Menurunkannya dari created_at '
        .'berarti menebak dari waktu INPUT, bukan waktu kejadian, dan jawaban yang terlihat rinci padahal '
        .'tidak pernah diukur lebih buruk daripada tidak ada jawaban.';

    /** Prosa `_note_shift_not_published` — dirender UTUH di layar. */
    public const NOTE_SHIFT_NOT_PUBLISHED = 'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan '
        .'daftar nilai tertutup dan bukan relasi ke master. Menyebarkan teks bebas menghasilkan satu '
        .'nilai per ejaan yang berbeda, sehingga empat penulisan dari satu giliran kerja yang sama akan '
        .'terbaca sebagai empat kategori. Kolom itu karena itu TIDAK diterbitkan di payload maupun di '
        .'layar ini — bukan sebagai rekap, bukan sebagai kolom tabel, bukan pula sebagai tempat kosong. '
        .'Ia TETAP ikut di berkas ekspor pada setiap baris muatan, bersama nomor gate pass, nomor segel '
        .'dan id operator, karena ekspor adalah rincian mentah. Pengelompokan per giliran kerja yang '
        .'sungguhan datang dari kolom shift_id pada tabel INDUK dan berlaku untuk 18 stasiun sekaligus — '
        .'pekerjaan tersendiri, bukan sesuatu yang ditempelkan pada satu laporan.';

    /** Prosa `_note_absences` — dirender UTUH di layar. */
    public const NOTE_ABSENCES = 'Layar ini tidak memuat blok downtime, dan ketiadaannya disengaja: '
        .'stasiun Solid Waste Disposal tidak mencatat downtime sama sekali — tidak ada kolom durasi '
        .'berhenti, tidak ada kolom penyebab berhenti, dan tidak ada tabel downtime pada baris '
        .'detailnya. Blok kosong sebagai tempat yang menunggu diisi akan terbaca sebagai belum ada '
        .'datanya, padahal yang benar adalah stasiun ini memang tidak mengukurnya. Tidak ada pula satu '
        .'nilai pun di layar ini yang ditandai di luar batas: stasiun ini tidak punya tabel master '
        .'target, sehingga tidak ada satu baris acuan yang dapat dibandingkan dengan berat bersih sebuah '
        .'muatan, dengan rata-rata netto per muatan, maupun dengan jumlah muatan per hari. Menurunkan '
        .'ambang dari data periode ini sendiri akan menghasilkan garis yang terlihat berwibawa lalu '
        .'dibaca sebagai batas resmi padahal bukan, dan garis itu ikut bergeser setiap kali kelengkapan '
        .'pencatatan berubah. Seluruh angka karena itu ditampilkan apa adanya, tanpa warna peringatan '
        .'dan tanpa satu sel yang ditandai; penilaian layak atau tidak layak tetap di tangan pembacanya.';

    /** Prosa `compliance._note`. */
    public const NOTE_COMPLIANCE = 'Nomor gate pass dan nomor segel keamanan adalah JEJAK KEPATUHAN, '
        .'bukan ukuran produksi: keduanya menyatakan dokumen angkut yang tidak terbit. Karena itu '
        .'keduanya diterbitkan HANYA sebagai penghitung kehadiran — berapa muatan tanpa gate pass dan '
        .'berapa muatan tanpa segel — dan tidak pernah dirata-rata, tidak pernah diurutkan, dan tidak '
        .'pernah digrafikkan. Sebuah nomor segel tidak punya besaran: segel bernomor 90021 bukan lebih '
        .'besar daripada segel bernomor 90018, selisih keduanya tidak berarti apa pun, dan rata-rata '
        .'nomor segel adalah angka yang tidak mengukur apa-apa. Bila seluruh muatan periode lengkap, '
        .'kedua penghitung menunjukkan nol dan nol itu TETAP diterbitkan — nol itulah yang memberi tahu '
        .'pembaca bahwa pemeriksaannya memang berjalan. Nomor-nomornya sendiri tidak diterbitkan di '
        .'payload layar; ia hanya ada di ekspor.';

    /** Prosa `daily_total._note`. */
    public const NOTE_DAILY_TOTAL = 'Baris total ini DIJUMLAHKAN dari daily[], bukan disalin dari blok '
        .'loads. Keduanya memang dapat berbeda, tepatnya sebesar undated_load_count — muatan yang '
        .'event_date-nya kosong ikut di loads dan di keempat rekap kategoris tetapi tidak dapat '
        .'ditempatkan pada hari mana pun. Perbedaan itu adalah informasi; menyalin akan '
        .'menyembunyikannya, dan selisih yang tak dijelaskan tidak dapat dibedakan dari cacat.';

    /**
     * code => name dari tabel master `station_types`, dimemoisasi per instance
     * service (satu request / satu render Livewire).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    // ------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------

    /**
     * business_logic 1-2 — mill mana yang boleh dilihat pemanggil.
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
     * VALIDATION_ERROR pada field business_unit_id — bukan null diam-diam dan
     * bukan hasil kosong, yang akan terbaca sebagai 'mill ini tidak punya data'.
     * Tidak ada mill default dan tidak ada 'mill pertama'.
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
            // screen-163 (laporan SWD mobile). Operator WAJIB di sini: cabang
            // di bawah memperlakukan apa pun yang sampai padanya sebagai Admin.
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
     * business_logic 4 — Production Line yang angka-angkanya dilaporkan.
     *
     * Tiga jawaban yang SENGAJA berbeda:
     *   - tidak dikirim  → 422 VALIDATION_ERROR pada production_line_id, dan
     *     NOL kueri solid_waste_disposal_records dijalankan. TIDAK ADA fallback
     *     'line pertama' dan TIDAK ADA mode semua-line: angka laporan ini
     *     AGREGAT, dan menjumlahkan muatan dua line menghasilkan bilangan yang
     *     tidak dimiliki siapa pun. Ini kebocoran cakupan yang sudah tiga kali
     *     terjadi di proyek ini.
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
     * business_logic 3 — memuat periode dan membuktikan pemanggil boleh
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
     * Sebuah periode mencakup Solid Waste Disposal ketika ia PUNYA baris
     * `period_stations` untuk station_type 'solid-waste-disposal'. Terbaru lebih
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
     * business_logic 5-22 — SELURUH angka layar ini untuk satu periode pada
     * satu Production Line.
     *
     * Urutan penjaga disengaja: peran lebih dulu (403), lalu mill (422), lalu
     * production line (422 / 403), lalu id periode (422), lalu periodenya
     * sendiri (404 / 403). Pemanggil yang memang tidak boleh berada di sini
     * tidak pernah mengetahui id periode mana yang ada, dan permintaan yang
     * belum lengkap tidak pernah berubah menjadi 404 tentang periode yang ia
     * tidak pernah berhak menanyakannya.
     *
     * @param  Period|string|null  $period  model atau id (keduanya diterima
     *                                      supaya pemanggil yang sudah
     *                                      mengotorisasi periode tidak perlu
     *                                      membacanya ulang)
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
        // 422 ketika absen, 403 ketika milik mill lain — dan pada KEDUA
        // keadaan itu nol kueri solid_waste_disposal_records sudah dijalankan.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // SATU kueri untuk seluruh periode+line, detail DAN station
        // di-eager-load sekaligus. Semua di bawah ini PHP.
        $records = $this->recordsFor($period, $productionLineId);
        $loads = $this->loadsOf($records);

        $daily = $this->dailyOf($loads);

        return [
            'business_unit' => $this->businessUnitInfo($businessUnitId),
            'production_line' => $this->productionLineInfo($productionLineId, (string) $period->business_unit_id),
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
            ],
            'loads' => $this->loadsBlockOf($loads),
            // EMPAT REKAP, bentuk identik, NOL join dan NOL pemetaan. Nama
            // kunci payload sengaja lebih pendek daripada nama kolomnya;
            // pemetaannya dinyatakan di sini supaya tidak ditebak.
            'by_waste_type' => $this->breakdownOf($loads, 'waste_type'),
            'by_source_station' => $this->breakdownOf($loads, 'source_station'),
            'by_disposal_site' => $this->breakdownOf($loads, 'disposal_site'),
            'by_purpose_end_use' => $this->breakdownOf($loads, 'purpose_end_use'),
            // JEJAK KEPATUHAN, blok tersendiri — sengaja TIDAK di dalam
            // `loads`, karena ini bukan ukuran produksi.
            'compliance' => [
                'missing_gate_pass_count' => $loads
                    ->filter(fn ($load) => $load->gate_pass_no === null)
                    ->count(),
                'missing_seal_count' => $loads
                    ->filter(fn ($load) => $load->security_seal_no === null)
                    ->count(),
                '_note' => self::NOTE_COMPLIANCE,
            ],
            // Muatan draft IKUT di seluruh angka di atas; ini pernyataannya,
            // bukan penyaringnya.
            'draft_load_count' => $loads
                ->filter(fn ($load) => in_array($load->status, self::DRAFT_STATUSES, true))
                ->count(),
            // Muatan yang event_date-nya kosong: IKUT di load_count dan di
            // keempat rekap, TIDAK ikut di daily[]. Satu-satunya sebab
            // penjumlahan daily[] boleh lebih kecil daripada load_count, dan
            // karena itu ia diterbitkan dan dicetak berdampingan dengan
            // totalnya.
            'undated_load_count' => $loads
                ->filter(fn ($load) => $load->date === null)
                ->count(),
            'daily' => $daily,
            'daily_total' => $this->dailyTotalOf($daily),
            'completeness' => $this->completenessOf($period, $daily),
            // DUA DAFTAR KEJADIAN BERTANGGAL, nol pelabelan kategoris atas
            // prosa. Satu muatan yang mengisi keduanya muncul di kedua daftar,
            // dan itu benar — bukan penghitungan ganda.
            'text_entries' => [
                'findings' => $this->textEntriesOf($loads, 'findings'),
                'remarks' => $this->textEntriesOf($loads, 'remarks'),
            ],
            '_note_no_hourly' => self::NOTE_NO_HOURLY,
            '_note_shift_not_published' => self::NOTE_SHIFT_NOT_PUBLISHED,
            '_note_absences' => self::NOTE_ABSENCES,
        ];
    }

    /**
     * Alias berkonvensi repo untuk buildSummary(), supaya service ini terbaca
     * sama dengan keenam belas saudaranya di titik pemanggilan. Satu
     * implementasi, dua nama — tidak pernah dua implementasi.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic 23 — SATU BARIS EKSPOR PER MUATAN, dengan konteks periode
     * / mill / production line / tanggal record / status diulang VERBATIM di
     * setiap baris supaya berkasnya dapat langsung di-pivot di spreadsheet.
     *
     * Kueri-nya SAMA dengan kueri summary(): rentang periode yang sama pada
     * kolom `date` INDUK, kolom line yang sama, dan ketiadaan penyaring status
     * yang sama.
     *
     * Muatan ber-netto NULL TETAP SEBUAH BARIS, dengan sel KOSONG — tidak
     * pernah dibuang dan tidak pernah ditulis 0. Membuangnya membuat berkas
     * tidak sepakat dengan load_count di layar; menuliskannya 0 mengaku-akui
     * pengukuran yang tidak pernah dilakukan siapa pun.
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
        // record induk: satu record membawa banyak muatan. COUNT(*) atas kunci
        // non-nullable bukan agregat atas kolom nullable, jadi ini bukan jebakan
        // SQLite/PostgreSQL yang dijaga aturan agregasi di docblock kelas.
        $detailRowCount = SolidWasteDisposalDetail::query()
            ->whereIn(
                'solid_waste_disposal_record_id',
                (clone $recordQuery)->select('solid_waste_disposal_records.id'),
            )
            ->count();

        // Lebih besar SECARA KETAT: tepat EXPORT_ROW_LIMIT baris masih
        // terekspor — batasnya inklusif, dan diuji pada kedua sisinya.
        // `static::` bukan `self::`: batas ini tunable, dan uji unit
        // menurunkannya di subclass untuk membuktikan pemeriksaan ini
        // menghitung BARIS YANG DITERBITKAN, bukan record.
        if ($detailRowCount > static::EXPORT_ROW_LIMIT) {
            // VALIDATION_ERROR, bukan EXPORT_FAILED — penyimpangan SADAR dari
            // keenam belas laporan saudaranya, karena spec layar ini (endpoint
            // /export) dan uji unitnya menuntut 422 VALIDATION_ERROR dengan
            // pesan yang MENYEBUT angka batasnya dan menyarankan mempersempit
            // periode. Keduanya 422; yang berbeda hanya kode mesinnya.
            throw ValidationException::withMessages([
                'period_id' => [
                    'Ekspor dibatasi '.number_format(static::EXPORT_ROW_LIMIT, 0, ',', '.')
                    .' baris muatan, sementara periode dan line ini menghasilkan '
                    .number_format($detailRowCount, 0, ',', '.')
                    .' baris. Persempit rentang periode lalu ekspor lagi.',
                ],
            ]);
        }

        $context = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId, (string) $period->business_unit_id)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $context);
    }

    /**
     * business_logic 23 — berkas yang dialirkan di sekitar buildExportRows().
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
     * Seluruh record induk periode+line, dengan baris detailnya DAN stasiunnya
     * di-eager-load SEKALIGUS.
     *
     * EAGER LOAD ADALAH BAGIAN DARI KEBENARAN LAYAR INI, bukan optimasi yang
     * boleh ditunda: tanpa `with(['solidWasteDisposalDetails', 'station'])`,
     * satu periode 31 hari menghasilkan satu kueri per record untuk detailnya —
     * pola N+1 yang sudah pernah lolos ke produksi pada laporan sebelumnya.
     * Nama relasinya `solidWasteDisposalDetails` (bukan `details`), mengikuti
     * App\Models\SolidWasteDisposalRecord.
     *
     * Detail diurutkan `event_date` lalu `id`: PostgreSQL tidak menjanjikan
     * urutan default apa pun, dan ekspor harus deterministik.
     *
     * @return Collection<int, SolidWasteDisposalRecord>
     */
    protected function recordsFor(Period $period, string $productionLineId): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            ->with([
                'solidWasteDisposalDetails' => fn ($query) => $query
                    ->orderBy('event_date')
                    ->orderBy('id'),
                'station',
            ])
            ->orderBy('solid_waste_disposal_records.date')
            ->orderBy('solid_waste_disposal_records.id')
            ->get();
    }

    /**
     * Kueri dasar atas record induk periode ini.
     *
     * EMPAT HAL YANG DILAKUKAN KUERI INI:
     *
     *  - Join ke `stations` untuk MEMBATASI MILL dan JENIS STASIUN.
     *    `solid_waste_disposal_records` tidak membawa business_unit_id maupun
     *    station_type, jadi join inilah satu-satunya cara menyatakan keduanya.
     *    Join-nya 1:1 (station_id adalah FK tunggal), sehingga satu line dengan
     *    DUA unit stasiun SWD tetap menghitung tiap record tepat sekali — bukan
     *    nol dan bukan dua kali.
     *  - Menyaring per line di `solid_waste_disposal_records.production_line_id`,
     *    kolom SNAPSHOT milik record itu sendiri (migrasi 2026_09_28_000041,
     *    NOT NULL sejak ...000043) — BUKAN lewat `stations.production_line_id`.
     *    Membaca dari kolom record itulah yang benar secara semantik: untuk
     *    record lama yang stasiunnya sudah DIPINDAH ke line lain, kolom record
     *    menunjuk line tempat data itu benar-benar dihasilkan. Menyaring lewat
     *    `stations` akan MENULIS ULANG SEJARAH setiap kali sebuah stasiun
     *    dipindahkan: angka bulan lalu berubah diam-diam.
     *  - Membatasi keanggotaan INKLUSIF pada `solid_waste_disposal_records.date`
     *    — kolom INDUK, bukan `event_date` pada detail. whereDate(), bukan
     *    where(): suite berjalan di SQLite sementara produksi dan e2e di
     *    PostgreSQL, dan Laravel merender whereDate() per driver.
     *  - TIDAK menerapkan penyaring status apa pun: baris draft adalah bagian
     *    dari setiap angka.
     *
     * Bukan pernah `created_at` dan bukan pernah waktu sinkronisasi — baris yang
     * dimasukkan terlambat tetap milik periode tempat ia terjadi.
     */
    protected function recordQueryFor(Period $period, string $productionLineId): Builder
    {
        return SolidWasteDisposalRecord::query()
            ->join('stations', 'stations.id', '=', 'solid_waste_disposal_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', self::STATION_TYPE)
            ->where('solid_waste_disposal_records.production_line_id', $productionLineId)
            ->whereDate('solid_waste_disposal_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('solid_waste_disposal_records.date', '<=', $period->end_date->toDateString())
            ->select('solid_waste_disposal_records.*');
    }

    /**
     * SELURUH BARIS DETAIL dalam lingkup, di-flatten menjadi objek sederhana
     * yang membawa tepat apa yang dibutuhkan agregasi.
     *
     * Inilah tempat SATUAN HITUNG ditetapkan: satu elemen = satu MUATAN KELUAR,
     * bukan satu record induk.
     *
     * Empat kolom kategoris di-TRIM lalu string kosong diperlakukan SAMA DENGAN
     * null di sini, satu kali, supaya tidak ada agregasi di bawah yang perlu
     * mengulanginya — dua baris terpisah untuk '' dan null akan membagi satu
     * keadaan menjadi dua. Yang di-trim HANYA spasi ujung: nilai yang berbeda
     * pada besar-kecil huruf TETAP dua nilai berbeda, karena kolom teks bebas
     * tidak menjamin keduanya hal yang sama, dan gejala data master yang perlu
     * dirapikan harus TERLIHAT.
     *
     * `net_weight` DIBACA APA ADANYA dari kolomnya dan NULL TETAP NULL — tidak
     * pernah dipaksa menjadi 0.0, dan tidak pernah dihitung ulang dari bruto −
     * tara.
     *
     * @param  Collection<int, SolidWasteDisposalRecord>  $records
     * @return Collection<int, object>
     */
    protected function loadsOf(Collection $records): Collection
    {
        $loads = [];

        foreach ($records as $record) {
            $status = $this->recordStatusValue($record);

            foreach ($record->solidWasteDisposalDetails as $detail) {
                /** @var SolidWasteDisposalDetail $detail */
                $loads[] = (object) [
                    // Tanggal KEJADIAN muatan. null berarti muatan itu tidak
                    // dapat ditempatkan pada hari mana pun — ia tetap muatan,
                    // dan ia dihitung di undated_load_count.
                    'date' => $detail->event_date === null
                        ? null
                        : $this->dateStringOf($detail->event_date),
                    'net_weight' => $detail->net_weight_mt === null
                        ? null
                        : (float) $detail->net_weight_mt,
                    'waste_type' => $this->textOrNull($detail->solid_waste_type),
                    'source_station' => $this->textOrNull($detail->source_station),
                    'disposal_site' => $this->textOrNull($detail->disposal_utilization_site),
                    'purpose_end_use' => $this->textOrNull($detail->purpose_end_use),
                    'gate_pass_no' => $this->textOrNull($detail->gate_pass_no),
                    'security_seal_no' => $this->textOrNull($detail->security_seal_no),
                    'findings' => $this->textOrNull($detail->findings),
                    'remarks' => $this->textOrNull($detail->remarks),
                    // Status INDUK, dibawa turun ke tiap muatan supaya
                    // draft_load_count dapat dihitung atas satuan yang sama
                    // dengan load_count.
                    'status' => $status,
                ];
            }
        }

        return collect($loads);
    }

    /**
     * Blok `loads` — angka pokok periode, dengan PENYEBUTNYA DITERBITKAN.
     *
     * load_count menghitung SELURUH muatan, termasuk yang netto-nya kosong dan
     * termasuk milik record draft: 'berapa muatan keluar pada periode ini', dan
     * muatan yang penimbangannya belum selesai tetap muatan.
     *
     * @param  Collection<int, object>  $loads
     * @return array{load_count: int, net_weight_total: float|null, net_weight_avg: float|null, net_weight_load_count: int, missing_net_weight_load_count: int}
     */
    protected function loadsBlockOf(Collection $loads): array
    {
        $loadCount = $loads->count();

        // PENYEBUT ANGKA BERAT, dan satu-satunya tempat ia ditetapkan: baris
        // yang net_weight_mt-nya NON-NULL. Null DIBUANG, tidak pernah diganti
        // nol.
        $weights = $loads
            ->map(fn ($load) => $load->net_weight)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        $netWeightLoadCount = count($weights);

        return [
            'load_count' => $loadCount,
            // null, BUKAN 0, ketika tak satu muatan pun tertimbang: nol akan
            // mengaku-akui total terukur yang sebenarnya tidak ada.
            'net_weight_total' => $netWeightLoadCount === 0
                ? null
                : round(array_sum($weights), 2),
            // Pembagian berpenjaga: penyebutnya diperiksa SEBELUM pembagian,
            // bukan di-rescue sesudahnya — tidak pernah DivisionByZeroError,
            // dan tidak pernah 0.
            'net_weight_avg' => $netWeightLoadCount === 0
                ? null
                : round(array_sum($weights) / $netWeightLoadCount, 2),
            // PENYEBUTNYA, diterbitkan berdampingan dengan rata-rata yang
            // memilikinya.
            'net_weight_load_count' => $netWeightLoadCount,
            // ANGKA TERSENDIRI, bukan turunan yang dibiarkan dihitung pembaca:
            // artinya PENIMBANGAN BELUM SELESAI, bukan 'lupa diisi'.
            'missing_net_weight_load_count' => $loadCount - $netWeightLoadCount,
        ];
    }

    /**
     * Satu rekap kategoris — bentuk yang sama untuk keempat kolom.
     *
     * PENGELOMPOKAN TERJADI DI PHP ATAS NILAI YANG TERSIMPAN APA ADANYA. Tanpa
     * GROUP BY, tanpa LIKE, dan tegas tanpa ILIKE — ILIKE adalah SYNTAX ERROR
     * di SQLite sementara LIKE telanjang case-SENSITIVE di PostgreSQL, sehingga
     * ekspresi pengelompokan di sisi SQL dapat meloloskan seluruh suite uji dan
     * tetap menggabungkan (atau gagal menggabungkan) baris yang salah di
     * produksi.
     *
     * Nilai null MEMBENTUK SATU KELOMPOK TERSENDIRI dan TIDAK PERNAH DIBUANG:
     * mempertahankannya itulah yang membuat penjumlahan load_count tiap rekap
     * TEPAT sama dengan loads.load_count — membuangnya meninggalkan pembaca
     * dengan selisih yang tidak dapat ia jelaskan. Kuncinya null HARFIAH, bukan
     * string kosong dan bukan label karangan, supaya layar dapat menyebutnya
     * 'tidak tercatat' tanpa service mengarang nilai yang tidak pernah dicatat.
     *
     * TIDAK PERNAH DIPANGKAS, dan TIDAK ADA kelompok 'lainnya': nilai yang
     * muncul SEKALI tetap mendapat barisnya sendiri. Memangkas daftar membuat
     * penyumbang terkecil lenyap dari laporan, dan load_count-nya lalu berhenti
     * menjumlah ke totalnya. Bila kelak kolom ini berisi ratusan nilai berbeda
     * karena diketik bebas, rekapnya menjadi daftar panjang berjumlah satu-satu
     * — gejala itu sengaja dibiarkan TERLIHAT, karena ia tanda kolom tersebut
     * perlu data master.
     *
     * @param  Collection<int, object>  $loads
     * @return list<array{value: string|null, load_count: int, net_weight_total: float|null, net_weight_load_count: int, net_weight_avg: float|null}>
     */
    protected function breakdownOf(Collection $loads, string $attribute): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];

        foreach ($loads as $load) {
            $raw = $load->{$attribute};

            // Kunci ember yang sengaja tidak ambigu: null tidak akan pernah
            // bertabrakan dengan string "null" yang diketik operator.
            $bucket = $raw === null ? "\0null" : 'v:'.$raw;

            if (! isset($groups[$bucket])) {
                $groups[$bucket] = [
                    'value' => $raw,
                    'load_count' => 0,
                    'net_weight_total' => null,
                    'net_weight_load_count' => 0,
                ];
            }

            $groups[$bucket]['load_count']++;

            if ($load->net_weight !== null) {
                $groups[$bucket]['net_weight_total'] = (float) ($groups[$bucket]['net_weight_total'] ?? 0.0)
                    + $load->net_weight;
                $groups[$bucket]['net_weight_load_count']++;
            }
        }

        $rows = array_values($groups);

        usort($rows, function (array $a, array $b) {
            // Total null diurutkan sebagai 0.0 UNTUK PENGURUTAN SAJA — di
            // payload ia tetap null, sebab 'belum pernah ditimbang' bukan
            // 'ditimbang nol'.
            $byWeight = ((float) ($b['net_weight_total'] ?? 0.0)) <=> ((float) ($a['net_weight_total'] ?? 0.0));

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byLoads = $b['load_count'] <=> $a['load_count'];

            if ($byLoads !== 0) {
                return $byLoads;
            }

            // Pemutus seri terakhir yang deterministik. null jatuh paling
            // belakang HANYA di antara yang seri pada kedua kunci di atas —
            // bukan terakhir tanpa syarat.
            return ((string) ($a['value'] ?? "\u{FFFF}")) <=> ((string) ($b['value'] ?? "\u{FFFF}"));
        });

        return array_map(fn (array $row) => $this->withWeightFigures($row), $rows);
    }

    /**
     * Rekap harian — SATU ENTRI PER HARI YANG PUNYA MUATAN, bukan satu entri
     * per hari kalender.
     *
     * Hari tanpa muatan sengaja TIDAK dibuatkan baris nol: laporan ini
     * per-transaksi, dan hari tanpa pengangkutan adalah keadaan normal —
     * mencetak 22 baris nol akan mengubur sembilan baris yang berarti. (Berbeda
     * dari laporan bergrid, yang justru WAJIB memunculkan slot kosongnya.)
     *
     * Baris ber-event_date NULL TIDAK MASUK ke sini sama sekali — ia tidak dapat
     * ditempatkan pada hari mana pun — sehingga penjumlahan load_count daftar
     * ini dapat KURANG dari loads.load_count, tepat sebesar undated_load_count.
     *
     * Tanggal di LUAR rentang periode (induknya di dalam) DIBIARKAN APA ADANYA
     * dan tetap mendapat entrinya: menyembunyikannya akan membuat total tidak
     * cocok dengan rincian hariannya.
     *
     * Urut tanggal NAIK, dinyatakan dengan ksort() — bukan diwarisi dari urutan
     * kueri, yang tidak dijanjikan PostgreSQL.
     *
     * @param  Collection<int, object>  $loads
     * @return list<array{date: string, load_count: int, net_weight_total: float|null, net_weight_load_count: int, net_weight_avg: float|null}>
     */
    protected function dailyOf(Collection $loads): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($loads as $load) {
            if ($load->date === null) {
                continue;
            }

            if (! isset($rows[$load->date])) {
                $rows[$load->date] = [
                    'date' => $load->date,
                    'load_count' => 0,
                    'net_weight_total' => null,
                    'net_weight_load_count' => 0,
                ];
            }

            $rows[$load->date]['load_count']++;

            if ($load->net_weight !== null) {
                $rows[$load->date]['net_weight_total'] = (float) ($rows[$load->date]['net_weight_total'] ?? 0.0)
                    + $load->net_weight;
                $rows[$load->date]['net_weight_load_count']++;
            }
        }

        ksort($rows);

        return array_map(fn (array $row) => $this->withWeightFigures($row), array_values($rows));
    }

    /**
     * Kaki tabel rekap harian: DIJUMLAHKAN dari daily[], BUKAN disalin dari
     * blok `loads`.
     *
     * Keduanya memang dapat berbeda — tepatnya sebesar baris undated — dan
     * perbedaan itu adalah INFORMASI. Menyalin akan menyembunyikannya, dan
     * pembaca yang melihat penjumlahan harian lebih kecil daripada totalnya
     * akan selalu mencurigainya sebagai bug; kecurigaan itu wajar, karena
     * selisih tanpa penjelasan tidak dapat dibedakan dari cacat.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{load_count: int, net_weight_total: float|null, net_weight_load_count: int, net_weight_avg: float|null, _note: string}
     */
    protected function dailyTotalOf(array $daily): array
    {
        $loadCount = 0;
        $netWeightLoadCount = 0;
        $netWeightTotal = null;

        foreach ($daily as $row) {
            $loadCount += (int) $row['load_count'];
            $netWeightLoadCount += (int) $row['net_weight_load_count'];

            if ($row['net_weight_total'] !== null) {
                $netWeightTotal = (float) ($netWeightTotal ?? 0.0) + (float) $row['net_weight_total'];
            }
        }

        return [
            'load_count' => $loadCount,
            'net_weight_total' => $netWeightTotal === null ? null : round($netWeightTotal, 2),
            'net_weight_load_count' => $netWeightLoadCount,
            'net_weight_avg' => $netWeightLoadCount === 0 || $netWeightTotal === null
                ? null
                : round($netWeightTotal / $netWeightLoadCount, 2),
            '_note' => self::NOTE_DAILY_TOTAL,
        ];
    }

    /**
     * Kelengkapan pencatatan — mengukur HARI, bukan kolom.
     *
     * days_counted datang dari App\Support\ReportPeriodDays::counted() APA
     * ADANYA, sama persis dengan keenam belas laporan sebelumnya: ia sudah
     * menangani periode berjalan (start..hari ini WIB) dan periode yang belum
     * mulai (0). Menghitung panjang periode secara lokal akan MENGHUKUM periode
     * berjalan atas hari yang belum datang — periode 31 hari pada hari ke-9
     * dengan muatan 9 hari harus memberi 100%, bukan 29%.
     *
     * percent NULL — bukan 0.0 — ketika days_counted 0 (periode belum mulai),
     * dan blade mencetak '—': nol persen berarti ada hari yang diharapkan dan
     * tak satu pun terisi, sedangkan '—' berarti belum ada hari yang diharapkan
     * sama sekali. Pembagian dengan nol tidak pernah terjadi karena penyebutnya
     * diperiksa SEBELUM pembagian.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{days_in_period: int, days_with_load: int, days_counted: int, percent: float|null}
     */
    protected function completenessOf(Period $period, array $daily): array
    {
        $daysCounted = ReportPeriodDays::counted($period);
        // COUNT DISTINCT tanggal yang punya minimal satu muatan. daily[] punya
        // tepat satu baris per tanggal semacam itu, jadi menghitungnya ADALAH
        // hitungan distinct-nya — satu definisi, satu jawaban.
        $daysWithLoad = count($daily);

        return [
            'days_in_period' => ReportPeriodDays::inPeriod($period),
            'days_with_load' => $daysWithLoad,
            'days_counted' => $daysCounted,
            'percent' => $daysCounted === 0
                ? null
                : round(100 * $daysWithLoad / $daysCounted, 1),
        ];
    }

    /**
     * Satu daftar kejadian bertanggal untuk satu kolom teks bebas.
     *
     * findings dan remarks menjadi DUA DAFTAR TERPISAH, masing-masing
     * {date, value}, urut tanggal. Keduanya TEKS BEBAS, sehingga tidak disebar
     * menjadi rekap kategoris — itu akan memperlakukan prosa sebagai enum, dan
     * isi yang berulang sama persis TIDAK dikelompokkan.
     *
     * Entri kosong/whitespace dibuang (sudah menjadi null di loadsOf()). Satu
     * muatan yang mengisi KEDUA kolom muncul di KEDUA daftar, dan itu benar —
     * menyatukan kedua daftar justru akan menampilkan muatan itu dua kali di
     * dalam satu daftar yang sama.
     *
     * Muatan tanpa event_date tetap muncul dengan date null, dan diurutkan
     * PALING BELAKANG: ia tetap sebuah catatan yang ditulis seseorang, dan
     * membuangnya akan menghilangkan prosa yang tidak ada di tempat lain mana
     * pun di layar ini.
     *
     * @param  Collection<int, object>  $loads
     * @return list<array{date: string|null, value: string}>
     */
    protected function textEntriesOf(Collection $loads, string $column): array
    {
        $entries = $loads
            ->filter(fn ($load) => $load->{$column} !== null)
            ->map(fn ($load) => [
                'date' => $load->date,
                'value' => (string) $load->{$column},
            ])
            ->values()
            ->all();

        usort($entries, fn (array $a, array $b) => ((string) ($a['date'] ?? "\u{FFFF}"))
            <=> ((string) ($b['date'] ?? "\u{FFFF}")));

        return $entries;
    }

    // ------------------------------------------------------------------
    // Aliran ekspor
    // ------------------------------------------------------------------

    /**
     * Separuh malas buildExportRows(): satu array per MUATAN, ditarik
     * berpotongan supaya periode panjang tidak pernah menjadi satu koleksi.
     *
     * Ketiga nilai konteks (periode / mill / production line) dihitung SEKALI
     * oleh pemanggil dan diulang VERBATIM di setiap baris, bersama tanggal dan
     * status RECORD-nya — lima kolom konteks, sesuai
     * EXPORT_CONTEXT_COLUMN_COUNT.
     *
     * KOLOM STATUS DICETAK LEWAT ExportValue::status() KARENA IA STATUS RECORD.
     * ExportValue::option() — yang dipakai untuk kolom enum PENGUKURAN — TIDAK
     * dipanggil di layar ini, dan ketiadaannya bukan kelalaian: tabel
     * `solid_waste_disposal_details` tidak punya satu pun kolom berenum. Keempat
     * kolom kategorisnya TEKS BEBAS, jadi memetakannya lewat Display::option()
     * berarti mengarang label untuk nilai yang belum pernah terlihat. Bila
     * kelak sebuah kolom enum pengukuran ditambahkan ke tabel ini, ia WAJIB
     * dicetak lewat ExportValue::option() — bukan status() — dan catatan ini
     * ada supaya pertukaran itu tidak pernah terjadi tanpa sengaja.
     *
     * @param  array{0: string, 1: string, 2: string}  $context
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $context): Generator
    {
        $query = (clone $recordQuery)
            ->with([
                'solidWasteDisposalDetails' => fn ($detailQuery) => $detailQuery
                    ->orderBy('event_date')
                    ->orderBy('id'),
            ])
            ->orderBy('solid_waste_disposal_records.date')
            ->orderBy('solid_waste_disposal_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var SolidWasteDisposalRecord $record */
            $rowContext = array_merge($context, [
                optional($record->date)->toDateString(),
                // Status RECORD, label Indonesia — ExportValue::status().
                ExportValue::status($this->recordStatusValue($record)),
            ]);

            foreach ($record->solidWasteDisposalDetails as $detail) {
                /** @var SolidWasteDisposalDetail $detail */
                $values = [];

                // KETUJUH BELAS kolom, urutan DETAIL_EXPORT_FIELDS. Definisinya
                // dibaca dari konstanta, tidak ditulis ulang di sini, supaya
                // daftarnya dapat diasersikan.
                foreach (self::DETAIL_EXPORT_FIELDS as $field) {
                    $value = $detail->{$field};

                    if ($field === 'event_date') {
                        $value = $value === null ? null : $this->dateStringOf($value);
                    }

                    // Netto NULL tetap NULL → sel KOSONG. Bukan 0, dan barisnya
                    // tidak dibuang.
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
     * OPERATOR DITERIMA SEJAK COMMIT PERTAMA — screen-163 (laporan SWD mobile)
     * dibangun dalam seri yang sama dan merupakan pemanggil sahnya. Menerima
     * peran DI SINI hanya sepertiga dari perubahannya: resolveBusinessUnit()
     * juga HARUS menempatkan Operator di cabang TERIKAT MILL, atau baris ini
     * membuka pembacaan lintas mill. Satu-satunya titik masuk yang tetap
     * menolak Operator adalah businessUnitOptions(), yang memeriksa perannya
     * sendiri PERSIS karena penjaga ini meloloskannya.
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
            // screen-163 (mobile). Lihat resolveBusinessUnit(), yang WAJIB
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
     * saat menulis uji KernelPlantReportService, dibuktikan lewat permintaan
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
     * Dipakai oleh keempat rekap kategoris DAN oleh daily[], supaya aturan
     * 'rata-rata selalu bersama penyebutnya' berlaku SAMA di tiap tingkat —
     * bukan hanya di blok `loads`. Konsekuensinya net_weight_avg sebuah baris
     * adalah null ketika baris itu tidak punya satu pun muatan bertimbang,
     * sekalipun baris lain punya.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function withWeightFigures(array $row): array
    {
        $count = (int) $row['net_weight_load_count'];
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
     * dinormalkan.
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
     * keenam belas laporan stasiun sebelumnya, sehingga pemisahan
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

    protected function recordStatusValue(SolidWasteDisposalRecord $record): string
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
     * yang SheetWriter dibuat untuk memperbaikinya (sampai 2026-10-04 setiap
     * 'Ekspor Excel' mengirim byte CSV bernama .xlsx dan Excel menolak
     * membukanya). Kontrak API layar ini memakai 'xlsx', jadi penerjemahannya
     * terjadi DI SATU TEMPAT, di sini.
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
                "laporan-solid-waste-disposal_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-solid-waste-disposal_{$slug}_{$timestamp}.csv",
        ];
    }
}
