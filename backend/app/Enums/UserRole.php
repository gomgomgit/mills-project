<?php

namespace App\Enums;

/**
 * User.role — entity-catalog: enum(operator, supervisor, mill_management, admin)
 */
enum UserRole: string
{
    case Operator = 'operator';
    case Supervisor = 'supervisor';
    case MillManagement = 'mill_management';
    case Admin = 'admin';

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
        };
    }
}
