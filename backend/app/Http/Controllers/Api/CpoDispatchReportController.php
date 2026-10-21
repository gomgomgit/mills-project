<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CpoDispatchReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CpoDispatchReportController — screen-166--laporan-cpo-dispatch-web
 * + screen-167--laporan-cpo-dispatch-mobile.
 *
 * auth_requirement: authenticated, Supervisor / Mill Management / Admin /
 * Operator (middleware 'role:supervisor,mill_management,admin,operator' pada
 * prefix /api/cpo-dispatch-reports di routes/api.php).
 *
 * GET-ONLY SECARA DESAIN: tidak ada POST/PUT/PATCH/DELETE di sini maupun pada
 * prefix itu. Seluruh resolusi / agregasi / ekspor hidup di
 * CpoDispatchReportService, dipakai bersama komponen Livewire
 * (App\Livewire\Dashboard\LaporanCpoDispatch), sehingga halaman web dan API
 * tidak pernah dapat menyebut angka yang berbeda.
 *
 * Satu baris `cpo_dispatch_details` = SATU PENGIRIMAN (satu tanker). Bentuk
 * transaksinya sama dengan screen-164 (Kernel Dispatch); yang BARU: /summary
 * membawa `hourly` (sebaran menurut JAM MASUK, 24 kelompok) dan `turnaround`
 * (waktu putar tanker, dengan jam keluar yang lebih kecil dibaca sebagai hari
 * berikutnya dan jumlahnya diterbitkan di `crossed_midnight_count`), karena
 * hanya tabel ini yang punya time_in/time_out. /summary juga menerbitkan EMPAT
 * PENYEBUT yang berbeda (`_note_four_denominators`), dan TIDAK membawa rekap
 * berlabel apa pun (`_note_no_labeled_recap`) karena stasiun ini tidak punya
 * kolom berpilihan tertutup. Kolom `shift` tidak ada di /summary, hanya di
 * /export, yang membawa jam APA ADANYA tanpa penafsiran.
 *
 * OPERATOR DITERIMA PADA KETIGA RUTE DATA SEJAK COMMIT PERTAMA (screen-167
 * dibangun dalam seri yang sama). ADMISINYA SATU PERUBAHAN DI TIGA TEMPAT: daftar
 * peran di routes/api.php, CpoDispatchReportService::guardAccess(), DAN cabang
 * TERIKAT-MILL di CpoDispatchReportService::resolveBusinessUnit(). Mengerjakan dua
 * yang pertama saja akan menjatuhkan Operator ke cabang Admin, tempat
 * business_unit_id kiriman klien JUSTRU dihormati — kebocoran lintas mill.
 *
 * YANG TETAP TERTUTUP: /business-units/options menolak siapa pun selain Admin
 * dengan 403, diangkat DI DALAM service dan bukan oleh middleware, karena
 * middleware prefix ini menerima keempat peran demi ketiga rute data. Rute WEB
 * /reports/cpo-dispatch juga TIDAK dilebarkan.
 *
 * TIGA JAWABAN BERBEDA DENGAN SENGAJA:
 *   - `business_unit_id` kiriman klien DIABAIKAN untuk peran terikat mill
 *     (200 berisi data mill akunnya; 403 akan memastikan mill lain itu ada);
 *   - `production_line_id` milik mill lain DITOLAK 403, sebelum satu kueri
 *     record pun berjalan;
 *   - `period_id` milik mill lain DITOLAK 403, SESUDAH penjaga line.
 *
 * DUA PARAMETER WAJIB pada /summary DAN /export: `period_id` DAN
 * `production_line_id` (422 bila absen; tidak ada fallback semua-line).
 *
 * Daftar OPSI production line TIDAK ada di sini: GET
 * /api/production-lines/options-for-report dipakai ulang verbatim.
 */
class CpoDispatchReportController extends Controller
{
    public function __construct(protected CpoDispatchReportService $service) {}

    /**
     * businessUnitOptions() — GET /api/cpo-dispatch-reports/business-units/options.
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
     * periods() — GET /api/cpo-dispatch-reports/periods?business_unit_id=...
     *
     * Mengembalikan { data: [] } dengan HTTP 200 ketika mill belum punya
     * periode yang mencakup CPO Dispatch — pemilih kosong plus arahan ke
     * Kelola Periode Pelaporan, bukan 404. Admin yang tidak memilih mill
     * mendapat 422 VALIDATION_ERROR; demikian pula akun terikat yang
     * users.business_unit_id-nya NULL, dan pada keadaan itu daftar seluruh mill
     * tidak pernah dibaca sama sekali.
     *
     * Sebuah periode 'mencakup CPO Dispatch' ketika ia punya baris
     * period_stations untuk station_type 'cpo-dispatch' — BERTANDA HUBUNG.
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
     * summary() — GET /api/cpo-dispatch-reports/summary?period_id=...&production_line_id=...
     *
     * business_logic 1-23: resolusi mill (422), resolusi production line (422
     * ketika absen, 403 untuk line mill lain), otorisasi periode (404 / 403),
     * lalu seluruh angka periode itu pada line itu — blok `dispatches` dengan
     * PENYEBUTNYA, `quality` berisi EMPAT UKURAN (FFA, kadar air, kadar kotoran,
     * DOBI) dengan EMPAT PENYEBUT TERPISAH, `turnaround` (waktu putar tanker),
     * `hourly` (24 kelompok menurut jam masuk), tiga rekap teks bebas apa adanya
     * tanpa satu join pun, TIGA penghitung kepatuhan, draft_dispatch_count,
     * rekap harian beserta kakinya, kelengkapan, dan satu daftar teks bebas
     * (`findings` — stasiun ini tidak punya `remarks`).
     *
     * ANGKA YANG WAJIB DAPAT DIREKONSILIASI PEMBACA, diterbitkan sebagai kunci
     * tersendiri:
     *   dispatch_count = net_weight_dispatch_count + missing_net_weight_dispatch_count;
     *   penjumlahan dispatch_count tiap rekap = dispatch_count (baris null ikut);
     *   penjumlahan daily[].dispatch_count = dispatch_count;
     *   penjumlahan 24 kelompok hourly + hourly.not_recorded_count = dispatch_count;
     *   turnaround.measured_count + turnaround.uncomputable_count = dispatch_count.
     * Keempat penyebut (dispatch_count, net_weight_dispatch_count,
     * hourly.recorded_count, turnaround.measured_count) dan keempat
     * quality.*.measured_count BOLEH berbeda — itu bukan selisih yang perlu
     * direkonsiliasi melainkan kolom yang terisi saling bebas, dan
     * `_note_four_denominators` serta `quality._note` mengatakannya.
     *
     * Jam keluar yang LEBIH KECIL daripada jam masuk dibaca sebagai hari
     * berikutnya (23:30 → 00:45 = 75 menit) dan jumlahnya diterbitkan di
     * turnaround.crossed_midnight_count; penafsiran itu tidak menggeser kelompok
     * jam. Jam yang sama = nol menit, bukan menyeberang tengah malam.
     *
     * completeness.percent NULL — bukan 0.0 — ketika days_counted 0 (periode
     * belum mulai), dan layar mencetak '—'.
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
     * export() — GET /api/cpo-dispatch-reports/export?period_id=...&production_line_id=...&format=csv
     *
     * business_logic 24: SATU BARIS PER PENGIRIMAN, dengan kolom konteks (nama
     * periode, rentang tanggalnya, nama mill, nama line, tanggal record, status
     * record) diulang VERBATIM di setiap baris supaya berkasnya dapat langsung
     * di-pivot di spreadsheet, disusul DUA PULUH SATU kolom detail. Label
     * kolomnya mengikuti layar Detail stasiun ini, bukan nama kolom database.
     *
     * Pengiriman ber-netto NULL tetap sebuah baris dengan sel KOSONG — tidak
     * pernah dibuang dan tidak pernah ditulis 0; demikian pula kolom mutu dan
     * jam yang kosong. JAM MASUK DAN JAM KELUAR DIEKSPOR APA ADANYA: tanpa
     * penafsiran menyeberang tengah malam dan tanpa kolom waktu putar.
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
