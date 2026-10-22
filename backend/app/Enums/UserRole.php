<?php

namespace App\Enums;

/**
 * User.role — entity-catalog: enum(operator, supervisor, mill_management, admin,
 * engineer, shift_supervisor, maintenance, mekanik, logistics, qa_qc, mill_admin)
 *
 * Tujuh role terakhir (keputusan produk 2026-10-22) SEMENTARA setara Admin
 * penuh. Mereka disimpan sebagai nilai tersendiri di kolom users.role, tetapi
 * model User membacanya sebagai Admin (lihat App\Casts\EffectiveUserRole) —
 * jadi guard 'role:...' dan semua pengecekan `=== UserRole::Admin` berlaku
 * tanpa diubah. Saat pembatasan per-role ditetapkan, cukup keluarkan role itu
 * dari adminEquivalents() lalu tambahkan ke guard yang relevan.
 */
enum UserRole: string
{
    case Operator = 'operator';
    case Supervisor = 'supervisor';
    case MillManagement = 'mill_management';
    case Admin = 'admin';
    case Engineer = 'engineer';
    case ShiftSupervisor = 'shift_supervisor';
    case Maintenance = 'maintenance';
    case Mekanik = 'mekanik';
    case Logistics = 'logistics';
    case QaQc = 'qa_qc';
    case MillAdmin = 'mill_admin';

    /**
     * Label tampilan — dulu tiap view menulis ucfirst(str_replace('_', ' ',
     * …)) yang menghasilkan "Mill management".
     */
    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::Supervisor => 'Supervisor',
            self::MillManagement => 'Mill Management',
            self::Admin => 'Admin',
            self::Engineer => 'Engineer',
            self::ShiftSupervisor => 'Shift Supervisor',
            self::Maintenance => 'Maintenance',
            self::Mekanik => 'Mekanik',
            self::Logistics => 'Logistics',
            self::QaQc => 'QA/QC',
            self::MillAdmin => 'Mill Admin',
        };
    }

    /**
     * Role yang saat ini diperlakukan persis seperti Admin (selain Admin itu sendiri).
     *
     * @return list<self>
     */
    public static function adminEquivalents(): array
    {
        return [
            self::Engineer,
            self::ShiftSupervisor,
            self::Maintenance,
            self::Mekanik,
            self::Logistics,
            self::QaQc,
            self::MillAdmin,
        ];
    }

    /** Admin atau salah satu role yang setara Admin. */
    public function isAdminLike(): bool
    {
        return $this === self::Admin || in_array($this, self::adminEquivalents(), true);
    }

    /** Role yang dipakai guard dan pengecekan akses: role setara Admin → Admin. */
    public function effective(): self
    {
        return $this->isAdminLike() ? self::Admin : $this;
    }
}
