<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Repositories\UserRepository;
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
        $name = $request['name'] ?? '';
        $email = $request['email'] ?? '';
        $password = $request['password'] ?? '';
        $terms = $request['terms'] ?? false;

        $normalizedName = trim(is_string($name) ? $name : '');
        $normalizedEmail = strtolower(trim(is_string($email) ? $email : ''));
        $normalizedPassword = is_string($password) ? $password : '';
        $acceptedTerms = $terms === true || (is_string($terms) && strtolower($terms) === 'true');

        if (!$acceptedTerms) {
            return [
                'status' => 'error',
                'message' => 'Debes aceptar los términos y condiciones para registrarte.'
            ];
        }

        if ($this->invalidName($normalizedName) || $this->invalidEmail($normalizedEmail) || $this->invalidPassword($normalizedPassword)) {
            return [
                'status' => 'error',
                'message' => 'El nombre, email y password no cumplen con los requisitos de seguridad.'
            ];
        }

        $dto = new RegisterUserDTO(
            name: $normalizedName,
            email: $normalizedEmail,
            password: $normalizedPassword,
            role: UserRole::STUDENT,
        );

        return $this->authService->register($dto);
    }

    public function handleLogin(array $request): array
    {
        $email = strtolower(trim((string) ($request['email'] ?? '')));
        $password = (string) ($request['password'] ?? '');

        if ($this->invalidEmail($email) || $this->invalidPassword($password)) {
            return [
                'status' => 'error',
                'message' => 'Debes proporcionar email y password válidos para iniciar sesión.'
            ];
        }

        $result = $this->authService->login($email, $password);

        if (($result['status'] ?? null) === 'success' && isset($result['token'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (strtolower((string) getenv('APP_ENV')) === 'production');
            $expiresAt = (int) ($result['session_expires_at'] ?? 0);
            if ($expiresAt <= time()) {
                throw new \RuntimeException('El token de sesión no tiene una expiración válida.');
            }
            setcookie('profego_token', (string) $result['token'], [
                'expires' => $expiresAt,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            unset($result['token']);
            unset($result['session_expires_at']);
        }

        return $result;
    }

    public function handleLogout(array $request): array
    {
        $token = (string) ($_COOKIE['profego_token'] ?? '');
        if ($token !== '') {
            $this->authService->revokeToken($token);
        }

        setcookie('profego_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (strtolower((string) getenv('APP_ENV')) === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return [
            'status' => 'success',
            'message' => 'Sesión cerrada correctamente.',
        ];
    }

    public function me(array $request, ?array $authUser = null): array
    {
        $userId = (int) (($authUser['user_id'] ?? $authUser['id'] ?? 0));
        if ($userId <= 0) {
            return [
                'status' => 'unauthorized',
                'message' => 'Sesión inválida.',
            ];
        }

        $user = (new UserRepository())->findById($userId);
        if ($user === null) {
            return [
                'status' => 'unauthorized',
                'message' => 'Sesión inválida.',
            ];
        }

        return [
            'status' => 'success',
            'user' => $user,
        ];
    }

    public function profile(array $request, ?array $authUser = null): array
    {
        return [
            'status' => 'success',
            'message' => 'Perfil procesado correctamente por el controlador.',
            'authenticated_user' => $authUser,
        ];
    }

    private function invalidName(mixed $name): bool
    {
        if (!is_string($name)) {
            return true;
        }

        return mb_strlen(trim($name), 'UTF-8') < 2 || mb_strlen(trim($name), 'UTF-8') > 100;
    }

    private function invalidEmail(mixed $email): bool
    {
        if (!is_string($email)) {
            return true;
        }

        return !filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== strtolower($email);
    }

    private function invalidPassword(mixed $password): bool
    {
        if (!is_string($password)) {
            return true;
        }

        return mb_strlen($password, 'UTF-8') < 10 || mb_strlen($password, 'UTF-8') > 128;
    }
}
