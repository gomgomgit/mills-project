<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveWeighbridgeStationException — thrown by WeighbridgeRecordService::create()
 * when the selected business_unit_id has no active Station of type=weighbridge
 * to attach the new record to (screen-022--form-weighbridge-web, business_logic
 * step 2 → 422 NO_ACTIVE_WEIGHBRIDGE_STATION).
 *
 * Deliberately a plain HttpException (same pattern as InvalidDateRangeException/
 * ExportFailedException) — a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveWeighbridgeStationException extends HttpException
{
    public function __construct(string $message = 'Business Unit yang dipilih belum memiliki station Weighbridge yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
