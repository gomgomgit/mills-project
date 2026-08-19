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
class ExportFailedException extends HttpException
{
    public function __construct(string $message = 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.')
    {
        parent::__construct(422, $message);
    }
}
