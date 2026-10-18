<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KernelDispatchReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KernelDispatchReportController — screen-164--laporan-kernel-dispatch-web
 * + screen-165--laporan-kernel-dispatch-mobile.
 *
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin /
 * Operator (lihat middleware 'role:supervisor,mill_management,admin,operator'
 * pada prefix /api/kernel-dispatch-reports di routes/api.php).
 *
 * GET-ONLY SECARA DESAIN. Tidak ada satu pun aksi POST/PUT/PATCH/DELETE di sini
 * maupun pada prefix itu — layar ini laporan baca-saja dan tidak boleh
 * menawarkan satu jalur pun yang dapat mengubah data Kernel Dispatch.
 *
 * Seluruh resolusi / agregasi / ekspor hidup di KernelDispatchReportService,
 * dipakai bersama komponen Livewire (App\Livewire\Dashboard\
 * LaporanKernelDispatch), sehingga halaman web dan API tidak pernah dapat
 * menyebut angka yang berbeda.
 *
 * ================================================================
 * LAPORAN HIBRIDA PERTAMA: HITUNGAN TRANSAKSI **DAN** MUTU
 * ================================================================
 * Satu baris `kernel_dispatch_details` = SATU PENGIRIMAN: satu kendaraan yang
 * berangkat membawa inti ke pembeli. `dispatches.dispatch_count` karena itu
 * menghitung BARIS DETAIL (bukan record induk), dan `daily[]` memuat satu entri
 * per HARI BERPENGIRIMAN — hari tanpa pengiriman sengaja tidak dibuatkan baris
 * nol. Sejauh itu ia mengikuti screen-162 (Solid Waste Disposal).
 *
 * YANG BARU: blok `quality` membawa EMPAT UKURAN min/avg/max — kadar air inti,
 * kadar kotoran, ALB, kadar inti pecah — dan MASING-MASING MENERBITKAN
 * `measured_count`-NYA SENDIRI. Keempat penyebut itu INDEPENDEN dan boleh
 * berbeda satu dengan lainnya serta berbeda dari dispatch_count, karena satu
 * pengiriman boleh punya sebagian hasil uji saja. Satu penyebut tunggal untuk
 * keempatnya akan tampak rapi dan salah secara sistematis; `quality._note`
 * menyatakan aturan itu di dalam payload supaya pembaca tidak menyimpulkan ada
 * data yang hilang. Penjaga 'tidak ada data' pun PER UKURAN: measured_count 0
 * pada satu ukuran mengosongkan ukuran ITU saja.
 *
 * OPERATOR DITERIMA PADA KETIGA RUTE DATA SEJAK COMMIT PERTAMA, preseden
 * Grading / Kernel Plant / Engine Room / Process Water / Solid Waste Disposal —
 * bukan preseden Weighbridge / Effluent Plant, tempat layar web mendarat lebih
 * dulu dan pelebaran akses harus dikerjakan sebagai tindak lanjut. Di sini
 * screen-165--laporan-kernel-dispatch-mobile dibangun dalam seri YANG SAMA,
 * jadi menerima perannya sekarang menghindarkan riak yang mahal.
 *
 * ADMISINYA SATU PERUBAHAN DI TIGA TEMPAT, BUKAN TIGA PERUBAHAN: daftar peran
 * di routes/api.php, KernelDispatchReportService::guardAccess(), DAN cabang
 * TERIKAT-MILL di KernelDispatchReportService::resolveBusinessUnit().
 * Mengerjakan dua yang pertama saja akan menjatuhkan Operator ke cabang Admin,
 * tempat business_unit_id kiriman klien JUSTRU dihormati — Operator lalu dapat
 * membaca laporan mill mana pun. Itu kebocoran lintas mill, bukan cacat
 * tampilan.
 *
 * YANG TETAP TERTUTUP: /business-units/options menolak siapa pun selain Admin
 * dengan 403 — Operator, Supervisor dan Mill Management sama-sama — diangkat DI
 * DALAM service dan bukan oleh middleware ini, PERSIS karena daftar peran itu
 * menerima keempat peran demi ketiga rute data, sehingga penjaga yang
 * ditempatkan di middleware tidak akan menutup rute itu sama sekali. Rute WEB
 * /reports/kernel-dispatch juga TIDAK dilebarkan.
 *
 * PERHATIKAN PENJAGA MANA MENUTUP LUBANG MANA — tiga jawaban berbeda dengan
 * sengaja:
 *   - `business_unit_id` kiriman klien DIABAIKAN untuk peran terikat mill,
 *     Operator termasuk (200 berisi data mill akunnya, sengaja BUKAN 403 — 403
 *     akan memastikan mill lain itu ada);
 *   - `production_line_id` milik mill lain DITOLAK 403 di
 *     resolveProductionLine(), sebelum satu kueri record pun berjalan, karena
 *     line adalah parameter yang pengguna PILIH secara sadar;
 *   - `period_id` milik mill lain DITOLAK 403 di authorizePeriod(), SESUDAH
 *     penjaga line, sehingga pemanggil tak berhak tidak pernah mengetahui id
 *     periode mana yang ada.
 *
 * DUA PARAMETER WAJIB pada /summary DAN /export: `period_id` DAN
 * `production_line_id`. Satu yang absen adalah 422 VALIDATION_ERROR dan NOL
 * kueri kernel_dispatch_records berjalan. TIDAK ADA fallback semua-line — total
 * yang mencampur belasan production line bukan angka yang dapat ditindaklanjuti
 * siapa pun, dan itu kebocoran cakupan yang sudah tiga kali terjadi di proyek
 * ini.
 *
 * /summary TIDAK membawa satu pun kunci `hourly`, `busiest_hour`,
 * `empty_hour_count`, `turnaround*`, `time_in`, `time_out` maupun `shift`, dan
 * setiap ketiadaan itu disengaja dengan alasannya masing-masing: baris detail
 * stasiun ini hanya punya `event_date` tanpa satu pun kolom waktu, dan kolom
 * `shift`-nya teks bebas. Payload membawa `_note_no_hourly`,
 * `_note_no_quality_thresholds` dan `_note_shift_not_published` yang menyatakan
 * ketiadaan itu UTUH, supaya tak satu pun terbaca sebagai bagian yang gagal
 * dimuat. Bandingkan CPO Dispatch (screen-166/167): tabel itu PUNYA
 * time_in/time_out, jadi di sana sebaran jam dan waktu putar kendaraan justru
 * wajib ada. /export tetap membawa DUA PULUH SATU kolom detail termasuk
 * `shift`, nomor tiket timbang, nomor surat jalan, nomor kendaraan, nama
 * pengemudi, kedua nomor segel dan kode operator timbang, karena ekspor adalah
 * rincian MENTAH.
 *
 * `by_gate_status[].value` MENERBITKAN KODE MENTAH ('released' /
 * 'not_released' / nilai lain / null). Labelnya diturunkan di lapisan tampilan
 * lewat App\Support\Display::option(); menerbitkan label di payload akan
 * memindahkan kosakata ke lapisan yang salah dan membuat pasangan mobile
 * (screen-165) menerima label yang tidak dapat ia ubah.
 *
 * Daftar OPSI production line TIDAK ada di sini: GET
 * /api/production-lines/options-for-report (dibuat untuk screen-135) dipakai
 * ulang verbatim. Ia bukan endpoint baru dan tidak diduplikasi di prefix ini.
 */
class KernelDispatchReportController extends Controller
{
    public function __construct(protected KernelDispatchReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/kernel-dispatch-reports/business-units/options.
     *
     * Pemilih mill khusus Admin; service melempar 403 FORBIDDEN untuk siapa pun
     * selainnya, sebab Operator, Supervisor dan Mill Management masing-masing
     * sudah terikat satu mill dan tidak punya pemilih sama sekali. Ditegakkan di
     * SERVICE, bukan oleh middleware rute ini — MIDDLEWARE PREFIX INI MENERIMA
     * KEEMPAT PERAN demi ketiga rute data, jadi penjaga di middleware tidak akan
     * menutup endpoint ini sama sekali.
     *
     * Master kosong adalah jawaban sah: { data: [] } dengan HTTP 200, bukan 404.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->businessUnitOptions(),
        ]);
    }

    /**
     * periods() — GET /api/kernel-dispatch-reports/periods?business_unit_id=...
     *
     * Mengembalikan { data: [] } dengan HTTP 200 ketika mill belum punya
     * periode yang mencakup Kernel Dispatch — pemilih kosong plus arahan ke
     * Kelola Periode Pelaporan, bukan 404. Admin yang tidak memilih mill
     * mendapat 422 VALIDATION_ERROR; demikian pula akun terikat yang
     * users.business_unit_id-nya NULL, dan pada keadaan itu daftar seluruh mill
     * tidak pernah dibaca sama sekali.
     *
     * Sebuah periode 'mencakup Kernel Dispatch' ketika ia punya baris
     * period_stations untuk station_type 'kernel-dispatch' — BERTANDA HUBUNG.
     * Service membaca nilai itu dari App\Enums\StationType alih-alih
     * menuliskannya, karena sebuah garis bawah di sana tidak akan cocok dengan
     * satu baris pun dan menjawab [] untuk setiap mill TANPA galat apa pun.
     *
     * STATUS PERIODE TIDAK PERNAH MENYARING daftar ini: periode tertutup
     * tercantum, dan tetap dapat dibaca serta diekspor sepenuhnya. Kunci periode
     * mengatur penulisan data, bukan pembacaan laporan.
     *
     * `production_line_id` sengaja BUKAN parameter di sini: periode milik MILL,
     * bukan milik line. Yang disaring sebuah line adalah DATANYA, bukan daftar
     * periodenya.
     */
    public function periods(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listPeriods(
                $this->queryString($request, 'business_unit_id'),
            ),
        ]);
    }

    /**
     * summary() — GET /api/kernel-dispatch-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic 1-23: resolusi mill (422), resolusi production line (422
     * ketika absen, 403 untuk line mill lain), otorisasi periode (404 / 403),
     * lalu seluruh angka periode itu pada line itu — blok `dispatches` dengan
     * PENYEBUTNYA, blok `quality` berisi EMPAT UKURAN dengan EMPAT PENYEBUT
     * TERPISAH, tiga rekap teks bebas apa adanya tanpa satu join pun,
     * by_gate_status berkode MENTAH, not_released_count yang ditonjolkan
     * tersendiri, TIGA penghitung kepatuhan, draft_dispatch_count,
     * undated_dispatch_count, rekap harian beserta kakinya, kelengkapan, dan
     * satu daftar teks bebas (`findings` — stasiun ini tidak punya `remarks`).
     *
     * EMPAT ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA, dan karena itu
     * semuanya diterbitkan sebagai kunci tersendiri:
     *   dispatch_count = net_weight_dispatch_count + missing_net_weight_dispatch_count;
     *   penjumlahan dispatch_count KEEMPAT rekap = dispatch_count (termasuk
     *   baris null dan termasuk nilai gate status yang tidak dikenal);
     *   penjumlahan daily[].dispatch_count = dispatch_count − undated_dispatch_count;
     *   dan keempat quality.*.measured_count BOLEH berbeda — itu bukan selisih
     *   yang perlu direkonsiliasi melainkan empat uji yang berdiri sendiri, dan
     *   quality._note mengatakannya.
     *
     * not_released_count MENGHITUNG HANYA 'not_released' PERSIS. Baris null ada
     * di by_gate_status tetapi TIDAK ikut angka itu: 'tidak tercatat' dan 'tidak
     * dilepas' adalah dua keadaan berbeda, dan menggabungkannya MENUDUH
     * pengiriman yang sekadar belum diisi statusnya.
     *
     * completeness.percent NULL — bukan 0.0 — ketika days_counted 0 (periode
     * belum mulai), dan layar mencetak '—': nol persen berarti ada hari yang
     * diharapkan dan tak satu pun terisi.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        // Urutannya disengaja: Admin tanpa mill adalah 422, dan production line
        // yang absen adalah 422, SEBELUM periodenya dicari — sehingga permintaan
        // yang belum lengkap tidak pernah berubah menjadi 404/403 tentang
        // periode yang ia tidak pernah berhak menanyakannya.
        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        return response()->json(
            $this->service->buildSummary($periodId, $businessUnitId, $productionLineId),
        );
    }

    /**
     * export() — GET /api/kernel-dispatch-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic 24: SATU BARIS PER PENGIRIMAN, dengan kolom konteks (nama
     * periode, rentang tanggalnya, nama mill, nama line, tanggal record, status
     * record) diulang VERBATIM di setiap baris supaya berkasnya dapat langsung
     * di-pivot di spreadsheet, disusul DUA PULUH SATU kolom detail. Label
     * kolomnya mengikuti layar Detail stasiun ini, bukan nama kolom database.
     *
     * Pengiriman ber-netto NULL tetap sebuah baris dengan sel KOSONG — tidak
     * pernah dibuang dan tidak pernah ditulis 0; demikian pula keempat kolom
     * mutu yang kosong.
     *
     * `remarks_gate_status` dicetak lewat App\Support\Display::option(), sama
     * dengan layar Detail dan layar laporan: ekspor rincian mentah tetap memakai
     * SATU kosakata, bukan dua. Nilai di luar kedua pilihan yang dikenal jatuh
     * ke nilai mentahnya.
     *
     * Bukan respons JSON. 422 VALIDATION_ERROR untuk format di luar {csv,xlsx},
     * dan 422 VALIDATION_ERROR ketika melampaui 50.000 baris — dihitung atas
     * BARIS PENGIRIMAN, bukan record, karena satu record induk membawa banyak
     * pengiriman.
     *
     * DEFAULT 'csv' DITETAPKAN DI SINI ATAS NILAI YANG SUDAH DI-TRIM, bukan atas
     * query mentah: queryString() memulangkan null untuk nilai yang kosong
     * setelah trim, sehingga format=' ' menjadi csv sementara format=' csv '
     * juga menjadi csv. Perbandingan terhadap daftar tertutupnya terjadi di
     * service, dan daftar itu PEKA HURUF — 'CSV' adalah 422, karena yang
     * dilakukan di sini hanya trim dan bukan lowercase.
     *
     * Penjaga peran, resolusi mill, resolusi line, pemeriksaan format dan
     * pemeriksaan batas baris SELURUHNYA berjalan EAGER di dalam service,
     * sebelum respons mulai dialirkan — galat setelah header terkirim mendarat
     * sebagai berkas rusak, bukan sebagai pesan.
     *
     * Periode TERTUTUP terekspor persis seperti periode terbuka.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessUnitId = $this->queryString($request, 'business_unit_id');

        $effectiveBusinessUnitId = $this->service->resolveBusinessUnit($businessUnitId);

        $productionLineId = $this->requireProductionLineId($request);

        $this->service->resolveProductionLine($effectiveBusinessUnitId, $productionLineId);

        $periodId = $this->requirePeriodId($request);

        $format = (string) ($this->queryString($request, 'format') ?? 'csv');

        return $this->service->export($periodId, $format, $businessUnitId, $productionLineId);
    }

    /**
     * `production_line_id` WAJIB pada summary dan export — yang absen adalah
     * 422 VALIDATION_ERROR dengan errors.production_line_id.
     *
     * IA TIDAK PERNAH DI-DEFAULT MENJADI 'SELURUH LINE MILL INI'. Total yang
     * mencampur belasan production line bukan angka yang dapat ditindaklanjuti
     * siapa pun, dan melebarkan cakupan laporan diam-diam hanya karena sebuah
     * parameter terlupa adalah yang terburuk dari keduanya: ia menjawab 200
     * berisi angka yang tidak diminta siapa pun. Penolakannya diangkat di sini
     * DAN lagi di dalam service, sehingga panggilan service langsung pun tidak
     * dapat melewatinya.
     *
     * @throws ValidationException
     */
    protected function requireProductionLineId(Request $request): string
    {
        $productionLineId = $this->queryString($request, 'production_line_id');

        if ($productionLineId === null) {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        return $productionLineId;
    }

    /**
     * `period_id` wajib pada summary maupun export — yang absen adalah 422
     * VALIDATION_ERROR dengan errors.period_id, bukan 404 untuk string kosong
     * dan bukan laporan kosong diam-diam.
     *
     * @throws ValidationException
     */
    protected function requirePeriodId(Request $request): string
    {
        $periodId = $this->queryString($request, 'period_id');

        if ($periodId === null) {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        return $periodId;
    }

    /** Nilai query yang sudah di-trim, atau null ketika absen/kosong. */
    protected function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
