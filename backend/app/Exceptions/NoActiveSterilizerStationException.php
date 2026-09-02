<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveSterilizerStationException — thrown by
 * SterilizerRecordService::create() when the selected production_line_id
 * has no active Station of type=sterilizer to attach the new record to
 * (screen-126--form-sterilizer-web, business_logic → 422
 * NO_ACTIVE_STERILIZER_STATION).
 *
 * Mirrors NoActiveCpoDispatchStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveSterilizerStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Sterilizer yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
