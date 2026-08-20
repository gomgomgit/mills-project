<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveCagesTrackStationException — thrown by
 * CagesTrackRecordService::create() when the selected business_unit_id has
 * no active Station of type=cages-track to attach the new record to
 * (screen-024--form-cages-track-web, business_logic step 2 → 422
 * NO_ACTIVE_CAGES_TRACK_STATION).
 *
 * Mirrors NoActiveWeighbridgeStationException (screen-022) and
 * NoActiveGradingStationException (screen-023) exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveCagesTrackStationException extends HttpException
{
    public function __construct(string $message = 'Business Unit yang dipilih belum memiliki station Cages Track yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
