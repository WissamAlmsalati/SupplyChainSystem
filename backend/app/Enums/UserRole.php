<?php

namespace App\Enums;

// Names of the built-in user_types rows; each maps to one profile table.
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Customer = 'customer';
    case Delegate = 'delegate';
}
