<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GradingParameterService;
use Illuminate\Http\JsonResponse;

/**
 * GradingParameterController — GET /api/grading-parameters (audit
 * 2026-10-04). Dipakai mobile (gradingParameterSync.ts) untuk menyelaraskan
 * master Quality Parameter lokal dengan id server. Dijaga
 * 'auth:web,sanctum' + ke-4 peran — lihat blok rute di routes/api.php.
 */
class GradingParameterController extends Controller
{
    public function __construct(protected GradingParameterService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->listForMobile()]);
    }
}
