<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecordVerificationStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RecordVerificationStatusController — GET
 * /api/records/{stationType}/verification?ids[]=… (audit 2026-10-04).
 *
 * Endpoint baca status verifikasi untuk mobile; GET /api/<station>-records/{id}
 * yang ada hanya 'auth:web' tanpa operator, jadi tidak terjangkau token
 * Sanctum. Lihat RecordVerificationStatusService untuk aturan cakupannya.
 */
class RecordVerificationStatusController extends Controller
{
    /** Batas jumlah id per request — mobile mengirim paling banyak 100. */
    public const MAX_IDS = 200;

    public function __construct(protected RecordVerificationStatusService $service) {}

    public function index(Request $request, string $stationType): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['required', 'string', 'uuid'],
        ], [
            'ids.required' => 'Daftar id record wajib diisi.',
            'ids.array' => 'Daftar id record harus berupa array.',
            'ids.max' => 'Paling banyak '.self::MAX_IDS.' id record per permintaan.',
            'ids.*.required' => 'Id record tidak boleh kosong.',
            'ids.*.string' => 'Id record harus berupa UUID.',
            'ids.*.uuid' => 'Id record harus berupa UUID.',
        ]);

        $modelClass = $this->service->modelForStationType($stationType);

        // abort() → ApiExceptionHandler: { message, code: NOT_FOUND }, amplop
        // standar seperti endpoint lain (temuan audit 2026-10-05 #11).
        if ($modelClass === null) {
            abort(404, 'Jenis stasiun tidak dikenal.');
        }

        return response()->json([
            'data' => $this->service->statusesFor($modelClass, $validated['ids']),
        ]);
    }
}
