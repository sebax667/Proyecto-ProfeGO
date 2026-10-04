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
        $name = $request['name'] ?? '';
        $email = $request['email'] ?? '';
        $password = $request['password'] ?? '';

        $normalizedName = trim(is_string($name) ? $name : '');
        $normalizedEmail = strtolower(trim(is_string($email) ? $email : ''));
        $normalizedPassword = is_string($password) ? $password : '';

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
            setcookie('profego_token', (string) $result['token'], [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            unset($result['token']);
        }

        return $result;
    }

    public function handleLogout(array $request): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = (string) ($_COOKIE['profego_token'] ?? '');
        if ($token !== '') {
            $tokenJti = null;
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $tokenJti = is_array($payload) ? ((string) ($payload['jti'] ?? '')) : '';
            }

            if ($tokenJti !== '') {
                $_SESSION['revoked_tokens'][$tokenJti] = true;
            }
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
