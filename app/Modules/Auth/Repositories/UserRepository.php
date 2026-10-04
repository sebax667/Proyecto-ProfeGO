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

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}