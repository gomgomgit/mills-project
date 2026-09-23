<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PeriodOverlapException — thrown by PeriodService::create()/update() when
 * the requested [start_date, end_date] range overlaps an existing Period
 * on the same scope (screen-128--kelola-periode-pelaporan,
 * usecase-128 → 422 PERIOD_OVERLAP).
 *
 * "Same scope" is (business_unit_id, station_type) with a station_type of
 * NULL meaning ALL station types in that mill — so a NULL-scoped period
 * clashes with every typed period in the mill and vice versa. Both
 * directions are caught by the single SQL clause in
 * PeriodService::findOverlapping().
 *
 * The message always names the conflicting period (the edge_case_handling
 * entry requires it: the Admin must be able to tell WHICH period is in the
 * way without leaving the form).
 *
 * Deliberately a plain HttpException — same pattern as
 * ExportFailedException — but implementing HasErrorCode so
 * ApiExceptionHandler emits `code: PERIOD_OVERLAP` alongside the message.
 * This is NOT a ValidationException: it is one non-field-keyed business
 * condition, not a per-field validation failure.
 */
class PeriodOverlapException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Rentang tanggal periode beririsan dengan periode lain pada mill dan jenis stasiun yang sama.')
    {
        parent::__construct(422, $message);
    }

    public function errorCode(): string
    {
        return 'PERIOD_OVERLAP';
    }
}
