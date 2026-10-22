<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ConfirmationRequiredException — dilempar ShiftScheduleService::apply() ketika penulisan akan menimpa sel yang sudah berisi tetapi confirm_overwrite bukan true. Nol tulis; klien mengulang dengan confirm_overwrite=true (screen-171--jadwal-shift, 409 CONFIRMATION_REQUIRED).
 *
 * HttpException biasa yang mengimplementasikan HasErrorCode, sama seperti
 * ShiftOverlapException: ApiExceptionHandler menyertakan `code: CONFIRMATION_REQUIRED` di
 * samping message-nya, dan komponen Livewire menangkapnya langsung. Ini satu
 * kondisi bisnis, bukan kegagalan validasi per-field.
 */
class ConfirmationRequiredException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Aksi ini akan menimpa sel yang sudah berisi dan memerlukan konfirmasi.')
    {
        parent::__construct(409, $message);
    }

    public function errorCode(): string
    {
        return 'CONFIRMATION_REQUIRED';
    }
}
