<?php

namespace App\Enums;

/**
 * Period.status — entity-catalog: enum(draft, open, closed).
 *
 * draft  = periode belum dipakai;
 * open   = periode berjalan, input/edit/verifikasi record diizinkan;
 * closed = terkunci penuh — tidak ada record stasiun yang tanggal
 *          kejadiannya jatuh di dalam rentang periode boleh dibuat,
 *          diubah, maupun diverifikasi.
 *
 * Transisi yang diizinkan: draft -> open, open -> closed, closed -> open
 * (buka kembali). Menutup dan membuka kembali hanya boleh dilakukan Admin;
 * penegakan transisi ada di layer service (PeriodService), bukan di enum.
 */
enum PeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
