<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ShiftOverlapException — dilempar ShiftService::create()/update() ketika
 * rentang jam yang diminta beririsan LEBIH DARI NOL MENIT dengan shift lain
 * pada production_line_id yang SAMA (screen-170--kelola-shift,
 * usecase-176 → 422 SHIFT_OVERLAP).
 *
 * BATASNYA INKLUSIF: shift yang berakhir TEPAT pada jam mulai shift lain
 * beririsan nol menit, jadi ia TIDAK bertindih — itu pergantian shift yang
 * normal dan harus diterima. Hanya irisan > 0 menit yang mendarat di sini.
 * Pola yang sama dengan PeriodOverlapException pada rentang tanggal Periode
 * Pelaporan.
 *
 * PESANNYA SELALU MENYEBUT NAMA SHIFT PEMBANDING DAN RENTANG JAM TINDIHANNYA
 * (business spec: pesan yang hanya berbunyi 'jam bertindih' memaksa Admin
 * mencari sendiri penyebabnya di antara belasan baris).
 *
 * Sengaja HttpException biasa — sama seperti PeriodOverlapException — tetapi
 * mengimplementasikan HasErrorCode sehingga ApiExceptionHandler menyertakan
 * `code: SHIFT_OVERLAP` di samping message-nya. Ini BUKAN ValidationException:
 * ia satu kondisi bisnis yang tidak terikat satu field, bukan kegagalan
 * validasi per-field (start_time == end_time justru yang sebaliknya — itu
 * VALIDATION_ERROR, lihat ShiftService::validate()).
 */
class ShiftOverlapException extends HttpException implements HasErrorCode
{
    public function __construct(string $message = 'Rentang jam shift bertindih dengan shift lain pada Production Line yang sama.')
    {
        parent::__construct(422, $message);
    }

    public function errorCode(): string
    {
        return 'SHIFT_OVERLAP';
    }
}
