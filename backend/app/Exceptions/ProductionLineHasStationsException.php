<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ProductionLineHasStationsException — thrown by
 * ProductionLineService::delete() when the production line being deleted
 * still has related Station rows (screen-036--kelola-production-line,
 * business_logic step "delete" → 409 PRODUCTION_LINE_HAS_STATIONS
 * delete-guard).
 *
 * Mirrors App\Exceptions\BusinessUnitHasStationsException /
 * App\Exceptions\MachineryGroupHasMachineryException exactly.
 */
class ProductionLineHasStationsException extends HttpException
{
    public function __construct(string $message = 'Production Line tidak dapat dihapus karena masih memiliki Station terkait.')
    {
        parent::__construct(409, $message);
    }
}
