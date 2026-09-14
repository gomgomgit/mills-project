<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecordVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

/**
 * RecordVerificationController — the mobile counterpart of the Detail
 * screens' approve/un-approve action (2026-09-14).
 *
 * ONE generic endpoint for all 18 stations rather than 18 near-identical
 * ones: the payload and the rule do not vary per station, only the target
 * table does, and that is resolved through
 * RecordVerificationService::modelForStationType()'s explicit whitelist
 * (never by building a class name out of the request).
 *
 * Deliberately NOT folded into the existing per-station PATCH
 * /{station}-records/{id} endpoints: those take a whole form payload and
 * run it through the station's normalisation/validation, so using them to
 * flip one attestation flag would risk rewriting record data. This one
 * writes only checked_by/acknowledged_by.
 *
 * Mobile calls this ONLINE ONLY. syncService.ts's queue is a one-way push
 * of locally-created records (status='saved' AND created_by = me) with no
 * outbox for other mutations, so a verification made while offline has no
 * path to reach the server later — the mobile UI therefore disables the
 * action when the request fails with a network error rather than silently
 * queueing it.
 */
class RecordVerificationController extends Controller
{
    public function __construct(protected RecordVerificationService $service) {}

    public function update(Request $request, string $stationType, string $id): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'string', 'in:checked,acknowledged'],
            'value' => ['required', 'boolean'],
        ]);

        $modelClass = $this->service->modelForStationType($stationType);

        if ($modelClass === null) {
            return response()->json(['message' => 'Jenis stasiun tidak dikenal.'], 404);
        }

        try {
            $this->service->setVerification(
                $modelClass,
                $id,
                $request->user(),
                $validated['level'],
                $validated['value'],
            );
        } catch (UnauthorizedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Record tidak ditemukan.'], 404);
        }

        $record = $modelClass::query()->with(['checkedBy:id,name', 'acknowledgedBy:id,name'])->findOrFail($id);

        return response()->json([
            'id' => $record->id,
            'checked_by' => $record->checked_by,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by' => $record->acknowledged_by,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
        ]);
    }
}
