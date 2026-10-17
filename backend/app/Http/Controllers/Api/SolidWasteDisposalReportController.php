<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SolidWasteDisposalReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SolidWasteDisposalReportController — screen-162--laporan-solid-waste-disposal-web
 * + screen-163--laporan-solid-waste-disposal-mobile.
 *
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin /
 * Operator (lihat middleware 'role:supervisor,mill_management,admin,operator'
 * pada prefix /api/solid-waste-disposal-reports di routes/api.php).
 *
 * GET-ONLY SECARA DESAIN. Tidak ada satu pun aksi POST/PUT/PATCH/DELETE di sini
 * maupun pada prefix itu — layar ini laporan baca-saja dan tidak boleh
 * menawarkan satu jalur pun yang dapat mengubah data Solid Waste Disposal.
 *
 * Seluruh resolusi / agregasi / ekspor hidup di
 * SolidWasteDisposalReportService, dipakai bersama komponen Livewire
 * (App\Livewire\Dashboard\LaporanSolidWasteDisposal), sehingga halaman web dan
 * API tidak pernah dapat menyebut angka yang berbeda.
 *
 * ================================================================
 * LAPORAN PER-TRANSAKSI: SATU BARIS DETAIL = SATU MUATAN KELUAR
 * ================================================================
 * Bukan laporan bergrid 24 slot seperti screen-160 (Process Water). Tabel
 * `solid_waste_disposal_details` tidak punya READING_FIELDS: barisnya adalah
 * satu kendaraan limbah padat yang meninggalkan pabrik, berkunci `event_date`,
 * dan jumlah baris per hari sepenuhnya bebas. Presedennya screen-143
 * (Weighbridge) dan screen-146 (Grading). Konsekuensi yang paling terlihat di
 * payload: `loads.load_count` menghitung BARIS DETAIL (bukan record induk), dan
 * `daily[]` memuat satu entri per HARI BERMUATAN — hari tanpa muatan sengaja
 * tidak dibuatkan baris nol.
 *
 * OPERATOR DITERIMA PADA KETIGA RUTE DATA SEJAK COMMIT PERTAMA, preseden
 * Grading / Kernel Plant / Engine Room / Process Water — bukan preseden
 * Weighbridge / Effluent Plant, tempat layar web mendarat lebih dulu dan
 * pelebaran akses harus dikerjakan sebagai tindak lanjut. Di sini
 * screen-163--laporan-solid-waste-disposal-mobile dibangun dalam seri YANG
 * SAMA, jadi menerima perannya sekarang menghindarkan riak yang mahal.
 *
 * ADMISINYA SATU PERUBAHAN DI TIGA TEMPAT, BUKAN TIGA PERUBAHAN: daftar peran
 * di routes/api.php, SolidWasteDisposalReportService::guardAccess(), DAN cabang
 * TERIKAT-MILL di SolidWasteDisposalReportService::resolveBusinessUnit().
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
 * /reports/solid-waste-disposal juga TIDAK dilebarkan.
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
 * kueri solid_waste_disposal_records berjalan. TIDAK ADA fallback semua-line —
 * total yang mencampur belasan production line bukan angka yang dapat
 * ditindaklanjuti siapa pun, dan itu kebocoran cakupan yang sudah tiga kali
 * terjadi di proyek ini.
 *
 * /summary TIDAK membawa satu pun kunci `hourly`, `busiest_hour`,
 * `empty_hour_count` maupun `shift`, dan keempat ketiadaan itu disengaja dengan
 * alasannya masing-masing: baris detail stasiun ini hanya punya `event_date`
 * tanpa satu pun kolom waktu, dan kolom `shift`-nya teks bebas. Payload membawa
 * `_note_no_hourly`, `_note_shift_not_published` dan `_note_absences` yang
 * menyatakan ketiadaan itu UTUH, supaya tak satu pun terbaca sebagai bagian
 * yang gagal dimuat. /export tetap membawa KETUJUH BELAS kolom detail termasuk
 * `shift`, nomor gate pass, nomor segel dan id operator, karena ekspor adalah
 * rincian MENTAH.
 *
 * Daftar OPSI production line TIDAK ada di sini: GET
 * /api/production-lines/options-for-report (dibuat untuk screen-135) dipakai
 * ulang verbatim. Ia bukan endpoint baru dan tidak diduplikasi di prefix ini.
 */
class SolidWasteDisposalReportController extends Controller
{
    public function __construct(protected SolidWasteDisposalReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/solid-waste-disposal-reports/business-units/options.
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
     * periods() — GET /api/solid-waste-disposal-reports/periods?business_unit_id=...
     *
     * Mengembalikan { data: [] } dengan HTTP 200 ketika mill belum punya
     * periode yang mencakup Solid Waste Disposal — pemilih kosong plus arahan ke
     * Kelola Periode Pelaporan, bukan 404. Admin yang tidak memilih mill
     * mendapat 422 VALIDATION_ERROR; demikian pula akun terikat yang
     * users.business_unit_id-nya NULL, dan pada keadaan itu daftar seluruh mill
     * tidak pernah dibaca sama sekali.
     *
     * Sebuah periode 'mencakup Solid Waste Disposal' ketika ia punya baris
     * period_stations untuk station_type 'solid-waste-disposal' — BERTANDA
     * HUBUNG. Service membaca nilai itu dari App\Enums\StationType alih-alih
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
     * summary() — GET /api/solid-waste-disposal-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic 1-22: resolusi mill (422), resolusi production line (422
     * ketika absen, 403 untuk line mill lain), otorisasi periode (404 / 403),
     * lalu seluruh angka periode itu pada line itu — blok `loads` dengan
     * PENYEBUTNYA, keempat rekap kategoris apa adanya tanpa satu join pun,
     * kedua penghitung kepatuhan, draft_load_count, undated_load_count, rekap
     * harian beserta kakinya, kelengkapan, dan dua daftar teks bebas yang
     * terpisah.
     *
     * TIGA ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA, dan karena itu
     * ketiganya diterbitkan sebagai kunci tersendiri:
     * load_count = net_weight_load_count + missing_net_weight_load_count; dan
     * penjumlahan daily[].load_count = load_count − undated_load_count.
     * Selisih yang tak dijelaskan tidak dapat dibedakan dari cacat.
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
     * export() — GET /api/solid-waste-disposal-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic 23: SATU BARIS PER MUATAN, dengan kolom konteks (nama
     * periode, nama mill, nama line, tanggal record, status record) diulang
     * VERBATIM di setiap baris supaya berkasnya dapat langsung di-pivot di
     * spreadsheet, disusul KETUJUH BELAS kolom detail. Label kolomnya mengikuti
     * layar Detail stasiun ini, bukan nama kolom database.
     *
     * Muatan ber-netto NULL tetap sebuah baris dengan sel KOSONG — tidak pernah
     * dibuang dan tidak pernah ditulis 0.
     *
     * Bukan respons JSON. 422 VALIDATION_ERROR untuk format di luar {csv,xlsx},
     * dan 422 VALIDATION_ERROR ketika melampaui 50.000 baris — dihitung atas
     * BARIS MUATAN, bukan record, karena satu record induk membawa banyak
     * muatan.
     *
     * DEFAULT 'csv' DITETAPKAN DI SINI ATAS NILAI YANG SUDAH DI-TRIM, bukan atas
     * query mentah: queryString() memulangkan null untuk nilai yang kosong
     * setelah trim, sehingga format=' ' menjadi csv sementara format=' csv '
     * juga menjadi csv. Perbandingan terhadap daftar tertutupnya terjadi di
     * service.
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
