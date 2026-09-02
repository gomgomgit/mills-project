<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveProcessWaterStationException — thrown by ProcessWaterRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=process-water to attach the new record to (screen-112--form-process-water-web,
 * business_logic step 2 → 422 NO_ACTIVE_PROCESS_WATER_STATION).
 *
 * Mirrors NoActiveThreshingStationException exactly — a plain HttpException
 * for a single, non-field-keyed condition, not a Laravel validation ruleset
 * failure.
 */
class NoActiveProcessWaterStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Process Water yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
