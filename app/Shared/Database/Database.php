<?php

declare(strict_types=1);

namespace App\Shared\Database;

use PDO;
use PDOException;
use Throwable;

class Database
{
    private static ?PDO $instance = null;
    private static bool $migrationChecked = false;

    public static function getConnection(bool $forceMigrate = false): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $pdo = null;
        try {
            $dbPath = self::getDatabasePath();
            $directory = dirname($dbPath);
            if ($directory !== '' && !is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException('No se pudo preparar el directorio de la base de datos.');
            }

            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA synchronous = NORMAL');

            self::$instance = $pdo;
            if (!self::$migrationChecked) {
                self::migrate($pdo, self::migrationFiles(), $forceMigrate);
                self::$migrationChecked = true;
            }

            $journalMode = strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
            if ($journalMode !== 'wal') {
                $pdo->exec('PRAGMA journal_mode = WAL');
            }

            return $pdo;
        } catch (PDOException $exception) {
            self::$instance = null;
            self::throwSafeDatabaseException($exception);
        } catch (Throwable $exception) {
            self::$instance = null;
            throw $exception;
        }
    }

    public static function closeConnection(): void
    {
        self::$instance = null;
        self::$migrationChecked = false;
    }

    public static function getDatabasePath(): string
    {
        $configuredPath = getenv('DB_PATH');
        if (is_string($configuredPath) && trim($configuredPath) !== '') {
            return trim($configuredPath);
        }

        return __DIR__ . '/../../../database/database.sqlite';
    }

    /**
     * Applies pending migration files from the selected directory.
     *
     * @param array<int, string>|null $files
     */
    public static function migrate(PDO $pdo, ?array $files = null, bool $force = false): void
    {
        $files ??= self::migrationFiles();
        usort($files, static fn (string $left, string $right): int => self::versionFromFile($left) <=> self::versionFromFile($right));
        if ($files === []) {
            return;
        }

        $maximumVersion = max(array_map(self::versionFromFile(...), $files));
        $currentVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $hasPendingMigrations = $currentVersion < $maximumVersion;
        if ($hasPendingMigrations && !$force && !self::autoMigrateEnabled()) {
            error_log(sprintf(
                'AUTO_MIGRATE=0: base pendiente de migración (versión %d, máxima %d).',
                $currentVersion,
                $maximumVersion
            ));
            throw new MigrationPendingException('La base de datos requiere una actualización.');
        }

        self::backfillMigrationHistoryIfNeeded($pdo, $files, $currentVersion);
        if (!$hasPendingMigrations) {
            return;
        }

        $pendingFiles = array_values(array_filter(
            $files,
            static fn (string $file): bool => self::versionFromFile($file) > $currentVersion
        ));
        foreach ($pendingFiles as $file) {
            self::applyMigrationSafely($pdo, $file);
        }
    }

    /**
     * Returns migration status without changing the database.
     *
     * @param array<int, string>|null $files
     * @return array{version: int, maximum_version: int, pending: array<int, int>}
     */
    public static function migrationStatus(PDO $pdo, ?array $files = null): array
    {
        $files ??= self::migrationFiles();
        $versions = array_map(self::versionFromFile(...), $files);
        $currentVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $maximumVersion = $versions === [] ? 0 : max($versions);

        return [
            'version' => $currentVersion,
            'maximum_version' => $maximumVersion,
            'pending' => array_values(array_filter(
                $versions,
                static fn (int $version): bool => $version > $currentVersion
            )),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function migrationFiles(?string $directory = null): array
    {
        $directory ??= __DIR__ . '/../../../database/migrations';
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (string $file): bool => preg_match('/^\d+_.+\.php$/', basename($file)) === 1
        ));
    }

    private static function applyMigrationSafely(PDO $pdo, string $file): void
    {
        $version = self::versionFromFile($file);
        $migration = require $file;
        $requiresForeignKeysOff = is_array($migration)
            && ($migration['foreign_keys'] ?? null) === false;
        $up = is_array($migration) ? ($migration['up'] ?? null) : $migration;

        if ($requiresForeignKeysOff) {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }

        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $currentVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
            if ($currentVersion >= $version) {
                $pdo->exec('COMMIT');
                return;
            }

            self::ensureBootstrapTables($pdo);
            if (is_callable($up)) {
                $up($pdo);
            } else {
                throw new \RuntimeException('El archivo de migración no contiene una función ejecutable.');
            }

            if ($requiresForeignKeysOff) {
                $violations = $pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
                if ($violations !== []) {
                    throw new \RuntimeException('La migración dejó referencias foráneas inválidas.');
                }
            }

            $pdo->exec('PRAGMA user_version = ' . $version);
            $insertHistory = $pdo->prepare('INSERT OR IGNORE INTO schema_migrations (version) VALUES (:version)');
            $insertHistory->execute([':version' => $version]);
            $pdo->exec('COMMIT');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            throw $exception;
        } finally {
            if ($requiresForeignKeysOff) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
    }

    /**
     * Create bootstrap tables only if a legacy or empty database lacks them.
     */
    private static function ensureBootstrapTables(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'users')) {
            $pdo->exec('CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "student",
                avatar_url TEXT,
                phone TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
        }

        if (!self::tableExists($pdo, 'schema_migrations')) {
            $pdo->exec('CREATE TABLE schema_migrations (
                version INTEGER NOT NULL PRIMARY KEY,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
        }
    }

    /**
     * Fill history only when an already-versioned database lacks migration rows.
     *
     * @param array<int, string> $files
     */
    private static function backfillMigrationHistoryIfNeeded(PDO $pdo, array $files, int $currentVersion): void
    {
        $hasHistory = self::tableExists($pdo, 'schema_migrations');
        $recordedVersions = [];
        if ($hasHistory) {
            $recordedVersions = array_map(
                'intval',
                $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)
            );
        }

        $missingVersions = [];
        foreach ($files as $file) {
            $version = self::versionFromFile($file);
            if ($version <= $currentVersion && !in_array($version, $recordedVersions, true)) {
                $missingVersions[] = $version;
            }
        }
        if ($missingVersions === []) {
            return;
        }

        try {
            $pdo->exec('BEGIN IMMEDIATE');
            self::ensureBootstrapTables($pdo);
            $selectVersion = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
            $insertVersion = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
            foreach ($missingVersions as $version) {
                $selectVersion->execute([':version' => $version]);
                if ($selectVersion->fetchColumn() === false) {
                    $insertVersion->execute([':version' => $version]);
                }
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            throw $exception;
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        $statement->execute([':table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    private static function versionFromFile(string $file): int
    {
        if (preg_match('/^(\d+)_.*\.php$/', basename($file), $matches) !== 1) {
            throw new \InvalidArgumentException('Nombre de migración no válido.');
        }

        return (int) $matches[1];
    }

    private static function autoMigrateEnabled(): bool
    {
        $value = getenv('AUTO_MIGRATE');

        return $value === false || !in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off'], true);
    }

    private static function throwSafeDatabaseException(PDOException $exception): never
    {
        $sqliteCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
        if (in_array($sqliteCode & 0xFF, [5, 6], true)) {
            error_log('SQLite ocupado durante una migración: ' . $exception->getMessage());
            throw new DatabaseBusyException('La base de datos está ocupada. Inténtelo de nuevo.', previous: $exception);
        }

        error_log('Error de conexión SQLite: ' . $exception->getMessage());
        throw new \RuntimeException('No se pudo conectar con la base de datos.');
    }
}
