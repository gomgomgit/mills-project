<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * InvalidDateRangeException — thrown by WeighbridgeRecordService when
 * `date_from` is later than `date_to` (screen-016--data-browser-weighbridge-web,
 * business_logic step 1 → 422 INVALID_DATE_RANGE). Applies to both the list
 * endpoint (GET /api/weighbridge-records) and the export endpoint
 * (GET /api/weighbridge-records/export), since both share the same filter
 * validation step.
 *
 * Deliberately a plain HttpException (same pattern as
 * OldPasswordIncorrectException / PasswordConfirmationMismatchException)
 * rather than Illuminate\Validation\ValidationException — this is a single,
 * non-field-keyed condition, not a Laravel validation ruleset failure, and
 * ApiExceptionHandler's HttpExceptionInterface branch already renders it
 * correctly as { "message": ... } with no `errors` key.
 */
class InvalidDateRangeException extends HttpException
{
    public function __construct(string $message = 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
    {
        parent::__construct(422, $message);
    }
}
