<?php

declare(strict_types=1);

namespace App\Shared\Middleware;

use App\Shared\Enums\UserRole;

class RoleMiddleware
{
    public function __construct(
        private readonly array $allowedRoles = [UserRole::ADMIN->value, UserRole::TUTOR->value]
    ) {}

    public function handleWithUser(?array $user): bool|array
    {
        if (!$user || !isset($user['role'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'unauthorized',
                'message' => 'Autenticación requerida previo a verificación de rol.'
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        if (!in_array($user['role'], $this->allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'forbidden',
                'message' => 'Acceso denegado. Tu rol [' . $user['role'] . '] no posee permisos suficientes.'
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    }
}