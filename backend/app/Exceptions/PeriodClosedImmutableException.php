<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodClosedImmutableException — thrown by PeriodService::update() and
 * PeriodService::delete() when the target Period is already
 * status='closed' (screen-128--kelola-periode-pelaporan, usecase-128 →
 * 409 PERIOD_CLOSED_IMMUTABLE).
 *
 * Nothing is written when this is thrown: the guard runs BEFORE any
 * UPDATE/DELETE statement, so a closed period is bit-for-bit unchanged
 * after a rejected attempt.
 *
 * The message deliberately contains the literal phrase "buka kembali
 * periode terlebih dahulu" — the Admin's only way forward is the Reopen
 * action, and the screen's browser test asserts that wording is visible.
 */
class PeriodClosedImmutableException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Periode sudah ditutup. Buka kembali periode terlebih dahulu sebelum mengubah atau menghapusnya.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_CLOSED_IMMUTABLE';
    }
}
