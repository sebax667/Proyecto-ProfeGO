<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Shared\Enums\UserRole;
use App\Shared\Http\ErrorPage;

class RoleMiddleware
{
    public function __construct(
        private readonly array $allowedRoles = [UserRole::ADMIN->value, UserRole::TUTOR->value]
    ) {}

    public function handleWithUser(?array $user): bool|array
    {
        if (!$user || !isset($user['role'])) {
            $this->denyAccess(401, 'Autenticación requerida previo a verificación de rol.');
            return false;
        }

        if (!in_array($user['role'], $this->allowedRoles, true)) {
            $this->denyAccess(403, 'Acceso denegado.');
            return false;
        }

        return true;
    }

    private function denyAccess(int $statusCode, string $message): void
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
        if (!str_starts_with($path, '/api/')) {
            ErrorPage::send($statusCode === 403 ? 403 : 401);
            return;
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $statusCode === 403 ? 'forbidden' : 'unauthorized',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }
}