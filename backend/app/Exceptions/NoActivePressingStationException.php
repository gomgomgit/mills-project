<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActivePressingStationException — thrown by PressingRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=pressing to attach the new record to (screen-058--form-pressing-web,
 * business_logic step 2 → 422 NO_ACTIVE_PRESSING_STATION).
 *
 * Mirrors NoActiveThreshingStationException exactly — a plain HttpException
 * for a single, non-field-keyed condition, not a Laravel validation ruleset
 * failure.
 */
class NoActivePressingStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Pressing yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
