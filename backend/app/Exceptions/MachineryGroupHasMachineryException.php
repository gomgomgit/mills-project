<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * MachineryGroupHasMachineryException — thrown by
 * MachineryGroupService::delete() when the machinery group being deleted
 * still has related Machinery rows (screen-033--kelola-machinery-group,
 * business_logic step "delete" → 409 MACHINERY_GROUP_HAS_MACHINERY
 * delete-guard).
 *
 * Mirrors App\Exceptions\StationHasMachineryException /
 * App\Exceptions\BusinessUnitHasStationsException /
 * App\Exceptions\CompanyHasBusinessUnitsException /
 * App\Exceptions\CorporateHasCompaniesException exactly: a plain
 * HttpException (not Illuminate\Validation\ValidationException) since
 * this is a single, non-field-keyed condition (a delete-guard, not a form
 * validation failure) — ApiExceptionHandler's HttpExceptionInterface
 * fallback branch already renders any status code generically as
 * { "message": ... } with no `errors` key, which covers 409 here.
 */
class MachineryGroupHasMachineryException extends HttpException
{
    public function __construct(string $message = 'Machinery Group tidak dapat dihapus karena masih memiliki Machinery terkait.')
    {
        parent::__construct(409, $message);
    }
}
