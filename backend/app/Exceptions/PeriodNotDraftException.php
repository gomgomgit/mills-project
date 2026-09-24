<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodNotDraftException — thrown by PeriodClosureService::open() when
 * the target Period is not status='draft'
 * (screen-128--kelola-periode-pelaporan, usecase-144 → 409
 * PERIOD_NOT_DRAFT).
 *
 * Opening is only meaningful for a draft period: it is the one conscious
 * step that separates "masih disiapkan" from "sudah berjalan". Running it
 * on a period that is already open would be a no-op, and running it on a
 * closed period would silently undo a closure that has its own dedicated
 * action ("Buka Kembali Periode" / reopen(), which also clears
 * closed_by/closed_at) — so both are refused instead.
 *
 * The two refusals carry DIFFERENT messages on purpose: "Buka Periode"
 * and "Buka Kembali Periode" are easy to confuse, so a closed period's
 * message names the other action explicitly. The caller passes the message
 * in; the default below only covers a status that is neither open nor
 * closed (or a row no longer readable).
 */
class PeriodNotDraftException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Periode ini tidak berstatus Draft, sehingga tidak dapat dibuka.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_NOT_DRAFT';
    }
}
