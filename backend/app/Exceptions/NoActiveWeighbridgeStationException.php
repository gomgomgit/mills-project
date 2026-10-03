<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveWeighbridgeStationException — thrown by WeighbridgeRecordService::create()
 * when the selected production_line_id has no active Station of type=weighbridge
 * to attach the new record to (screen-022--form-weighbridge-web, business_logic
 * step 2 → 422 NO_ACTIVE_WEIGHBRIDGE_STATION). Since 2026-08-20 the station is
 * resolved from the Production Line, not the Business Unit — the Business Unit
 * dropdown only narrows the Production Line choices.
 *
 * Deliberately a plain HttpException (same pattern as InvalidDateRangeException/
 * ExportFailedException) — a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveWeighbridgeStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Weighbridge yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
