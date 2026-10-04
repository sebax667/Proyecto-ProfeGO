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
                $dbPath = getenv('DB_PATH') ?: __DIR__ . '/../../../database/database.sqlite';
                $directory = dirname($dbPath);
                if ($directory !== '' && !is_dir($directory)) {
                    @mkdir($directory, 0777, true);
                }

                self::$instance = new PDO('sqlite:' . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                self::$instance->exec('PRAGMA foreign_keys = ON');
                self::$instance->exec('PRAGMA journal_mode = WAL');
                self::$instance->exec('PRAGMA busy_timeout = 5000');
                self::$instance->exec('PRAGMA synchronous = NORMAL');
                self::migrate(self::$instance);
            } catch (PDOException $e) {
                throw new \RuntimeException('Error de conexión a la base de datos: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "student",
            avatar_url TEXT,
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER NOT NULL PRIMARY KEY,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $migrationsDir = __DIR__ . '/../../../database/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }

        $currentVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $files = glob($migrationsDir . '/*.php');
        if ($files === false) {
            return;
        }

        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = (int) basename($file, '.php');
            if ($version <= $currentVersion) {
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO schema_migrations (version) VALUES (:version)');
                $stmt->execute([':version' => $version]);
                continue;
            }

            $migration = require $file;
            $pdo->beginTransaction();
            try {
                if (is_callable($migration)) {
                    $migration($pdo);
                }

                $pdo->exec('PRAGMA user_version = ' . $version);
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO schema_migrations (version) VALUES (:version)');
                $stmt->execute([':version' => $version]);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }

            $currentVersion = $version;
        }
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns, true)) {
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
        }
    }
}