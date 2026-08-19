<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CompanyHasBusinessUnitsException — thrown by CompanyService::delete()
 * when the company being deleted still has related BusinessUnit rows
 * (screen-028--kelola-company, usecase-028--kelola-company, business_logic
 * step 5 → 409 COMPANY_HAS_BUSINESS_UNITS delete-guard).
 *
 * Mirrors App\Exceptions\CorporateHasCompaniesException exactly: a plain
 * HttpException (not Illuminate\Validation\ValidationException) since this
 * is a single, non-field-keyed condition (a delete-guard, not a form
 * validation failure) — ApiExceptionHandler's HttpExceptionInterface
 * fallback branch already renders any status code generically as
 * { "message": ... } with no `errors` key, which covers 409 here.
 */
class CompanyHasBusinessUnitsException extends HttpException
{
    public function __construct(string $message = 'Company tidak dapat dihapus karena masih memiliki Business Unit terkait.')
    {
        parent::__construct(409, $message);
    }
}
