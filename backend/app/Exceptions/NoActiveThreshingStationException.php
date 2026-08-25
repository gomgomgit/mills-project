<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveThreshingStationException — thrown by ThreshingRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=threshing to attach the new record to (screen-057--form-threshing-web,
 * business_logic step 2 → 422 NO_ACTIVE_THRESHING_STATION).
 *
 * Mirrors NoActiveCagesTrackStationException exactly — a plain HttpException
 * for a single, non-field-keyed condition, not a Laravel validation ruleset
 * failure.
 */
class NoActiveThreshingStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Threshing yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
