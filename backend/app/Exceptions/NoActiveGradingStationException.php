<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveGradingStationException — thrown by GradingRecordService::create()
 * when the selected business_unit_id has no active Station of type=grading
 * to attach the new record to (screen-023--form-grading-web, business_logic
 * step 2 → 422 NO_ACTIVE_GRADING_STATION).
 *
 * Mirrors NoActiveWeighbridgeStationException (screen-022) exactly — a
 * plain HttpException for a single, non-field-keyed condition, not a
 * Laravel validation ruleset failure.
 */
class NoActiveGradingStationException extends HttpException
{
    public function __construct(string $message = 'Business Unit yang dipilih belum memiliki station Grading yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
