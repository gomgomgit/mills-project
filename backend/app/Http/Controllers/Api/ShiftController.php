<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShiftService;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ShiftController — permukaan API screen-170--kelola-shift /
 * usecase-176--kelola-shift (Kelola Shift per Production Line):
 *
 *   GET    /api/shifts/business-units/options     businessUnitOptions()
 *   GET    /api/shifts/production-lines/options   productionLineOptions()
 *   GET    /api/shifts                            index()
 *   POST   /api/shifts                            store()
 *   PATCH  /api/shifts/{id}                       update()
 *   DELETE /api/shifts/{id}                       destroy()
 *
 * URUTAN PENDAFTARAN RUTE ADALAH BAGIAN DARI KONTRAK, BUKAN GAYA. Kedua
 * rute LITERAL di atas wajib terdaftar SEBELUM /shifts/{id}; bila tidak,
 * Laravel mencocokkan "business-units" dan "production-lines" sebagai {id}
 * dan menjawab 404 untuk rute yang jelas-jelas ada. Jebakan ini sudah
 * pernah terjadi dan terdokumentasi di routes/api.php untuk
 * /periods/business-units/options — di sini ia diulang dengan sengaja
 * sebagai aturan, bukan ditemukan ulang.
 *
 * SELURUH validasi dan logika bisnis didelegasikan ke ShiftService —
 * service yang SAMA dengan yang dipakai komponen Livewire
 * (App\Livewire\MasterData\KelolaShift), sehingga halaman dan API tidak
 * mungkin berbeda hasil hitungan cakupannya. Bentuknya mengikuti
 * PeriodController.
 *
 * Seluruh aksi di-gate admin di lapisan rute (routes/api.php, 'auth:web' +
 * 'role:admin') per screen_tech_spec.actor_permissions — supervisor /
 * mill_management / operator semuanya can_access=false. ShiftService
 * menegakkannya SEKALI LAGI di dalam dirinya (lapis ketiga), sehingga
 * pemanggil mana pun yang melewati middleware tetap ditolak sebelum kueri
 * pertama berjalan.
 *
 * TIDAK ADA SATU PUN PARAMETER TANGGAL/HARI di berkas ini, dan itu
 * disengaja: jadwal shift berlaku sama setiap hari (keputusan pemilik
 * produk 2026-10-15).
 */
class ShiftController extends Controller
{
    public function __construct(protected ShiftService $service) {}

    /**
     * businessUnitOptions() — GET /api/shifts/business-units/options.
     * Daftar mill { id, name } tak berpaginasi untuk pemilih Mill. Admin
     * tidak terikat satu mill, jadi ia memilih.
     *
     * RUTE LITERAL — wajib terdaftar SEBELUM /shifts/{id}.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json(['data' => $this->service->businessUnitOptions()]);
    }

    /**
     * productionLineOptions() — GET /api/shifts/production-lines/options
     * ?business_unit_id=…. Line milik mill terpilih, untuk filter dan untuk
     * pemilih Production Line di dalam form. business_unit_id WAJIB (422
     * bila absen): tidak ada nilai yang dapat diturunkan dari akun Admin.
     *
     * RUTE LITERAL — wajib terdaftar SEBELUM /shifts/{id}.
     */
    public function productionLineOptions(Request $request): JsonResponse
    {
        $businessUnitId = $request->query('business_unit_id');

        return response()->json([
            'data' => $this->service->productionLineOptions(
                $businessUnitId !== null ? (string) $businessUnitId : null
            ),
        ]);
    }

    /**
     * index() — GET /api/shifts. Daftar shift satu mill, disaring opsional
     * pada satu Production Line, BESERTA ringkasan cakupan per line yang
     * dihitung SERVER.
     *
     * `data` berpaginasi; `coverage` TIDAK — ia merangkum cakupan line,
     * bukan mendaftar baris, dan panel yang ikut berpindah halaman akan
     * mengatakan hal berbeda tentang line yang sama tergantung halaman mana
     * yang terbuka.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max((int) $request->query('page', Pagination::DEFAULT_PAGE), 1);
        $perPage = Pagination::resolvePerPage($request);

        $businessUnitId = $request->query('business_unit_id');
        $productionLineId = $request->query('production_line_id');

        return response()->json($this->service->listShifts(
            $businessUnitId !== null ? (string) $businessUnitId : null,
            $productionLineId !== null ? (string) $productionLineId : null,
            $page,
            $perPage
        ));
    }

    /**
     * store() — POST /api/shifts. Validasi → 422 VALIDATION_ERROR (field
     * wajib, nama sudah dipakai pada line itu, jam berimpit, line tidak
     * ada) → pemeriksaan tumpang tindih pada line yang sama → 422
     * SHIFT_OVERLAP yang menyebut nama shift pembanding DAN rentang jam
     * tindihannya → INSERT.
     *
     * 200, bukan 201 — mengikuti expected_status test_scenarios screen-170.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->create($request->only(ShiftService::FIELDS)),
        ]);
    }

    /**
     * update() — PATCH /api/shifts/{id}. 404 bila shift tidak ada. PARSIAL:
     * field yang tidak dikirim mempertahankan nilai tersimpan. Shift yang
     * sedang diubah DIKECUALIKAN dari pembanding tumpang tindih, dan bila
     * production_line_id ikut berubah maka pemeriksaan dijalankan terhadap
     * line TUJUAN.
     *
     * Didaftarkan SESUDAH seluruh rute literal di atas.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->service->update($id, $request->only(ShiftService::FIELDS)),
        ]);
    }

    /**
     * destroy() — DELETE /api/shifts/{id}. 404 bila tidak ada, else DELETE.
     * Tidak ada penjagaan 'sudah dipakai record' pada putaran ini karena
     * belum ada tabel record yang merujuk shift — lihat open_question #1
     * business spec screen-170 dan ShiftService::delete().
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
