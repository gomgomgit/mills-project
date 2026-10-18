<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Support\Display;
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
 * KernelDispatchReportService — screen-164--laporan-kernel-dispatch-web /
 * usecase-170 (Laporan Periode Kernel Dispatch), dan pasangan mobile-nya
 * screen-165 yang memakai KETIGA rute data yang sama.
 *
 * Dipakai bersama oleh controller API (App\Http\Controllers\Api\
 * KernelDispatchReportController) dan komponen Livewire (App\Livewire\
 * Dashboard\LaporanKernelDispatch) — pembagian yang sama dengan ketujuh belas
 * laporan stasiun sebelumnya, sehingga halaman web dan API tidak pernah dapat
 * menyebut angka yang berbeda.
 *
 * BACA SAJA SECARA KONSTRUKSI: setiap metode publik adalah SELECT, dan prefix
 * /api/kernel-dispatch-reports tidak membawa satu pun POST/PUT/PATCH/DELETE.
 *
 * =====================================================================
 * LAPORAN HIBRIDA PERTAMA DI PROYEK INI — DUA PRESEDEN, BUKAN SATU
 * =====================================================================
 * Layar ini menjawab TIGA pertanyaan yang berbeda sifatnya: BERAPA BANYAK inti
 * terkirim, SEBAIK APA MUTUNYA, dan APAKAH DOKUMENNYA LENGKAP. Dua yang
 * pertama belum pernah hidup dalam satu laporan di basis kode ini:
 *
 *   - SEPARUH TRANSAKSI disalin dari SolidWasteDisposalReportService
 *     (screen-162): bentuk per-transaksi (satu baris detail = SATU PENGIRIMAN),
 *     rekap kategoris teks bebas dengan baris 'tidak tercatat', penghitung
 *     kepatuhan sebagai blok tersendiri, `daily[]` satu entri per
 *     HARI-BERPENGIRIMAN, dan ReportPeriodDays::counted.
 *   - SEPARUH MUTU disalin dari ProcessWaterReportService (screen-160):
 *     min/avg/max per ukuran dengan PENYEBUT PER UKURAN, dan kalimat yang
 *     menyatakan bahwa penyebut antar-ukuran boleh berbeda. Di sana ukurannya
 *     per slot jam; di sini per pengiriman — rumusnya sama, satuannya berbeda.
 *
 * Menyalin dari SATU preseden saja akan kehilangan separuh bentuknya.
 *
 * ---------------------------------------------------------------------
 * EMPAT HAL YANG MENENTUKAN BENAR-SALAHNYA LAYAR INI
 * ---------------------------------------------------------------------
 *  1. KEEMPAT `measured_count` DIHITUNG TERPISAH. kernel_moisture_percent,
 *     dirt_impurities_percent, ffa_percent dan broken_kernel_percent adalah
 *     kolom nullable yang INDEPENDEN: satu pengiriman boleh punya hasil uji
 *     kadar air tanpa ALB, sehingga ia masuk satu penyebut dan tidak masuk yang
 *     lain. SATU PENYEBUT TUNGGAL UNTUK KEEMPATNYA akan tampak rapi, lolos
 *     review, dan SALAH SECARA SISTEMATIS. Keempatnya dihitung, DITERBITKAN,
 *     dan dicetak di kartunya masing-masing.
 *
 *  2. PENJAGANYA PER UKURAN, BUKAN SATU PENJAGA GLOBAL. Ketika measured_count
 *     sebuah ukuran 0, min/avg/max ukuran ITU menjadi null (blade '—')
 *     sementara ketiga ukuran lain TETAP menerbitkan angkanya. Satu penjaga
 *     'ada data mutu atau tidak' akan mengosongkan keempatnya sekaligus.
 *
 *  3. not_released_count MENGHITUNG HANYA `value === 'not_released'` PERSIS.
 *     Baris null TIDAK termasuk. 'Tidak tercatat' dan 'tidak dilepas' adalah
 *     dua keadaan berbeda, dan menggabungkannya MENUDUH pengiriman yang sekadar
 *     belum diisi statusnya — tuduhan yang akan sampai ke orang sungguhan.
 *
 *  4. NILAI ENUM TAK DIKENAL TIDAK DIBUANG. KernelDispatchRecordService
 *     menyimpan `filled(...) ? $value : null` TANPA daftar tertutup, jadi jalur
 *     selain form web dapat menyimpan apa pun. Nilai semacam itu mendapat
 *     BARISNYA SENDIRI di by_gate_status, apa adanya — tidak dibuang dan tidak
 *     dipaksa masuk ke salah satu pilihan — dan Display::option() di blade
 *     memang jatuh ke nilai mentah untuk yang tak dikenal. Konsekuensi yang
 *     diasersikan: penjumlahan `dispatch_count` rekap itu TETAP sama dengan
 *     `dispatch_count` di atasnya.
 *
 * ---------------------------------------------------------------------
 * SATUAN HITUNG DAN LINGKUP BARIS
 * ---------------------------------------------------------------------
 * SATU BARIS `kernel_dispatch_details` = SATU PENGIRIMAN: satu kendaraan yang
 * berangkat membawa inti ke pembeli, berkunci `event_date`, dan jumlah baris
 * per hari sepenuhnya bebas. `dispatch_count` karena itu menghitung BARIS
 * DETAIL yang ter-flatten, BUKAN record induk — satu record induk membawa
 * banyak pengiriman.
 *
 * Record induk disaring: `production_line_id` = line terpilih (kolom SNAPSHOT
 * milik record itu sendiri, bukan `stations.production_line_id` — menyaring
 * lewat `stations` akan MENULIS ULANG SEJARAH setiap kali sebuah stasiun
 * dipindahkan ke line lain), `stations.type` = 'kernel-dispatch', dan `date`
 * INDUK di dalam rentang periode (inklusif, whereDate()). TIDAK ADA penyaring
 * status: baris draft adalah bagian dari setiap angka, dan
 * `draft_dispatch_count` MENYATAKAN berapa.
 *
 * `net_weight_mt` DIBACA APA ADANYA dan TIDAK PERNAH dihitung ulang dari
 * bruto − tara: turunannya terjadi di KernelDispatchRecordService, dan baris
 * yang ditulis lewat jalur lain dapat punya netto tanpa bruto/tara sama sekali.
 * NULL BUKAN NOL — nol pengukuran menjadi null, bukan 0.0, dan
 * `missing_net_weight_dispatch_count` ditonjolkan tersendiri karena netto
 * adalah kolom TURUNAN SERVICE: kosongnya berarti PENIMBANGAN BELUM SELESAI,
 * bukan 'lupa diisi'.
 *
 * ---------------------------------------------------------------------
 * `silo_source_id` ADALAH TEKS BEBAS MESKI NAMANYA BERAKHIRAN `_id`
 * ---------------------------------------------------------------------
 * Diverifikasi tiga cara: migrasi 2026_08_31_000007 menuliskannya
 * `$table->string('silo_source_id')->nullable()`; TIDAK ADA tabel silo di satu
 * migrasi pun; dan form-kernel-dispatch.blade.php merendernya sebagai
 * `<input type="text">`. Laporan ini memperlakukannya PERSIS seperti kedua
 * kolom teks lainnya: nol join, nol pencarian nama, nol pemetaan. Andaian wajar
 * bahwa ia foreign key akan menghasilkan pekerjaan yang tidak mungkin
 * diselesaikan.
 *
 * `remarks_gate_status` adalah SATU-SATUNYA kolom berpilihan TERTUTUP di
 * stasiun ini (form merendernya `<select>` dengan tepat dua `<option>`), dan ia
 * KEBALIKAN dari `action_taken_status` di Process Water — yang namanya juga
 * berbunyi seperti status tetapi `<input type="text">` dan karena itu TIDAK
 * disebar menjadi rekap. Perbedaannya dibaca dari FORM masing-masing, bukan
 * dari namanya. PAYLOAD MENERBITKAN KODE MENTAH; LABELNYA DITURUNKAN DI BLADE
 * dan DI EKSPOR lewat Display::option(). Menerbitkan label di payload akan
 * memindahkan kosakata ke lapisan yang salah dan membuat pasangan mobile
 * (screen-165) menerima label yang tidak dapat ia ubah.
 *
 * ---------------------------------------------------------------------
 * TIGA KETIADAAN YANG DINYATAKAN, BUKAN DISEMBUNYIKAN
 * ---------------------------------------------------------------------
 *  - TIDAK ADA BLOK hourly DAN TIDAK ADA WAKTU PUTAR KENDARAAN. Baris detail
 *    stasiun ini hanya membawa `event_date` — sebuah TANGGAL — tanpa satu pun
 *    kolom waktu: tidak ada jam masuk, tidak ada jam keluar. Keduanya BUKAN
 *    fitur yang ditunda. `_note_no_hourly` menyebut secara eksplisit bahwa di
 *    CPO Dispatch (screen-166/167) keduanya JUSTRU ADA karena tabel itu punya
 *    time_in/time_out, supaya ketiadaan di sini tidak dibaca sebagai kelalaian
 *    dan supaya tidak ada yang menambahkannya kemudian dengan menebak jam dari
 *    `created_at` — yang berarti menebak dari waktu INPUT, bukan waktu kejadian.
 *  - TANPA PENANDAAN NILAI MUTU DI LUAR BATAS: stasiun ini tidak punya master
 *    ambang mutu apa pun. Sebuah laporan mutu TANPA penandaan terlihat seperti
 *    laporan yang lupa membandingkan, jadi sebabnya ditulis
 *    (`_note_no_quality_thresholds`).
 *  - KOLOM `shift` TIDAK DITERBITKAN di payload maupun layar — hanya di ekspor.
 *    Ia teks bebas pada tabel DETAIL (bukan FK ke `shifts`), sehingga
 *    menyebarnya berarti mengelompokkan EJAAN, bukan giliran kerja.
 *
 * HANYA SATU KOLOM TEKS KEJADIAN: `findings`. Stasiun ini TIDAK punya
 * `remarks`, berbeda dari Solid Waste Disposal — jadi `text_entries` di sini
 * BERISI SATU DAFTAR, dan struktur dua-daftar dari sana sengaja TIDAK disalin.
 *
 * ---------------------------------------------------------------------
 * CAKUPAN LINTAS MILL DITUTUP DI TIGA TITIK YANG BERBEDA, DENGAN SENGAJA
 * ---------------------------------------------------------------------
 *   1. resolveBusinessUnit() MENGABAIKAN business_unit_id kiriman klien untuk
 *      Operator / Supervisor / Mill Management — tidak divalidasi, tidak
 *      dibandingkan, dibuang. Menyebut id mill lain menjawab 200 berisi data
 *      MILL SENDIRI, sengaja BUKAN 403: sebuah 403 akan memastikan mill itu ada.
 *   2. resolveProductionLine() MENOLAK line milik mill lain dengan 403 — di
 *      sini ada pegangan nyata ke data mill lain, dan line adalah parameter
 *      yang pengguna PILIH secara sadar, bukan konteks yang diturunkan dari
 *      akunnya.
 *   3. authorizePeriod() MENOLAK periode milik mill lain dengan 403.
 *
 * Akun terikat mill yang `users.business_unit_id`-nya NULL GAGAL TERTUTUP dan
 * daftar seluruh mill tidak pernah dibaca — lihat allBusinessUnits(), yang
 * publik dan sengaja sederhana supaya sebuah spy dapat MEMBUKTIKAN ia tidak
 * pernah dipanggil. Jawabannya 422, bukan 403: master yang rusak, bukan izin
 * yang kurang.
 *
 * OPERATOR DITERIMA SEJAK COMMIT PERTAMA pada ketiga rute data — preseden
 * Grading / Kernel Plant / Engine Room / Process Water / Solid Waste Disposal,
 * bukan preseden Weighbridge yang harus melebarkan akses sebagai tindak lanjut.
 * Alasannya screen-165 (laporan Kernel Dispatch mobile) dibangun dalam seri
 * yang sama dan merupakan pemanggil sahnya. ADMISINYA SATU PERUBAHAN DI TIGA
 * TEMPAT, BUKAN TIGA PERUBAHAN: daftar peran di routes/api.php, guardAccess()
 * di bawah, DAN cabang TERIKAT-MILL di resolveBusinessUnit(). Melebarkan dua
 * yang pertama saja akan menjatuhkan Operator ke cabang Admin, tempat
 * business_unit_id kiriman klien JUSTRU dihormati — itu kebocoran lintas mill,
 * bukan cacat tampilan.
 *
 * YANG TETAP TERTUTUP: businessUnitOptions() menolak siapa pun selain Admin
 * dengan 403, ditegakkan DI DALAM service dan bukan oleh middleware — PERSIS
 * karena middleware prefix ini menerima keempat peran demi ketiga rute data,
 * sehingga penjaga di middleware tidak akan menutup rute itu sama sekali. Rute
 * WEB /reports/kernel-dispatch juga TIDAK dilebarkan.
 *
 * ---------------------------------------------------------------------
 * SELURUH AGREGASI DI PHP, NOL DI SQL
 * ---------------------------------------------------------------------
 * Keputusan yang sama dengan ketujuh belas laporan sebelumnya, dengan alasan
 * yang menentukan: perilaku agregat SQL atas kolom NULLABLE TIDAK sama di
 * SQLite (suite uji) dan PostgreSQL (produksi/e2e), dan aturan
 * penyebut-tersendiri serta null-bukan-nol adalah justru yang hilang dalam
 * perbedaan itu. min/avg/max keempat ukuran mutu karena itu dihitung DI PHP,
 * bukan lewat MIN()/AVG()/MAX() — yang juga menghindarkan perbedaan pembulatan
 * antar-mesin basis data. `ILIKE` adalah SYNTAX ERROR di SQLite sementara
 * `LIKE` telanjang case-SENSITIVE di PostgreSQL, jadi ekspresi pengelompokan di
 * sisi SQL dapat meloloskan seluruh suite dan tetap salah di produksi. Kueri di
 * bawah hanya memilih baris dan menyaringnya dengan helper portabel Laravel
 * (whereDate / whereKey), dan urutan SELALU dinyatakan eksplisit karena
 * PostgreSQL tidak menjanjikan urutan default apa pun.
 */
class KernelDispatchReportService
{
    /**
     * Batas baris ekspor, dihitung atas BARIS YANG AKAN DITERBITKAN — yaitu
     * baris DETAIL (satu pengiriman = satu baris berkas), bukan record induk.
     * Satu record induk dapat membawa puluhan pengiriman, jadi menghitung
     * record akan meloloskan berkas jauh lebih besar daripada batasnya.
     *
     * Dibaca lewat `static::` di buildExportRows() supaya uji unit dapat
     * MENURUNKANNYA di subclass alih-alih menyemai 50.001 baris.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang keberadaannya menentukan apakah sebuah periode mencakup stasiun ini.
     *
     * Diambil dari App\Enums\StationType, tidak ditulis sebagai literal: kunci
     * snake_case akan ter-compile, terlihat benar, dan tidak pernah cocok
     * dengan satu baris master pun. Nilainya 'kernel-dispatch' BERTANDA HUBUNG.
     */
    protected const STATION_TYPE = StationTypeEnum::KernelDispatch->value;

    /**
     * Format ekspor yang dikenal layar ini — DAFTAR TERTUTUP {csv, xlsx},
     * sama dengan screen-162 dan berbeda dari keenam belas laporan yang lebih
     * tua ({csv, excel}): kontrak API layar ini menyebut 'xlsx' secara harfiah.
     * Penerjemahan ke SheetWriter::FORMAT_EXCEL terjadi di sheetFormatOf() —
     * memanggil SheetWriter::open('xlsx') akan diam-diam menghasilkan CSV
     * berekstensi .xlsx, yaitu persis cacat yang SheetWriter dibuat untuk
     * memperbaikinya. Daftarnya PEKA HURUF: yang dilakukan controller hanya
     * trim, bukan lowercase, sehingga 'CSV' adalah 422.
     */
    public const SUPPORTED_FORMATS = ['csv', 'xlsx'];

    /** Status yang dihitung sebagai log sheet belum selesai (draft). */
    public const DRAFT_STATUSES = [
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ];

    /**
     * KEEMPAT UKURAN MUTU, masing-masing dengan PENYEBUTNYA SENDIRI.
     *
     * Didaftar sebagai konstanta — bukan empat blok kode yang disalin — PERSIS
     * karena empat salinan adalah empat kesempatan kehilangan penyebut atau
     * mewarisi penyebut kartu lain. qualityOf() me-loop daftar ini, sehingga
     * tidak ada satu tempat pun di bawah yang dapat memakai penyebut bersama.
     *
     * Keempatnya nullable dan INDEPENDEN: satu pengiriman boleh punya hasil uji
     * kadar air tanpa ALB. Urutannya mengikuti urutan kolom pada layar Detail
     * stasiun ini (kadar air → kotoran → ALB → inti pecah), yaitu urutan yang
     * sama dengan yang dibaca manusia di log sheet-nya.
     *
     * @var array<int, string>
     */
    public const QUALITY_MEASURES = [
        'kernel_moisture_percent',
        'dirt_impurities_percent',
        'ffa_percent',
        'broken_kernel_percent',
    ];

    /**
     * Berapa desimal yang dipakai membulatkan ukuran mutu. TIGA, bukan dua:
     * kadar kotoran bergerak di orde perseratus (0,01–0,03 persen pada data
     * nyata), sehingga dua desimal akan melipat dua nilai yang berbeda menjadi
     * satu angka yang sama. Berat tetap dua desimal (satuannya MT).
     *
     * PEMBULATAN TERJADI DI SINI DAN TIDAK DI TEMPAT LAIN, sehingga blade, API
     * dan ekspor tidak pernah dapat membulatkan secara berbeda.
     */
    public const QUALITY_DECIMALS = 3;

    /**
     * Berapa kolom pertama EXPORT_HEADER yang merupakan KONTEKS, diulang
     * VERBATIM di setiap baris. Diterbitkan sebagai konstanta supaya bentuk
     * ekspor diturunkan, tidak dihitung dengan tangan.
     *
     * ENAM, BUKAN LIMA seperti ketujuh belas laporan sebelumnya: spec layar ini
     * menuntut RENTANG TANGGAL periode ikut sebagai kolom konteks yang diulang
     * verbatim, dan rentang itu tidak dapat diturunkan dari nama periode (nama
     * periode adalah teks bebas yang tidak dijamin memuat tanggalnya). Urutan:
     * Periode, Rentang Periode, Mill, Production Line, Tanggal Record, Status.
     *
     * Dua kolom terakhir memang BERBEDA antar-record — ia tanggal dan status
     * RECORD INDUK, bukan konteks periode — dan itu berlaku identik di ketujuh
     * belas laporan lain; yang dijanjikan sama di setiap baris adalah keempat
     * kolom pertama.
     */
    public const EXPORT_CONTEXT_COLUMN_COUNT = 6;

    /**
     * DUA PULUH SATU kolom detail yang masuk ekspor, dalam urutan berkas.
     *
     * Didaftar sebagai konstanta — bukan ditulis inline di streamExportRows() —
     * supaya daftar ini dapat DIASERSIKAN, bukan diingat. LIMA di antaranya
     * (`weighbridge_ticket_no`, `waybill_number`, `vehicle_plate_no`,
     * `driver_name`, `weighbridge_operator_id`) tidak muncul di SATU ANGKA
     * LAYAR PUN, dan justru karena itu kelimanya yang paling mudah tertinggal
     * dari ekspor. `shift` juga ada di sini meski tidak ada di layar: ekspor
     * adalah rincian MENTAH, jadi pengecualian sebuah kolom dari layar tidak
     * berarti kolom itu dibuang dari berkas.
     *
     * @var array<int, string>
     */
    public const DETAIL_EXPORT_FIELDS = [
        'event_date',
        'shift',
        'weighbridge_ticket_no',
        'waybill_number',
        'transporter_contractor',
        'vehicle_plate_no',
        'driver_name',
        'silo_source_id',
        'destination_buyer',
        'gross_weight_mt',
        'tare_weight_mt',
        'net_weight_mt',
        'kernel_moisture_percent',
        'dirt_impurities_percent',
        'ffa_percent',
        'broken_kernel_percent',
        'security_seal_no_top',
        'security_seal_no_bottom',
        'weighbridge_operator_id',
        'remarks_gate_status',
        'findings',
    ];

    /**
     * Label kolom ekspor: 6 kolom konteks + dua puluh satu kolom detail = 27.
     *
     * LABELNYA MENGIKUTI LAYAR DETAIL stasiun ini
     * (resources/views/livewire/data/detail-kernel-dispatch.blade.php),
     * VERBATIM — bukan nama kolom database. Konvensi proyek, sudah berlaku di
     * seluruh ekspor laporan: berkas yang tidak dapat menggantikan layar Detail
     * tidak layak diekspor.
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
        'Weighbridge Ticket No',
        'Waybill Number',
        'Transporter/Contractor',
        'Vehicle Plate No',
        'Driver Name',
        'Silo Source ID',
        'Destination/Buyer',
        'Gross Weight (MT)',
        'Tare Weight (MT)',
        'Net Weight (MT)',
        'Kernel Moisture (%)',
        'Dirt/Impurities (%)',
        'FFA (%)',
        'Broken Kernel (%)',
        'Security Seal No (Top)',
        'Security Seal No (Bottom)',
        'Weighbridge Operator ID',
        'Remarks/Gate Status',
        'Findings',
    ];

    /** Prosa `quality._note` — dirender UTUH di layar. */
    public const NOTE_QUALITY_DENOMINATORS = 'KEEMPAT PENYEBUT DI BAWAH BOLEH BERBEDA SATU DENGAN '
        .'LAINNYA, dan boleh berbeda dari jumlah pengiriman periode ini. Sebabnya sederhana dan bukan '
        .'cacat data: kadar air inti, kadar kotoran, kadar asam lemak bebas dan kadar inti pecah adalah '
        .'EMPAT UJI YANG BERDIRI SENDIRI, dan satu pengiriman boleh punya sebagian hasil uji saja. '
        .'Karena itu setiap ukuran menyebut penyebutnya SENDIRI — berapa pengiriman yang kolom ITU-nya '
        .'benar-benar terisi — dan tidak satu pun dari keempatnya meminjam penyebut dari yang lain. '
        .'Pembaca yang melihat kadar air dihitung atas 12 pengiriman sementara ALB atas 7 sedang melihat '
        .'dua uji yang tidak selalu dilakukan bersama, BUKAN lima hasil uji yang hilang. Satu penyebut '
        .'tunggal untuk keempat ukuran akan tampak lebih rapi dan akan salah untuk setidaknya tiga di '
        .'antaranya: ia mengempiskan ukuran yang jarang diuji sambil tetap menghasilkan angka yang '
        .'terlihat sah. Dan ketika sebuah ukuran tidak punya satu hasil uji pun, hanya ukuran ITU yang '
        .'ditulis sebagai tanda pisah — ketiga ukuran lain tetap menampilkan angkanya, karena yang '
        .'kosong adalah satu kolom, bukan seluruh mutu periode ini.';

    /** Prosa `_note_no_hourly` — dirender UTUH di layar. */
    public const NOTE_NO_HOURLY = 'Sebaran pengiriman per jam dan waktu putar kendaraan KEDUANYA TIDAK '
        .'DAPAT dibuat untuk stasiun ini, dan ketiadaannya dinyatakan di sini alih-alih disembunyikan. '
        .'Baris detail Kernel Dispatch hanya membawa event_date — sebuah tanggal — dan tidak punya satu '
        .'pun kolom waktu: tidak ada jam masuk, tidak ada jam keluar. Bukan kolom waktu yang kosong, '
        .'bukan kolom yang belum diisi operator: kolomnya tidak ada. Karena itu pertanyaan tentang kapan '
        .'dalam sehari pengiriman paling padat, dan pertanyaan tentang berapa lama satu kendaraan berada '
        .'di area pabrik, tidak dapat dihitung dari data yang ada dalam bentuk apa pun — tidak sebagai '
        .'grafik 24 kolom, tidak sebagai kartu jam tersibuk, tidak sebagai rata-rata menit, tidak pula '
        .'sebagai perkiraan. KEDUANYA BUKAN FITUR YANG DITUNDA. Bandingkan CPO Dispatch (screen-166 dan '
        .'screen-167): tabel detail stasiun itu PUNYA time_in dan time_out, sehingga di sana sebaran per '
        .'jam dan waktu putar kendaraan JUSTRU ADA dan memang wajib ada — perbedaannya terletak pada '
        .'kolom yang tersedia, bukan pada kelengkapan laporannya. Menurunkan jam dari created_at berarti '
        .'menebak dari waktu INPUT, bukan waktu kejadian, dan jawaban yang terlihat rinci padahal tidak '
        .'pernah diukur lebih buruk daripada tidak ada jawaban. Satu-satunya sumbu waktu yang tersedia '
        .'di layar ini adalah tanggal, yaitu rekap harian.';

    /** Prosa `_note_no_quality_thresholds` — dirender UTUH di layar. */
    public const NOTE_NO_QUALITY_THRESHOLDS = 'Tidak ada satu nilai mutu pun di layar ini yang ditandai '
        .'sebagai di luar batas, dan ketiadaan penandaan itu disengaja: stasiun Kernel Dispatch tidak '
        .'punya tabel master ambang mutu apa pun — tidak ada baris acuan untuk kadar air inti, kadar '
        .'kotoran, kadar asam lemak bebas, maupun kadar inti pecah. Sebuah laporan mutu tanpa penandaan '
        .'mudah terbaca sebagai laporan yang lupa membandingkan, jadi sebabnya ditulis di sini. '
        .'Menurunkan ambang dari data periode ini sendiri akan menghasilkan garis yang terlihat '
        .'berwibawa lalu dibaca sebagai batas resmi padahal bukan, dan garis itu ikut bergeser setiap '
        .'kali kelengkapan pencatatan berubah — sebuah nilai dapat berpindah dari aman ke melanggar '
        .'hanya karena pengiriman lain ikut diuji. Keempat ukuran karena itu ditampilkan apa adanya, '
        .'tanpa warna peringatan dan tanpa satu sel yang ditandai; penilaian layak atau tidak layak '
        .'tetap di tangan pembacanya, yang memegang spesifikasi pembelinya dan laporan ini tidak.';

    /** Prosa `_note_shift_not_published` — dirender UTUH di layar. */
    public const NOTE_SHIFT_NOT_PUBLISHED = 'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan '
        .'daftar nilai tertutup dan bukan relasi ke master. Menyebarkan teks bebas menghasilkan satu '
        .'nilai per ejaan yang berbeda, sehingga empat penulisan dari satu giliran kerja yang sama akan '
        .'terbaca sebagai empat kategori. Kolom itu karena itu TIDAK diterbitkan di payload maupun di '
        .'layar ini — bukan sebagai rekap, bukan sebagai kolom tabel, bukan pula sebagai tempat kosong. '
        .'Ia TETAP ikut di berkas ekspor pada setiap baris pengiriman, bersama nomor tiket timbang, '
        .'nomor surat jalan, nomor kendaraan, nama pengemudi dan kode operator timbang, karena ekspor '
        .'adalah rincian mentah. Pengelompokan per giliran kerja yang sungguhan datang dari kolom '
        .'shift_id pada tabel INDUK dan berlaku untuk 18 stasiun sekaligus — pekerjaan tersendiri, bukan '
        .'sesuatu yang ditempelkan pada satu laporan.';

    /** Prosa `compliance._note`. */
    public const NOTE_COMPLIANCE = 'Nomor surat jalan dan kedua nomor segel keamanan adalah JEJAK '
        .'KEPATUHAN, bukan ukuran produksi: ketiganya menyatakan dokumen angkut yang tidak terbit. '
        .'Karena itu ketiganya diterbitkan HANYA sebagai penghitung kehadiran dan tidak pernah '
        .'dirata-rata, tidak pernah diurutkan, dan tidak pernah digrafikkan. Sebuah nomor segel tidak '
        .'punya besaran: segel bernomor 90021 bukan lebih besar daripada segel bernomor 90018, selisih '
        .'keduanya tidak berarti apa pun, dan rata-rata nomor segel adalah angka yang tidak mengukur '
        .'apa-apa. SEGEL ATAS DAN SEGEL BAWAH DIHITUNG TERPISAH DAN TIDAK PERNAH DIJUMLAHKAN MENJADI '
        .'SATU ANGKA. Satu kendaraan yang kehilangan satu segel adalah keadaan yang berbeda dari dua '
        .'kendaraan yang masing-masing kehilangan satu, dan satu angka gabungan menghapus perbedaan itu '
        .'tepat ketika perbedaan itu paling dibutuhkan. Satu pengiriman yang kehilangan KEDUA segelnya '
        .'terhitung di KEDUA penghitung — itu bukan penghitungan ganda, itu dua fakta, dan keduanya '
        .'benar. Bila seluruh pengiriman periode lengkap, ketiga penghitung menunjukkan nol dan nol itu '
        .'TETAP diterbitkan — nol itulah yang memberi tahu pembaca bahwa pemeriksaannya memang berjalan. '
        .'Nomor-nomornya sendiri tidak diterbitkan di payload layar; ia hanya ada di ekspor.';

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
            // screen-165 (laporan Kernel Dispatch mobile). Operator WAJIB di
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
     *     NOL kueri kernel_dispatch_records dijalankan. TIDAK ADA fallback
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
     * Sebuah periode mencakup Kernel Dispatch ketika ia PUNYA baris
     * `period_stations` untuk station_type 'kernel-dispatch'. Terbaru lebih
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
     * Urutan penjaga disengaja: peran lebih dulu (403), lalu mill (422), lalu
     * production line (422 / 403), lalu id periode (422), lalu periodenya
     * sendiri (404 / 403). Pemanggil yang memang tidak boleh berada di sini
     * tidak pernah mengetahui id periode mana yang ada, dan permintaan yang
     * belum lengkap tidak pernah berubah menjadi 404 tentang periode yang ia
     * tidak pernah berhak menanyakannya.
     *
     * TIGA ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA, dan karena itu
     * ketiganya diterbitkan sebagai kunci tersendiri:
     *   dispatch_count = net_weight_dispatch_count + missing_net_weight_dispatch_count;
     *   penjumlahan dispatch_count tiap rekap = dispatch_count;
     *   penjumlahan daily[].dispatch_count = dispatch_count − undated_dispatch_count.
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
        // keadaan itu nol kueri kernel_dispatch_records sudah dijalankan.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // SATU kueri untuk seluruh periode+line, detail DAN station
        // di-eager-load sekaligus. Semua di bawah ini PHP.
        $dispatches = $this->dispatchesFor($period, $productionLineId);

        $daily = $this->dailyOf($dispatches);

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
            'dispatches' => $this->dispatchesBlockOf($dispatches),
            // SEPARUH MUTU: EMPAT UKURAN, EMPAT PENYEBUT. Lihat qualityOf() —
            // tidak ada satu penyebut bersama di mana pun di bawah.
            'quality' => $this->qualityOf($dispatches),
            // TIGA REKAP TEKS BEBAS, bentuk identik, NOL join dan NOL pemetaan.
            'by_transporter' => $this->breakdownOf($dispatches, 'transporter'),
            // `silo_source_id` ADALAH TEKS, bukan rujukan master — lihat
            // docblock kelas. Tidak pernah dicari namanya.
            'by_silo_source' => $this->breakdownOf($dispatches, 'silo_source'),
            'by_destination_buyer' => $this->breakdownOf($dispatches, 'destination_buyer'),
            // REKAP KEEMPAT BERBEDA SIFATNYA: satu-satunya kolom berpilihan
            // tertutup di stasiun ini. KODE MENTAH di payload; labelnya
            // diturunkan di blade lewat Display::option().
            'by_gate_status' => $this->breakdownOf($dispatches, 'gate_status'),
            // DITONJOLKAN TERSENDIRI di luar rekapnya: inti yang berangkat
            // tanpa pelepasan gerbang adalah kejadian yang perlu DILIHAT, bukan
            // baris yang perlu dihitung. HANYA 'not_released' PERSIS — baris
            // null tidak termasuk, karena 'tidak tercatat' bukan 'tidak
            // dilepas' dan menggabungkannya MENUDUH.
            'not_released_count' => $dispatches
                ->filter(fn ($dispatch) => $dispatch->gate_status === 'not_released')
                ->count(),
            // JEJAK KEPATUHAN, blok tersendiri — sengaja TIDAK di dalam
            // `dispatches`, karena ini bukan ukuran produksi. KEDUA PENGHITUNG
            // SEGEL TIDAK PERNAH DIJUMLAHKAN menjadi satu angka.
            'compliance' => [
                'missing_waybill_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->waybill_number === null)
                    ->count(),
                'missing_seal_top_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->security_seal_no_top === null)
                    ->count(),
                'missing_seal_bottom_count' => $dispatches
                    ->filter(fn ($dispatch) => $dispatch->security_seal_no_bottom === null)
                    ->count(),
                '_note' => self::NOTE_COMPLIANCE,
            ],
            // Pengiriman draft IKUT di seluruh angka di atas, termasuk keempat
            // ukuran mutu; ini pernyataannya, bukan penyaringnya.
            'draft_dispatch_count' => $dispatches
                ->filter(fn ($dispatch) => in_array($dispatch->status, self::DRAFT_STATUSES, true))
                ->count(),
            // PENJAGA INVARIAN. `event_date` NOT NULL di migrasi
            // 2026_08_31_000007, jadi angka ini SELALU 0 dalam praktik — dan
            // layar mencetaknya HANYA ketika > 0, karena mencetak 'nol
            // pengiriman tanpa tanggal' pada setiap laporan berarti mengarang
            // kekhawatiran atas keadaan yang dilarang skema. Pelajaran ini
            // sudah dibayar sekali di screen-162. Cabang > 0 tetap diuji lewat
            // service-ganda in-memory (lihat dispatchesFor()).
            'undated_dispatch_count' => $dispatches
                ->filter(fn ($dispatch) => $dispatch->date === null)
                ->count(),
            'daily' => $daily,
            'daily_total' => $this->dailyTotalOf($daily),
            'completeness' => $this->completenessOf($period, $daily),
            // SATU DAFTAR, BUKAN DUA: stasiun ini TIDAK punya kolom `remarks`,
            // berbeda dari Solid Waste Disposal. Struktur dua-daftar dari sana
            // sengaja tidak disalin, dan kunci `findings` TETAP diterbitkan
            // meski daftarnya kosong supaya layar dapat mencetak pernyataan
            // 'tidak ada' alih-alih menghilangkan bagiannya.
            'text_entries' => [
                'findings' => $this->textEntriesOf($dispatches, 'findings'),
            ],
            '_note_no_hourly' => self::NOTE_NO_HOURLY,
            '_note_no_quality_thresholds' => self::NOTE_NO_QUALITY_THRESHOLDS,
            '_note_shift_not_published' => self::NOTE_SHIFT_NOT_PUBLISHED,
        ];
    }

    /**
     * Alias berkonvensi repo untuk buildSummary(), supaya service ini terbaca
     * sama dengan ketujuh belas saudaranya di titik pemanggilan. Satu
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
        $detailRowCount = KernelDispatchDetail::query()
            ->whereIn(
                'kernel_dispatch_record_id',
                (clone $recordQuery)->select('kernel_dispatch_records.id'),
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
     * ditentukan.
     *
     * Cabang `undated_dispatch_count > 0` TIDAK DAPAT DICAPAI LEWAT BASIS DATA:
     * `event_date` NOT NULL di migrasi, sehingga factory maupun insert langsung
     * menolak barisnya. Cabang itu tetap wajib diuji karena ia penjaga
     * invarian — penjumlahan daily[] yang lebih kecil daripada dispatch_count
     * harus punya satu sebab yang diterbitkan. Metode ini karena itu protected
     * dan sengaja sempit: sebuah subclass uji meng-override-nya untuk
     * memulangkan koleksi objek pengiriman in-memory, dan SELURUH agregasi di
     * bawah berjalan apa adanya atas koleksi itu.
     *
     * @return Collection<int, object>
     */
    protected function dispatchesFor(Period $period, string $productionLineId): Collection
    {
        return $this->dispatchesOf($this->recordsFor($period, $productionLineId));
    }

    /**
     * Seluruh record induk periode+line, dengan baris detailnya DAN stasiunnya
     * di-eager-load SEKALIGUS.
     *
     * EAGER LOAD ADALAH BAGIAN DARI KEBENARAN LAYAR INI, bukan optimasi yang
     * boleh ditunda: tanpa `with(['kernelDispatchDetails', 'station'])`, satu
     * periode 31 hari menghasilkan satu kueri per record untuk detailnya — pola
     * N+1 yang sudah pernah lolos ke produksi pada laporan sebelumnya. Nama
     * relasinya `kernelDispatchDetails` (bukan `details`), mengikuti
     * App\Models\KernelDispatchRecord.
     *
     * Detail diurutkan `event_date` lalu `id`: PostgreSQL tidak menjanjikan
     * urutan default apa pun, dan ekspor harus deterministik.
     *
     * @return Collection<int, KernelDispatchRecord>
     */
    protected function recordsFor(Period $period, string $productionLineId): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            ->with([
                'kernelDispatchDetails' => fn ($query) => $query
                    ->orderBy('event_date')
                    ->orderBy('id'),
                'station',
            ])
            ->orderBy('kernel_dispatch_records.date')
            ->orderBy('kernel_dispatch_records.id')
            ->get();
    }

    /**
     * Kueri dasar atas record induk periode ini.
     *
     * EMPAT HAL YANG DILAKUKAN KUERI INI:
     *
     *  - Join ke `stations` untuk MEMBATASI MILL dan JENIS STASIUN.
     *    `kernel_dispatch_records` tidak membawa business_unit_id maupun
     *    station_type, jadi join inilah satu-satunya cara menyatakan keduanya.
     *    Join-nya 1:1 (station_id adalah FK tunggal), sehingga satu line dengan
     *    DUA unit stasiun Kernel Dispatch tetap menghitung tiap record tepat
     *    sekali — bukan nol dan bukan dua kali.
     *  - Menyaring per line di `kernel_dispatch_records.production_line_id`,
     *    kolom SNAPSHOT milik record itu sendiri — BUKAN lewat
     *    `stations.production_line_id`. Membaca dari kolom record itulah yang
     *    benar secara semantik: untuk record lama yang stasiunnya sudah
     *    DIPINDAH ke line lain, kolom record menunjuk line tempat data itu
     *    benar-benar dihasilkan. Menyaring lewat `stations` akan MENULIS ULANG
     *    SEJARAH setiap kali sebuah stasiun dipindahkan: angka bulan lalu
     *    berubah diam-diam.
     *  - Membatasi keanggotaan INKLUSIF pada `kernel_dispatch_records.date` —
     *    kolom INDUK, bukan `event_date` pada detail. whereDate(), bukan
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
        return KernelDispatchRecord::query()
            ->join('stations', 'stations.id', '=', 'kernel_dispatch_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', self::STATION_TYPE)
            ->where('kernel_dispatch_records.production_line_id', $productionLineId)
            ->whereDate('kernel_dispatch_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('kernel_dispatch_records.date', '<=', $period->end_date->toDateString())
            ->select('kernel_dispatch_records.*');
    }

    /**
     * SELURUH BARIS DETAIL dalam lingkup, di-flatten menjadi objek sederhana
     * yang membawa tepat apa yang dibutuhkan agregasi.
     *
     * Inilah tempat SATUAN HITUNG ditetapkan: satu elemen = SATU PENGIRIMAN,
     * bukan satu record induk.
     *
     * Kolom teks di-TRIM lalu string kosong diperlakukan SAMA DENGAN null di
     * sini, satu kali, supaya tidak ada agregasi di bawah yang perlu
     * mengulanginya — dua baris terpisah untuk '' dan null akan membagi satu
     * keadaan menjadi dua. Yang di-trim HANYA spasi ujung: nilai yang berbeda
     * pada besar-kecil huruf TETAP dua nilai berbeda, karena kolom teks bebas
     * tidak menjamin keduanya hal yang sama, dan gejala data master yang perlu
     * dirapikan harus TERLIHAT.
     *
     * `gate_status` ikut di-trim dengan aturan yang sama, tetapi TIDAK
     * dibandingkan dengan daftar nilai apa pun: nilai di luar kedua pilihan
     * form dibawa APA ADANYA ke rekapnya. Membuangnya akan menghilangkan baris
     * dan membuat penjumlahan rekap berhenti sama dengan dispatch_count.
     *
     * `net_weight` dan KEEMPAT kolom mutu DIBACA APA ADANYA dan NULL TETAP
     * NULL — tidak pernah dipaksa menjadi 0.0, dan netto tidak pernah dihitung
     * ulang dari bruto − tara.
     *
     * @param  Collection<int, KernelDispatchRecord>  $records
     * @return Collection<int, object>
     */
    protected function dispatchesOf(Collection $records): Collection
    {
        $dispatches = [];

        foreach ($records as $record) {
            $status = $this->recordStatusValue($record);

            foreach ($record->kernelDispatchDetails as $detail) {
                /** @var KernelDispatchDetail $detail */
                $quality = [];

                // KEEMPAT KOLOM MUTU dibaca lewat daftar konstantanya, bukan
                // disebut satu per satu: empat salinan adalah empat kesempatan
                // menukar dua kolom tanpa ada yang menyadarinya.
                foreach (self::QUALITY_MEASURES as $measure) {
                    $raw = $detail->{$measure};
                    $quality[$measure] = $raw === null || $raw === '' ? null : (float) $raw;
                }

                $dispatches[] = (object) [
                    // Tanggal KEJADIAN pengiriman. null berarti pengiriman itu
                    // tidak dapat ditempatkan pada hari mana pun — ia tetap
                    // sebuah pengiriman, dan dihitung di
                    // undated_dispatch_count. NOT NULL di skema, jadi ini
                    // penjaga invarian.
                    'date' => $detail->event_date === null
                        ? null
                        : $this->dateStringOf($detail->event_date),
                    'net_weight' => $detail->net_weight_mt === null
                        ? null
                        : (float) $detail->net_weight_mt,
                    'quality' => $quality,
                    'transporter' => $this->textOrNull($detail->transporter_contractor),
                    // TEKS BEBAS meski namanya berakhiran _id. Nol join.
                    'silo_source' => $this->textOrNull($detail->silo_source_id),
                    'destination_buyer' => $this->textOrNull($detail->destination_buyer),
                    // KODE MENTAH. Label diturunkan di blade/ekspor.
                    'gate_status' => $this->textOrNull($detail->remarks_gate_status),
                    'waybill_number' => $this->textOrNull($detail->waybill_number),
                    'security_seal_no_top' => $this->textOrNull($detail->security_seal_no_top),
                    'security_seal_no_bottom' => $this->textOrNull($detail->security_seal_no_bottom),
                    'findings' => $this->textOrNull($detail->findings),
                    // Status INDUK, dibawa turun ke tiap pengiriman supaya
                    // draft_dispatch_count dapat dihitung atas satuan yang sama
                    // dengan dispatch_count.
                    'status' => $status,
                ];
            }
        }

        return collect($dispatches);
    }

    /**
     * Blok `dispatches` — angka pokok periode, dengan PENYEBUTNYA DITERBITKAN.
     *
     * dispatch_count menghitung SELURUH pengiriman, termasuk yang netto-nya
     * kosong dan termasuk milik record draft: 'berapa pengiriman keluar pada
     * periode ini', dan pengiriman yang penimbangannya belum selesai tetap
     * sebuah pengiriman.
     *
     * @param  Collection<int, object>  $dispatches
     * @return array{dispatch_count: int, net_weight_total: float|null, net_weight_avg: float|null, net_weight_dispatch_count: int, missing_net_weight_dispatch_count: int}
     */
    protected function dispatchesBlockOf(Collection $dispatches): array
    {
        $dispatchCount = $dispatches->count();

        // PENYEBUT ANGKA BERAT, dan satu-satunya tempat ia ditetapkan: baris
        // yang net_weight_mt-nya NON-NULL. Null DIBUANG, tidak pernah diganti
        // nol.
        $weights = $dispatches
            ->map(fn ($dispatch) => $dispatch->net_weight)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        $netWeightDispatchCount = count($weights);

        return [
            'dispatch_count' => $dispatchCount,
            // null, BUKAN 0, ketika tak satu pengiriman pun tertimbang: nol
            // akan mengaku-akui total terukur yang sebenarnya tidak ada.
            'net_weight_total' => $netWeightDispatchCount === 0
                ? null
                : round(array_sum($weights), 2),
            // Pembagian berpenjaga: penyebutnya diperiksa SEBELUM pembagian,
            // bukan di-rescue sesudahnya — tidak pernah DivisionByZeroError,
            // dan tidak pernah 0.
            'net_weight_avg' => $netWeightDispatchCount === 0
                ? null
                : round(array_sum($weights) / $netWeightDispatchCount, 2),
            // PENYEBUTNYA, diterbitkan berdampingan dengan rata-rata yang
            // memilikinya.
            'net_weight_dispatch_count' => $netWeightDispatchCount,
            // ANGKA TERSENDIRI, bukan turunan yang dibiarkan dihitung pembaca:
            // artinya PENIMBANGAN BELUM SELESAI, bukan 'lupa diisi'.
            'missing_net_weight_dispatch_count' => $dispatchCount - $netWeightDispatchCount,
        ];
    }

    /**
     * EMPAT UKURAN MUTU, EMPAT PENYEBUT — INILAH INTI LAYAR INI.
     *
     * Setiap ukuran dihitung HANYA atas baris yang kolom ITU-nya non-null, dan
     * melaporkan berapa baris itu. TIDAK ADA satu pun penyebut bersama di
     * payload: satu penyebut tunggal akan mengempiskan setiap ukuran yang
     * jarang diuji sambil tetap menghasilkan angka yang terlihat sepenuhnya
     * masuk akal, dan ia akan salah untuk setidaknya tiga dari empat.
     *
     * PENJAGANYA PER UKURAN, BUKAN SATU PENJAGA GLOBAL: measured_count 0 pada
     * satu ukuran memberi null/null/null pada ukuran ITU saja — ketiga ukuran
     * lain tetap menerbitkan angkanya. Sebuah penjaga 'apakah ada data mutu'
     * yang dipasang di sini akan mengosongkan keempatnya sekaligus, dan itu
     * cacat yang paling mudah ditulis di layar ini.
     *
     * Nilai yang tidak masuk akal (misalnya persen negatif) DILAPORKAN APA
     * ADANYA: tidak ada CHECK constraint pada keempat kolom itu, dan menyaring
     * di lapisan laporan akan menyembunyikan data masukan yang rusak dari orang
     * yang justru harus memperbaikinya.
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

        // DIRENDER UTUH DI LAYAR. Tanpa kalimat ini, pembaca yang melihat kadar
        // air atas 12 pengiriman dan ALB atas 7 akan menyimpulkan ada data yang
        // hilang — padahal yang terjadi adalah dua uji yang tidak selalu
        // dilakukan bersama.
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
     * min / avg / max / measured_count atas nilai SATU ukuran.
     *
     * Masukan kosong adalah null/null/null/0, TIDAK PERNAH 0/0/0/0 — nol
     * berarti 'diuji dan hasilnya nol', sedangkan null berarti 'tidak pernah
     * diuji'. Satu nilai saja memberi min = avg = max, dan itu benar.
     *
     * PEMBULATAN TERJADI DI SINI DAN TIDAK DI TEMPAT LAIN, sehingga blade, API
     * dan ekspor tidak pernah dapat membulatkan secara berbeda. Dihitung DI
     * PHP, bukan lewat MIN()/AVG()/MAX() basis data: angka yang sama harus
     * keluar di SQLite (suite) dan PostgreSQL (produksi/e2e).
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
     * Satu rekap kategoris — bentuk yang sama untuk KEEMPAT rekap, termasuk
     * by_gate_status.
     *
     * PENGELOMPOKAN TERJADI DI PHP ATAS NILAI YANG TERSIMPAN APA ADANYA. Tanpa
     * GROUP BY, tanpa LIKE, dan tegas tanpa ILIKE — ILIKE adalah SYNTAX ERROR
     * di SQLite sementara LIKE telanjang case-SENSITIVE di PostgreSQL, sehingga
     * ekspresi pengelompokan di sisi SQL dapat meloloskan seluruh suite uji dan
     * tetap menggabungkan (atau gagal menggabungkan) baris yang salah di
     * produksi.
     *
     * Nilai null MEMBENTUK SATU KELOMPOK TERSENDIRI dan TIDAK PERNAH DIBUANG:
     * mempertahankannya itulah yang membuat penjumlahan dispatch_count tiap
     * rekap TEPAT sama dengan dispatches.dispatch_count. Kuncinya null HARFIAH,
     * bukan string kosong dan bukan label karangan, supaya layar dapat
     * menyebutnya 'tidak tercatat' tanpa service mengarang nilai yang tidak
     * pernah dicatat.
     *
     * NILAI YANG TIDAK DIKENAL TIDAK DIBUANG DAN TIDAK DIPAKSA MASUK KE
     * KELOMPOK LAIN — berlaku khusus untuk `gate_status`, satu-satunya kolom
     * berpilihan tertutup di stasiun ini: KernelDispatchRecordService
     * menyimpannya tanpa daftar tertutup, jadi jalur selain form web dapat
     * menyimpan apa pun, dan nilai semacam itu mendapat barisnya sendiri. Sebuah
     * implementasi yang 'merapikan' dengan membuangnya akan menghilangkan baris
     * dan membuat penjumlahan rekap berhenti sama dengan angka di atasnya.
     *
     * TIDAK PERNAH DIPANGKAS, dan TIDAK ADA kelompok 'lainnya': nilai yang
     * muncul SEKALI tetap mendapat barisnya sendiri. Bila kelak sebuah kolom
     * berisi ratusan nilai berbeda karena diketik bebas, rekapnya menjadi
     * daftar panjang berjumlah satu-satu — gejala itu sengaja dibiarkan
     * TERLIHAT, karena ia tanda kolom tersebut perlu data master.
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

            // Kunci ember yang sengaja tidak ambigu: null tidak akan pernah
            // bertabrakan dengan string "null" yang diketik operator.
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
            // Total null diurutkan sebagai 0.0 UNTUK PENGURUTAN SAJA — di
            // payload ia tetap null, sebab 'belum pernah ditimbang' bukan
            // 'ditimbang nol'.
            $byWeight = ((float) ($b['net_weight_total'] ?? 0.0)) <=> ((float) ($a['net_weight_total'] ?? 0.0));

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byDispatches = $b['dispatch_count'] <=> $a['dispatch_count'];

            if ($byDispatches !== 0) {
                return $byDispatches;
            }

            // Pemutus seri terakhir yang deterministik. null jatuh paling
            // belakang HANYA di antara yang seri pada kedua kunci di atas —
            // bukan terakhir tanpa syarat, sehingga sekelompok pengiriman tanpa
            // nilai tercatat tetapi bertonase besar MUNCUL DI ATAS.
            return ((string) ($a['value'] ?? "\u{FFFF}")) <=> ((string) ($b['value'] ?? "\u{FFFF}"));
        });

        return array_map(fn (array $row) => $this->withWeightFigures($row), $rows);
    }

    /**
     * Rekap harian — SATU ENTRI PER HARI YANG PUNYA PENGIRIMAN, bukan satu
     * entri per hari kalender.
     *
     * Hari tanpa pengiriman sengaja TIDAK dibuatkan baris nol: laporan ini
     * per-transaksi, dan hari tanpa pengiriman adalah keadaan normal —
     * mencetak 22 baris nol akan mengubur sembilan baris yang berarti. (Berbeda
     * dari laporan bergrid, yang justru WAJIB memunculkan slot kosongnya.)
     *
     * Baris ber-event_date NULL TIDAK MASUK ke sini sama sekali — ia tidak dapat
     * ditempatkan pada hari mana pun — sehingga penjumlahan dispatch_count
     * daftar ini dapat KURANG dari dispatch_count, tepat sebesar
     * undated_dispatch_count.
     *
     * Tanggal di LUAR rentang periode (induknya di dalam) DIBIARKAN APA ADANYA
     * dan tetap mendapat entrinya: menyembunyikannya akan membuat total tidak
     * cocok dengan rincian hariannya.
     *
     * Urut tanggal NAIK, dinyatakan dengan ksort() — bukan diwarisi dari urutan
     * kueri, yang tidak dijanjikan PostgreSQL.
     *
     * @param  Collection<int, object>  $dispatches
     * @return list<array{date: string, dispatch_count: int, net_weight_total: float|null, net_weight_dispatch_count: int, net_weight_avg: float|null}>
     */
    protected function dailyOf(Collection $dispatches): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];

        foreach ($dispatches as $dispatch) {
            if ($dispatch->date === null) {
                continue;
            }

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
     * Kaki tabel rekap harian: DIJUMLAHKAN dari daily[], BUKAN disalin dari
     * blok `dispatches`.
     *
     * Keduanya memang dapat berbeda — tepatnya sebesar undated_dispatch_count —
     * dan perbedaan itu adalah INFORMASI. Menyalin akan menyembunyikannya, dan
     * pembaca yang melihat penjumlahan harian lebih kecil daripada totalnya
     * akan selalu mencurigainya sebagai bug; kecurigaan itu wajar, karena
     * selisih tanpa penjelasan tidak dapat dibedakan dari cacat.
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
     * Kelengkapan pencatatan — mengukur HARI, bukan kolom.
     *
     * days_counted datang dari App\Support\ReportPeriodDays::counted() APA
     * ADANYA, sama persis dengan ketujuh belas laporan sebelumnya: ia sudah
     * menangani periode berjalan (start..hari ini WIB) dan periode yang belum
     * mulai (0). Menghitung panjang periode secara lokal akan MENGHUKUM periode
     * berjalan atas hari yang belum datang — periode 31 hari pada hari ke-9
     * dengan pengiriman 9 hari harus memberi 100%, bukan 29%.
     *
     * percent NULL — bukan 0.0 — ketika days_counted 0 (periode belum mulai),
     * dan blade mencetak '—': nol persen berarti ada hari yang diharapkan dan
     * tak satu pun terisi, sedangkan '—' berarti belum ada hari yang diharapkan
     * sama sekali. Pembagian dengan nol tidak pernah terjadi karena penyebutnya
     * diperiksa SEBELUM pembagian.
     *
     * @param  list<array<string, mixed>>  $daily
     * @return array{days_in_period: int, days_with_dispatch: int, days_counted: int, percent: float|null}
     */
    protected function completenessOf(Period $period, array $daily): array
    {
        $daysCounted = ReportPeriodDays::counted($period);
        // COUNT DISTINCT tanggal yang punya minimal satu pengiriman. daily[]
        // punya tepat satu baris per tanggal semacam itu, jadi menghitungnya
        // ADALAH hitungan distinct-nya — satu definisi, satu jawaban.
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
     * Daftar kejadian bertanggal untuk kolom teks bebas `findings`.
     *
     * SATU DAFTAR, karena stasiun ini punya SATU kolom teks kejadian. Solid
     * Waste Disposal punya `remarks` juga dan karena itu dua daftar; menyalin
     * strukturnya ke sini akan menerbitkan kunci untuk kolom yang tidak ada.
     *
     * Teks bebas TIDAK disebar menjadi rekap kategoris — itu akan memperlakukan
     * prosa sebagai enum — dan isi yang berulang sama persis TIDAK
     * dikelompokkan. Entri kosong/whitespace dibuang (sudah menjadi null di
     * dispatchesOf()); daftar yang kosong tetap diterbitkan sebagai [] supaya
     * layar mencetak pernyataan 'tidak ada' alih-alih menghilangkan bagiannya.
     *
     * Pengiriman tanpa event_date tetap muncul dengan date null dan diurutkan
     * PALING BELAKANG: ia tetap sebuah catatan yang ditulis seseorang, dan
     * membuangnya akan menghilangkan prosa yang tidak ada di tempat lain mana
     * pun di layar ini.
     *
     * @param  Collection<int, object>  $dispatches
     * @return list<array{date: string|null, value: string}>
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

        usort($entries, fn (array $a, array $b) => ((string) ($a['date'] ?? "\u{FFFF}"))
            <=> ((string) ($b['date'] ?? "\u{FFFF}")));

        return $entries;
    }

    // ------------------------------------------------------------------
    // Aliran ekspor
    // ------------------------------------------------------------------

    /**
     * Separuh malas buildExportRows(): satu array per PENGIRIMAN, ditarik
     * berpotongan supaya periode panjang tidak pernah menjadi satu koleksi.
     *
     * Keempat nilai konteks (periode / rentang / mill / production line)
     * dihitung SEKALI oleh pemanggil dan diulang VERBATIM di setiap baris,
     * bersama tanggal dan status RECORD-nya — enam kolom konteks, sesuai
     * EXPORT_CONTEXT_COLUMN_COUNT.
     *
     * DUA KOLOM YANG DICETAK LEWAT KOSAKATA BERSAMA, DAN MASING-MASING LEWAT
     * FUNGSI YANG BERBEDA — pertukaran keduanya adalah cacat yang sudah pernah
     * terjadi di proyek ini:
     *   - STATUS RECORD lewat ExportValue::status(), karena ia status record.
     *   - `remarks_gate_status` lewat ExportValue::option(), karena ia kolom
     *     ENUM PENGUKURAN. Ekspor rincian mentah tetap memakai SATU kosakata
     *     dengan layar Detail dan layar laporan — dua label untuk satu nilai
     *     adalah dua kosakata — dan ExportValue::option() memperolehnya dengan
     *     MENDELEGASIKAN ke Display::option() lalu mengubah '-' menjadi null.
     *
     *     DIKOREKSI 2026-10-18: versi pertama memakai Display::option() di
     *     sini dengan alasan '-' harus tercetak untuk status yang tidak
     *     tercatat, sama seperti layar Detail. Alasan itu salah tempat. Di
     *     LAYAR, '-' adalah penanda yang dibaca manusia; di BERKAS EKSPOR, ia
     *     sebuah NILAI TEKS yang akan diurutkan, disaring dan dijumlahkan oleh
     *     pembacanya — sebuah sel '-' menjadi kategori palsu di tabel pivot,
     *     sementara sel KOSONG memang berarti kosong. Itulah sebabnya
     *     ExportValue::option() ada, dan itulah aturan rumah (keputusan pemilik
     *     produk 2026-10-14) yang menyatakan kolom enum pengukuran memakainya
     *     DI MANA PUN. Kedua preseden yang sudah ada — EffluentPlantReportService
     *     dan EngineRoomReportService — memakainya persis begitu; layar ini
     *     yang menyimpang, bukan aturannya.
     *
     *     Nilai di luar kedua pilihan yang dikenal tetap jatuh ke nilai
     *     MENTAH-nya, karena ExportValue::option() hanya membungkus
     *     Display::option() — perilaku itu tidak berubah.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $context
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $context): Generator
    {
        $query = (clone $recordQuery)
            ->with([
                'kernelDispatchDetails' => fn ($detailQuery) => $detailQuery
                    ->orderBy('event_date')
                    ->orderBy('id'),
            ])
            ->orderBy('kernel_dispatch_records.date')
            ->orderBy('kernel_dispatch_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var KernelDispatchRecord $record */
            $rowContext = array_merge($context, [
                optional($record->date)->toDateString(),
                // Status RECORD, label Indonesia — ExportValue::status().
                ExportValue::status($this->recordStatusValue($record)),
            ]);

            foreach ($record->kernelDispatchDetails as $detail) {
                /** @var KernelDispatchDetail $detail */
                $values = [];

                // DUA PULUH SATU kolom, urutan DETAIL_EXPORT_FIELDS.
                // Definisinya dibaca dari konstanta, tidak ditulis ulang di
                // sini, supaya daftarnya dapat diasersikan.
                foreach (self::DETAIL_EXPORT_FIELDS as $field) {
                    $value = $detail->{$field};

                    if ($field === 'event_date') {
                        $value = $value === null ? null : $this->dateStringOf($value);
                    }

                    if ($field === 'remarks_gate_status') {
                        // KOSAKATA BERSAMA, bukan salinan pemetaan. Lihat
                        // docblock metode ini — khususnya mengapa ini
                        // ExportValue::option() dan bukan Display::option():
                        // di berkas ekspor sebuah sel '-' menjadi kategori
                        // palsu, sementara sel kosong memang berarti kosong.
                        $value = ExportValue::option($value);
                    }

                    // Netto dan keempat kolom mutu yang NULL tetap NULL → sel
                    // KOSONG. Bukan 0, dan barisnya tidak dibuang.
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
     * OPERATOR DITERIMA SEJAK COMMIT PERTAMA — screen-165 (laporan Kernel
     * Dispatch mobile) dibangun dalam seri yang sama dan merupakan pemanggil
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
            // screen-165 (mobile). Lihat resolveBusinessUnit(), yang WAJIB
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

    protected function recordStatusValue(KernelDispatchRecord $record): string
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
                "laporan-kernel-dispatch_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-kernel-dispatch_{$slug}_{$timestamp}.csv",
        ];
    }
}
