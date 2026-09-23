<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodNotClosedException — thrown by PeriodClosureService::reopen() when
 * the target Period is not status='closed'
 * (screen-128--kelola-periode-pelaporan, usecase-140 → 409
 * PERIOD_NOT_CLOSED).
 *
 * Reopening is only meaningful for a closed period: it clears closed_by /
 * closed_at and moves the status to 'open'. Running it on a draft/open
 * period would silently promote a draft to open and blank two columns that
 * are already null, so it is refused instead.
 *
 * NOTE (tech-spec implementation_notes): this code has no Phase 2
 * bdd_scenario mapped to it — it is covered by unit tests only.
 */
class PeriodNotClosedException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Periode ini belum ditutup, sehingga tidak dapat dibuka kembali.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_NOT_CLOSED';
    }
}
