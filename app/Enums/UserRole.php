<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Patient = 'patient';
}
