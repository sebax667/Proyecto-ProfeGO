<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\UserRepository;
use App\Shared\DTOs\RegisterUserDTO;
use App\Shared\Security\JwtService;

class AuthService
{
    private readonly JwtService $jwtService;

    public function __construct(
        private readonly UserRepository $userRepository = new UserRepository(),
        ?JwtService $jwtService = null
    ) {
        $this->jwtService = $jwtService ?? new JwtService();
    }

    public function register(RegisterUserDTO $dto): array
    {
        $email = strtolower(trim($dto->email));

        if ($this->userRepository->findByEmail($email)) {
            return [
                'status' => 'error',
                'message' => 'El correo electrónico ya se encuentra registrado.'
            ];
        }

        $hashedPassword = password_hash($dto->password, PASSWORD_ARGON2ID);

        $created = $this->userRepository->create([
            'name' => $dto->name,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $dto->role->value,
        ]);

        if ($created <= 0) {
            return [
                'status' => 'error',
                'message' => 'No se pudo guardar el usuario.'
            ];
        }

        return [
            'status' => 'success',
            'created' => true,
            'message' => 'Usuario guardado en base de datos correctamente.',
            'user' => [
                'name' => $dto->name,
                'email' => $email,
                'role' => $dto->role->value,
                'role_label' => $dto->role->label(),
            ],
        ];
    }

    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->userRepository->findByEmail($normalizedEmail);
        $passwordValid = false;

        if ($user !== null && is_string($user['password'] ?? '')) {
            $passwordValid = password_verify($password, $user['password']);
            if ($passwordValid && password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
                $user['password'] = password_hash($password, PASSWORD_ARGON2ID);
            }
        }

        if (!$user || !$passwordValid) {
            usleep(200000);
            return [
                'status' => 'unauthorized',
                'message' => 'Credenciales de acceso inválidas.'
            ];
        }

        $token = $this->jwtService->generateToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);

        return [
            'status' => 'success',
            'message' => 'Autenticación exitosa.',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
            'token' => $token,
        ];
    }
}