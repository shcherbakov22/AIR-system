<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Student = 'student';

    public function label(): string
    {
            self::Admin => 'Mentor',
            self::Student => 'Student',
            self::Student => 'Ð£Ñ‡ÐµÐ½Ð¸Ðº',
        };
    }
}
