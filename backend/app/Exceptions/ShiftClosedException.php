<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ShiftClosedException — dilempar ShiftScheduleService ketika PUT cell atau apply menyasar shift yang sudah ditutup (soft delete). Shift tertutup tidak dapat ditulisi sama sekali (screen-171--jadwal-shift, 422 SHIFT_CLOSED).
 *
 * HttpException biasa yang mengimplementasikan HasErrorCode, sama seperti
 * ShiftOverlapException: ApiExceptionHandler menyertakan `code: SHIFT_CLOSED` di
 * samping message-nya, dan komponen Livewire menangkapnya langsung. Ini satu
 * kondisi bisnis, bukan kegagalan validasi per-field.
 */
class ShiftClosedException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Shift ini sudah dihapus (ditutup) sehingga jadwalnya tidak dapat diubah.')
    {
        parent::__construct(422, $message);
    }

    public function errorCode(): string
    {
        return 'SHIFT_CLOSED';
    }
}
