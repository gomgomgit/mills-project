<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveStorageTankStationException — thrown by StorageTankRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=storage-tank to attach the new record to (screen-116--form-storage-tank-web,
 * business_logic step 2 → 422 NO_ACTIVE_STORAGE_TANK_STATION).
 *
 * Mirrors NoActiveEffluentPlantStationException exactly — a plain
 * HttpException for a single, non-field-keyed condition, not a Laravel
 * validation ruleset failure.
 */
class NoActiveStorageTankStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Storage Tank yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
