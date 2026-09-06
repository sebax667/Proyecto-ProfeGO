<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

use App\Shared\Enums\UserRole;

readonly class RegisterUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role
    ) {}
}