<?php

namespace App\Exceptions;

/**
 * PeriodHasRecordsException — thrown by PeriodService::delete() when the
 * period already FRAMES station records: at least one record of that mill,
 * of one of the period's station types, has an event date inside the
 * period's range (409 PERIOD_HAS_RECORDS, 2026-10-04).
 *
 * Why refuse: menutup periode membekukan angkanya, dan menghapus periode
 * yang berisi data diam-diam melepas bingkai record-record itu — record
 * tidak lagi berada di periode mana pun, sehingga laporan periode dan kunci
 * input (usecase-141) kehilangan jangkarnya. Periode KOSONG tetap boleh
 * dihapus.
 *
 * EXTENDS PeriodClosedImmutableException ON PURPOSE. Both Livewire screens
 * that delete a period (Kelola Periode Pelaporan, Detail Periode Pelaporan)
 * already catch that class and render its message inline next to the row;
 * extending it means this refusal surfaces the same way without touching
 * those components, while the API still reports its own code.
 */
class PeriodHasRecordsException extends PeriodClosedImmutableException
{
    public function errorCode(): string
    {
        return 'PERIOD_HAS_RECORDS';
    }
}
