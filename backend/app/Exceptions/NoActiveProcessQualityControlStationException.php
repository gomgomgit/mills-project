<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveProcessQualityControlStationException — thrown by ProcessQualityControlRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=process-quality-control to attach the new record to (screen-120--form-process-quality-control-web,
 * business_logic step 2 → 422 NO_ACTIVE_PROCESS_QUALITY_CONTROL_STATION).
 *
 * Mirrors NoActiveClarificationStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveProcessQualityControlStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Process Quality Control yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
