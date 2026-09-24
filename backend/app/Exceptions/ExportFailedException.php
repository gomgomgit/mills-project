<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ExportFailedException — thrown by WeighbridgeRecordService::export() when
 * the filtered dataset exceeds the row limit (WeighbridgeRecordService::EXPORT_ROW_LIMIT)
 * or file generation otherwise fails (screen-016--data-browser-weighbridge-web,
 * business_logic step 5 → 422 EXPORT_FAILED).
 *
 * Deliberately a plain HttpException (same pattern as
 * InvalidDateRangeException) rather than Illuminate\Validation\ValidationException
 * — this is a single, non-field-keyed condition, and ApiExceptionHandler's
 * HttpExceptionInterface branch already renders it correctly as
 * { "message": ... } with no `errors` key.
 */
class ExportFailedException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.')
    {
        parent::__construct(422, $message);
    }

    /**
     * ADDED 2026-09-23 with screen-129--laporan-sterilizer-web, whose
     * api_contract names EXPORT_FAILED as the machine-readable code for a
     * refused export (shared_decisions.naming_conventions lists it by name
     * alongside PERIOD_CLOSED / INVALID_DATE_RANGE).
     *
     * Purely ADDITIVE, per ApiExceptionHandler's opt-in contract: the
     * response keeps its status (422), its message, and its lack of an
     * `errors` key — it only gains `code`. No existing assertion on this
     * response's shape changes meaning, and every other export in the
     * codebase (18 station browsers) now carries the same code for the same
     * condition, which is exactly what a machine-readable code is for.
     */
    public function errorCode(): string
    {
        return 'EXPORT_FAILED';
    }
}
