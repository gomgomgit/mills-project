<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveBoilerRoomStationException — thrown by BoilerRoomRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=boiler-room to attach the new record to (screen-118--form-boiler-room-web,
 * business_logic step 2 → 422 NO_ACTIVE_BOILER_ROOM_STATION).
 *
 * Mirrors NoActiveEngineRoomStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveBoilerRoomStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Boiler Room yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
