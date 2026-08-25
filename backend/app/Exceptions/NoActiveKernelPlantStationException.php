<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveKernelPlantStationException — thrown by
 * KernelPlantRecordService::create() when the selected production_line_id
 * has no active Station of type=kernel-plant to attach the new record to
 * (screen-060--form-kernel-plant-web, business_logic step 2 → 422
 * NO_ACTIVE_KERNEL_PLANT_STATION).
 *
 * Mirrors NoActiveDepricarpingStationException/NoActiveThreshingStationException
 * exactly — a plain HttpException for a single, non-field-keyed condition,
 * not a Laravel validation ruleset failure.
 */
class NoActiveKernelPlantStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Kernel Plant yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
