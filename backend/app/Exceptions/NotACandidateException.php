<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * NotACandidateException — dilempar ShiftScheduleService ketika ada user_id yang bukan user AKTIF berperan Operator/Supervisor (role yang DIPILIH) pada mill dari line itu. Pesannya menyebut user dan alasannya; seluruh penyimpanan ditolak (screen-171--jadwal-shift, 422 NOT_A_CANDIDATE).
 *
 * HttpException biasa yang mengimplementasikan HasErrorCode, sama seperti
 * ShiftOverlapException: ApiExceptionHandler menyertakan `code: NOT_A_CANDIDATE` di
 * samping message-nya, dan komponen Livewire menangkapnya langsung. Ini satu
 * kondisi bisnis, bukan kegagalan validasi per-field.
 */
class NotACandidateException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Ada petugas yang bukan calon peserta shift pada mill ini.')
    {
        parent::__construct(422, $message);
    }

    public function errorCode(): string
    {
        return 'NOT_A_CANDIDATE';
    }
}
