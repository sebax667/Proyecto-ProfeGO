<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum UserRole: string
{
    case STUDENT = 'student';
    case TUTOR = 'tutor';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match($this) {
            self::STUDENT => 'Estudiante',
            self::TUTOR => 'Tutor Calificado',
            self::ADMIN => 'Administrador',
        };
    }
}