<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CorporateHasCompaniesException — thrown by CorporateService::delete()
 * when the corporate being deleted still has related Company rows
 * (screen-027--kelola-corporate, usecase-027--kelola-corporate,
 * business_logic step 4 → 409 CORPORATE_HAS_COMPANIES delete-guard).
 *
 * Deliberately a plain HttpException (same pattern as
 * InvalidDateRangeException / ExportFailedException) rather than
 * Illuminate\Validation\ValidationException — this is a single,
 * non-field-keyed condition (a delete-guard, not a form validation
 * failure), and ApiExceptionHandler's HttpExceptionInterface branch
 * already renders any status code generically as { "message": ... } with
 * no `errors` key (409 here — not one of ApiExceptionHandler's specially
 * handled types, but that fallback branch covers it).
 */
class CorporateHasCompaniesException extends HttpException
{
    public function __construct(string $message = 'Corporate tidak dapat dihapus karena masih memiliki Company terkait.')
    {
        parent::__construct(409, $message);
    }
}
