<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodAlreadyClosedException — thrown by PeriodClosureService::close()
 * when the conditional UPDATE (WHERE id=? AND status <> 'closed') affects
 * 0 rows (screen-128--kelola-periode-pelaporan, usecase-140 → 409
 * PERIOD_ALREADY_CLOSED).
 *
 * That affected-rows check is the ENTIRE concurrency story for this
 * screen: when two Admins confirm the same closure at the same moment,
 * exactly one UPDATE matches and the loser lands here — so the first
 * closer's closed_by/closed_at are never overwritten, with no explicit
 * locking. See PeriodClosureService::close() for why read-then-write is
 * not used.
 *
 * The message names the Admin who actually closed the period and when,
 * because the loser of the race needs to know their action did not take
 * effect AND that the period is nevertheless closed.
 */
class PeriodAlreadyClosedException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Periode ini sudah ditutup.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_ALREADY_CLOSED';
    }
}
