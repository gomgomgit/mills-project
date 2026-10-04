<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * BusinessUnitHasStationsException — thrown by BusinessUnitService::delete()
 * when the business unit being deleted still has related Station rows
 * (screen-029--kelola-business-unit, usecase-029--kelola-business-unit,
 * business_logic step "delete" → 409 BUSINESS_UNIT_HAS_STATIONS
 * delete-guard).
 *
 * Mirrors App\Exceptions\CompanyHasBusinessUnitsException /
 * App\Exceptions\CorporateHasCompaniesException exactly: a plain
 * HttpException (not Illuminate\Validation\ValidationException) since
 * this is a single, non-field-keyed condition (a delete-guard, not a form
 * validation failure) — ApiExceptionHandler's HttpExceptionInterface
 * fallback branch already renders any status code generically as
 * { "message": ... } with no `errors` key, which covers 409 here.
 *
 * Sejak audit 2026-10-05 mengimplementasikan HasErrorCode sehingga respons
 * 409 membawa `code: BUSINESS_UNIT_HAS_STATIONS` (sebelumnya hanya `message`,
 * padahal spec menamai kode ini).
 */
class BusinessUnitHasStationsException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Business Unit tidak dapat dihapus karena masih memiliki Station terkait.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'BUSINESS_UNIT_HAS_STATIONS';
    }
}
