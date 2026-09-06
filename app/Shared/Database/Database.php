<?php

declare(strict_types=1);

namespace App\Shared\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO('sqlite:' . __DIR__ . '/../../../database/database.sqlite');
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec('PRAGMA foreign_keys = ON');
                self::migrate(self::$instance);
            } catch (PDOException $e) {
                throw new \RuntimeException("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL, role TEXT NOT NULL DEFAULT "student",
            avatar_url TEXT, phone TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS tutor_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE,
            headline TEXT NOT NULL, bio TEXT DEFAULT "", hourly_rate REAL NOT NULL DEFAULT 0,
            rating_avg REAL NOT NULL DEFAULT 0, reviews_count INTEGER NOT NULL DEFAULT 0,
            modality TEXT NOT NULL DEFAULT "virtual", city TEXT, subjects TEXT DEFAULT "[]",
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS tutor_availabilities (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tutor_id INTEGER NOT NULL,
            start_at TEXT NOT NULL, end_at TEXT NOT NULL, is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tutor_id INTEGER NOT NULL, student_id INTEGER NOT NULL,
            starts_at TEXT NOT NULL, ends_at TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending",
            hourly_rate REAL NOT NULL, total_price REAL NOT NULL, title TEXT, notes TEXT,
            meeting_type TEXT DEFAULT "video", meeting_id TEXT, meeting_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tutor_id) REFERENCES tutor_profiles(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        self::addColumnIfMissing($pdo, 'users', 'avatar_url', 'TEXT');
        self::addColumnIfMissing($pdo, 'users', 'phone', 'TEXT');
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns, true)) {
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
        }
    }
}