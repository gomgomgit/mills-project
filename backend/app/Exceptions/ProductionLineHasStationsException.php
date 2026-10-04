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
 *
 * Sejak audit 2026-10-05 mengimplementasikan HasErrorCode sehingga respons
 * 409 membawa `code: PRODUCTION_LINE_HAS_STATIONS` (sebelumnya hanya `message`,
 * padahal spec menamai kode ini).
 */
class ProductionLineHasStationsException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Production Line tidak dapat dihapus karena masih memiliki Station terkait.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'PRODUCTION_LINE_HAS_STATIONS';
    }
}
