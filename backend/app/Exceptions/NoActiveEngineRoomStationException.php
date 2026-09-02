<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveEngineRoomStationException — thrown by EngineRoomRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=engine-room to attach the new record to (screen-117--form-engine-room-web,
 * business_logic step 2 → 422 NO_ACTIVE_ENGINE_ROOM_STATION).
 *
 * Mirrors NoActiveStorageTankStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveEngineRoomStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Engine Room yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
