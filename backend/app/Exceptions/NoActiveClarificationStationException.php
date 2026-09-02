<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveClarificationStationException — thrown by ClarificationRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=clarification to attach the new record to (screen-119--form-clarification-web,
 * business_logic step 2 → 422 NO_ACTIVE_CLARIFICATION_STATION).
 *
 * Mirrors NoActiveBoilerRoomStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveClarificationStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Clarification yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
