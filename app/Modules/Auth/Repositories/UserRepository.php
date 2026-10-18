<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Shared\Database\Database;
use PDO;
use PDOException;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $email = $this->normalizeEmail((string) ($data['email'] ?? ''));

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
        );

        try {
            $stmt->execute([
                ':name' => trim((string) ($data['name'] ?? '')),
                ':email' => $email,
                ':password' => (string) ($data['password'] ?? ''),
                ':role' => (string) ($data['role'] ?? 'student'),
            ]);

            $id = $this->db->lastInsertId();
            return $id === false ? 0 : (int) $id;
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? null) === '23000') {
                return 0;
            }

            throw $e;
        }
    }

    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $stmt = $this->db->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute([':email' => $normalizedEmail]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function isLoginBlocked(string $email, string $ip): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email = :email COLLATE NOCASE
               AND ip = :ip
               AND succeeded = 0
               AND created_at >= datetime('now', '-15 minutes')"
        );
        $stmt->execute([
            ':email' => $this->normalizeEmail($email),
            ':ip' => $ip,
        ]);

        return (int) $stmt->fetchColumn() >= 5;
    }

    public function recordLoginAttempt(string $email, string $ip, bool $succeeded): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (email, ip, succeeded) VALUES (:email, :ip, :succeeded)'
        );
        $stmt->execute([
            ':email' => $this->normalizeEmail($email),
            ':ip' => $ip,
            ':succeeded' => $succeeded ? 1 : 0,
        ]);
    }

    public function createAuthSession(int $userId, string $jti, int $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO auth_sessions (user_id, jti, expires_at, ip, user_agent)
             VALUES (:user_id, :jti, :expires_at, :ip, :user_agent)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':jti' => $jti,
            ':expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
            ':ip' => $this->requestIp(),
            ':user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
        ]);
    }

    public function revokeAuthSession(string $jti): void
    {
        $stmt = $this->db->prepare(
            'UPDATE auth_sessions SET revoked_at = CURRENT_TIMESTAMP
             WHERE jti = :jti AND revoked_at IS NULL'
        );
        $stmt->execute([':jti' => $jti]);
    }

    public function isAuthSessionActive(string $jti): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM auth_sessions
             WHERE jti = :jti
               AND revoked_at IS NULL
               AND datetime(expires_at) > datetime('now')
             LIMIT 1"
        );
        $stmt->execute([':jti' => $jti]);

        return $stmt->fetchColumn() !== false;
    }

    private function requestIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : 'unknown';
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}