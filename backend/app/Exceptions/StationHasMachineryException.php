<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * StationHasMachineryException — thrown by StationService::delete()
 * when the station being deleted still has related MachineryGroup and/or
 * Machinery rows (screen-030--kelola-station, business_logic step
 * "delete" → 409 STATION_HAS_MACHINERY delete-guard).
 *
 * Checks BOTH `MachineryGroup` (via App\Models\MachineryGroup, the
 * placeholder model this screen creates) and `Machinery` (via the
 * pre-existing App\Models\Machinery) counts — EITHER being non-zero
 * blocks deletion, since both point at the station via `station_id`.
 *
 * Mirrors App\Exceptions\BusinessUnitHasStationsException /
 * App\Exceptions\CompanyHasBusinessUnitsException /
 * App\Exceptions\CorporateHasCompaniesException exactly: a plain
 * HttpException (not Illuminate\Validation\ValidationException) since
 * this is a single, non-field-keyed condition (a delete-guard, not a form
 * validation failure) — ApiExceptionHandler's HttpExceptionInterface
 * fallback branch already renders any status code generically as
 * { "message": ... } with no `errors` key, which covers 409 here.
 */
class StationHasMachineryException extends HttpException
{
    public function __construct(string $message = 'Station tidak dapat dihapus karena masih memiliki Machinery Group atau Machinery terkait.')
    {
        parent::__construct(409, $message);
    }
}
