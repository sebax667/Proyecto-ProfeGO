<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Shared\DTOs\RegisterUserDTO;
use App\Shared\Enums\UserRole;

class AuthController
{
    private readonly AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handleRegister(array $request): array
    {
        $name = trim($request['name'] ?? '');
        $email = trim($request['email'] ?? '');
        $password = (string) ($request['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            return [
                'status' => 'error',
                'message' => 'Los campos name, email y password son obligatorios.'
            ];
        }

        $role = UserRole::STUDENT;

        $dto = new RegisterUserDTO(
            name: $name,
            email: $email,
            password: $password,
            role: $role
        );

        return $this->authService->register($dto);
    }
    

    public function handleLogin(array $request): array
    {
        $email = trim($request['email'] ?? '');
        $password = (string) ($request['password'] ?? '');

        if (empty($email) || empty($password)) {
            return [
                'status' => 'error',
                'message' => 'Debes proporcionar email y password para iniciar sesión.'
            ];
        }

        $result = $this->authService->login($email, $password);

        if (($result['status'] ?? null) === 'success' && isset($result['token'])) {
            setcookie('profego_token', (string) $result['token'], [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        return $result;
    }
    public function profile(array $request, ?array $authUser = null): array
    {
        return [
            'status' => 'success',
            'message' => 'Perfil procesado correctamente por el controlador.',
            'authenticated_user' => $authUser
        ];
    }
}
