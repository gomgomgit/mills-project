<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveCpoDispatchStationException — thrown by
 * CpoDispatchRecordService::create() when the selected
 * production_line_id has no active Station of type=cpo-dispatch to
 * attach the new record to (screen-114--form-cpo-dispatch-web,
 * business_logic → 422 NO_ACTIVE_CPO_DISPATCH_STATION).
 *
 * Mirrors NoActiveKernelDispatchStationException (screen-113) exactly —
 * a plain HttpException for a single, non-field-keyed condition, not a
 * Laravel validation ruleset failure.
 */
class NoActiveCpoDispatchStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station CPO Dispatch yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
