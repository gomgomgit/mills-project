<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShiftScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ShiftScheduleController — permukaan API screen-171--jadwal-shift /
 * usecase-177--jadwal-shift (Jadwal Shift: mengisi sel shift × tanggal
 * dengan orang, per Production Line, per bulan):
 *
 *   GET  /api/shift-schedules/business-units/options    businessUnitOptions()
 *   GET  /api/shift-schedules/production-lines/options  productionLineOptions()
 *   GET  /api/shift-schedules/candidates                candidates()
 *   PUT  /api/shift-schedules/cell                      saveCell()
 *   POST /api/shift-schedules/apply                     apply()
 *   POST /api/shift-schedules/copy-previous             copyPrevious()
 *   GET  /api/shift-schedules                           grid()
 *
 * TIDAK ADA RUTE BER-{id}, tetapi urutan pendaftaran tetap bagian dari
 * kontrak: keenam rute literal didaftarkan SEBELUM GET /shift-schedules
 * (lihat routes/api.php) supaya tidak tertelan pola lain di masa depan.
 *
 * SELURUH validasi dan logika bisnis didelegasikan ke ShiftScheduleService
 * — service yang SAMA dengan yang dipakai komponen Livewire
 * (App\Livewire\MasterData\JadwalShift). Bentuknya mengikuti
 * ShiftController (screen-170).
 *
 * Gate: 'auth:web' + 'role:admin,supervisor,mill_management' di lapisan
 * rute; operator → 403. Lingkup mill Supervisor / Mill Management
 * ditegakkan di service, bukan hanya di UI. Galat dirender
 * ApiExceptionHandler dari exception yang dilempar service.
 */
class ShiftScheduleController extends Controller
{
    public function __construct(protected ShiftScheduleService $service) {}

    /**
     * businessUnitOptions() — GET /api/shift-schedules/business-units/options.
     * { data: [{id, name}], fixed }. RUTE LITERAL.
     */
    public function businessUnitOptions(): JsonResponse
    {
        return response()->json($this->service->businessUnitOptions());
    }

    /**
     * productionLineOptions() — GET /api/shift-schedules/production-lines/options
     * ?business_unit_id=…. RUTE LITERAL.
     */
    public function productionLineOptions(Request $request): JsonResponse
    {
        $businessUnitId = $request->query('business_unit_id');

        return response()->json($this->service->productionLineOptions(
            $businessUnitId !== null ? (string) $businessUnitId : null
        ));
    }

    /**
     * candidates() — GET /api/shift-schedules/candidates
     * ?production_line_id=…&q=…&date=…. RUTE LITERAL.
     */
    public function candidates(Request $request): JsonResponse
    {
        $lineId = $request->query('production_line_id');
        $q = $request->query('q');
        $date = $request->query('date');

        return response()->json($this->service->candidates(
            $lineId !== null ? (string) $lineId : null,
            $q !== null ? (string) $q : null,
            $date !== null ? (string) $date : null
        ));
    }

    /**
     * saveCell() — PUT /api/shift-schedules/cell. Mengganti isi SATU sel;
     * daftar kosong mengosongkannya. RUTE LITERAL.
     */
    public function saveCell(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->saveCell($request->only(['shift_id', 'date', 'user_ids', 'loaded_user_ids'])),
        ]);
    }

    /**
     * apply() — POST /api/shift-schedules/apply. dry_run=true hanya
     * menghitung; menimpa sel berisi wajib confirm_overwrite=true.
     * RUTE LITERAL.
     */
    public function apply(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->apply($request->only([
                'shift_id', 'month', 'dates', 'range', 'user_ids', 'dry_run', 'confirm_overwrite',
            ])),
        ]);
    }

    /**
     * copyPrevious() — POST /api/shift-schedules/copy-previous. Sel tujuan
     * yang sudah berisi dipertahankan. RUTE LITERAL.
     */
    public function copyPrevious(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->copyPrevious($request->only(['production_line_id', 'month', 'dry_run'])),
        ]);
    }

    /**
     * grid() — GET /api/shift-schedules?production_line_id=…&month=YYYY-MM.
     * Didaftarkan SESUDAH seluruh rute literal di atas.
     */
    public function grid(Request $request): JsonResponse
    {
        $lineId = $request->query('production_line_id');
        $month = $request->query('month');

        return response()->json([
            'data' => $this->service->grid(
                $lineId !== null ? (string) $lineId : null,
                $month !== null ? (string) $month : null
            ),
        ]);
    }
}
