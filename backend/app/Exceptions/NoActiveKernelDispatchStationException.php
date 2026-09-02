<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NoActiveKernelDispatchStationException — thrown by
 * KernelDispatchRecordService::create() when the selected
 * production_line_id has no active Station of type=kernel-dispatch to
 * attach the new record to (screen-113--form-kernel-dispatch-web,
 * business_logic → 422 NO_ACTIVE_KERNEL_DISPATCH_STATION).
 *
 * Mirrors NoActiveSolidWasteDisposalStationException (screen-111) exactly —
 * a plain HttpException for a single, non-field-keyed condition, not a
 * Laravel validation ruleset failure.
 */
class NoActiveKernelDispatchStationException extends HttpException
{
    public function __construct(string $message = 'Production Line yang dipilih belum memiliki station Kernel Dispatch yang aktif.')
    {
        parent::__construct(422, $message);
    }
}
