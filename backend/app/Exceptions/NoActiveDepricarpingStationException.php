<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveDepricarpingStationException — thrown by
 * DepricarpingRecordService::create() when the selected production_line_id
 * has no active Station of type=depricarping to attach the new record to
 * (screen-059--form-depricarping-web, business_logic step 2 → 422
 * NO_ACTIVE_DEPRICARPING_STATION).
 *
 * Mirrors NoActivePressingStationException/NoActiveThreshingStationException
 * exactly — a plain HttpException for a single, non-field-keyed condition,
 * not a Laravel validation ruleset failure.
 */
class NoActiveDepricarpingStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Depricarping yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
