<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveEffluentPlantStationException — thrown by EffluentPlantRecordService::create()
 * when the selected production_line_id has no active Station of
 * type=effluent-plant to attach the new record to (screen-115--form-effluent-plant-web,
 * business_logic step 2 → 422 NO_ACTIVE_EFFLUENT_PLANT_STATION).
 *
 * Mirrors NoActiveThreshingStationException exactly — a plain HttpException
 * for a single, non-field-keyed condition, not a Laravel validation ruleset
 * failure.
 */
class NoActiveEffluentPlantStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Effluent Plant yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
