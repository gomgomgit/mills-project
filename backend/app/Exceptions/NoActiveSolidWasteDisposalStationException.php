<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveSolidWasteDisposalStationException — thrown by
 * SolidWasteDisposalRecordService::create() when the selected
 * production_line_id has no active Station of type=solid-waste-disposal to
 * attach the new record to (screen-111--form-solid-waste-disposal-web,
 * business_logic → 422 NO_ACTIVE_SOLID_WASTE_DISPOSAL_STATION).
 *
 * Mirrors NoActiveCagesTrackStationException (screen-024) exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveSolidWasteDisposalStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Solid Waste Disposal yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
