<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

readonly class UserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public ?string $avatarUrl = null,
        public ?string $phone = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            role: (string) ($data['role'] ?? 'student'),
            avatarUrl: $data['avatar_url'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }
}
