<?php

namespace App\Enums;

/**
 * PeriodStation.status (`period_stations`.`status`) — entity-catalog v18:
 * enum(draft, open, closed).
 *
 * Sampai 2026-09-25 ini adalah `periods`.`status`, satu status untuk seluruh
 * periode. Statusnya pindah ke `period_stations` karena stasiun tidak selesai
 * serentak: statusnya kini milik SATU JENIS STASIUN di dalam satu periode,
 * bukan milik periodenya.
 *
 * draft  = stasiun ini belum dipakai di periode ini;
 * open   = berjalan, input/edit/verifikasi record diizinkan;
 * closed = terkunci penuh UNTUK JENIS STASIUN INI SAJA — tidak ada record
 *          stasiun jenis ini yang tanggal kejadiannya jatuh di dalam rentang
 *          periode induk boleh dibuat, diubah, maupun diverifikasi. Jenis
 *          stasiun lain di periode yang sama TIDAK ikut terkunci.
 *
 * Transisi yang diizinkan: draft -> open, open -> closed, closed -> open
 * (buka kembali), berlaku per baris period_stations. Menutup dan membuka
 * kembali hanya boleh dilakukan Admin; penegakan transisi ada di layer service
 * (PeriodService/PeriodClosureService), bukan di enum.
 */
enum PeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
