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
}
