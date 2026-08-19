<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use Illuminate\Http\JsonResponse;

/**
 * BusinessUnitController — GET /api/business-units.
 *
 * Fills a known gap flagged since screen-002--login-mobile: both the web
 * (screen-001) and mobile (screen-002) login forms render a "Business
 * Area" dropdown, but no screen tech-spec ever defined the endpoint that
 * populates it — this was the pre-login blocker documented in
 * screen-002's implementation known_issues. Public (no auth_required),
 * consistent with POST /api/login: the picker must be usable before the
 * user has any session/token. Server-side login validation (business area
 * mismatch check in AuthService::login()) is the actual authorization
 * boundary — this endpoint just lists all business units for the picker,
 * it does not gate access.
 */
class BusinessUnitController extends Controller
{
    public function index(): JsonResponse
    {
        $businessUnits = BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $businessUnits]);
    }
}
