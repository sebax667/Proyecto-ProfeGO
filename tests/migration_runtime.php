<?php

declare(strict_types=1);

use App\Shared\Database\Database;
use App\Shared\Database\DatabaseBusyException;
use App\Shared\Database\MigrationPendingException;

require_once __DIR__ . '/../vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$makeTemporaryPath = static fn (string $prefix): string =>
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8)) . '.sqlite';
$temporaryPaths = [];
register_shutdown_function(static function () use (&$temporaryPaths): void {
    unset(
        $GLOBALS['pdo'],
        $GLOBALS['firstConnection'],
        $GLOBALS['secondConnection'],
        $GLOBALS['exception']
    );
    Database::closeConnection();
    gc_collect_cycles();
    $failed = [];
    foreach ($temporaryPaths as $databasePath) {
        foreach ([$databasePath, $databasePath . '-wal', $databasePath . '-shm', $databasePath . '-journal'] as $file) {
            for ($attempt = 0; is_file($file) && $attempt < 10; $attempt++) {
                if (!unlink($file)) {
                    usleep(50000);
                    gc_collect_cycles();
                }
            }
            if (is_file($file)) {
                $failed[] = $file;
            }
        }
    }
    if ($failed !== []) {
        fwrite(STDERR, 'No se pudieron eliminar bases temporales: ' . implode(', ', $failed) . PHP_EOL);
        exit(1);
    }
});

$environmentForChild = static function (string $databasePath): array {
    $environment = getenv();
    if (!is_array($environment)) {
        $environment = [];
    }
    $environment['DB_PATH'] = $databasePath;
    $environment['AUTO_MIGRATE'] = '0';
    $environment['APP_ENV'] = 'testing';

    return $environment;
};
$runMigrationCommand = static function (string $databasePath, array $arguments = []) use ($environmentForChild): array {
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/../scripts/migrate.php', ...$arguments],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__),
        $environmentForChild($databasePath)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar scripts/migrate.php.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};
$readDatabaseState = static function (string $databasePath): array {
    if (!is_file($databasePath)) {
        return ['exists' => false, 'version' => 0, 'history' => null];
    }
    $pdo = new PDO('sqlite:' . $databasePath);
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $hasHistory = (int) $pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'"
    )->fetchColumn() > 0;
    $history = $hasHistory
        ? $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN)
        : null;
    $pdo = null;

    return ['exists' => true, 'version' => $version, 'history' => $history];
};

$absentStatusPath = $makeTemporaryPath('profego_status_absent_');
$temporaryPaths[] = $absentStatusPath;
$absentStatus = $runMigrationCommand($absentStatusPath, ['--status']);
$assert($absentStatus['exit'] === 0, '--status falló para una ruta de base todavía inexistente.');
$assert(str_contains($absentStatus['stdout'], 'user_version: 0'), '--status no reportó versión cero para base inexistente.');
$assert(!is_file($absentStatusPath), '--status creó una base de datos inexistente.');

$alreadyMigratedPath = $makeTemporaryPath('profego_no_write_');
$temporaryPaths[] = $alreadyMigratedPath;
putenv('DB_PATH=' . $alreadyMigratedPath);
putenv('AUTO_MIGRATE=1');
Database::closeConnection();
$firstConnection = Database::getConnection();
$firstConnection = null;
Database::closeConnection();
$pdo = Database::getConnection();
$assert((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 8, 'La base temporal no llegó a user_version 8.');
$assert((int) $pdo->query('SELECT total_changes()')->fetchColumn() === 0, 'getConnection escribió en una base ya migrada.');
$pdo = null;
Database::closeConnection();

$markerPath = $makeTemporaryPath('profego_migration_race_');
$temporaryPaths[] = $markerPath;
$firstConnection = new PDO('sqlite:' . $markerPath);
$firstConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$firstConnection->exec('CREATE TABLE migration_marker (application_count INTEGER NOT NULL)');
$firstConnection->exec('INSERT INTO migration_marker VALUES (0)');
$secondConnection = new PDO('sqlite:' . $markerPath);
$secondConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$fixtureMigrations = [__DIR__ . '/fixtures/migrations/001_count_application.php'];
Database::migrate($firstConnection, $fixtureMigrations, force: true);
$assert((int) $firstConnection->query('SELECT application_count FROM migration_marker')->fetchColumn() === 1, 'La migración marcador no corrió exactamente una vez.');
Database::migrate($secondConnection, $fixtureMigrations, force: true);
$assert((int) $secondConnection->query('SELECT application_count FROM migration_marker')->fetchColumn() === 1, 'La segunda conexión reejecutó una migración ya aplicada.');
$assert((int) $secondConnection->query('SELECT total_changes()')->fetchColumn() === 0, 'La segunda conexión escribió al detectar la migración aplicada.');
$firstConnection = null;
$secondConnection = null;

$pendingPath = $makeTemporaryPath('profego_auto_migrate_off_');
$temporaryPaths[] = $pendingPath;
putenv('DB_PATH=' . $pendingPath);
putenv('AUTO_MIGRATE=0');
Database::closeConnection();
$blockedAsExpected = false;
try {
    Database::getConnection();
} catch (MigrationPendingException $exception) {
    $blockedAsExpected = $exception->getMessage() === 'La base de datos requiere una actualización.';
}
unset($exception);
$assert($blockedAsExpected, 'AUTO_MIGRATE=0 no rechazó una base pendiente con el error esperado.');
$pendingState = $readDatabaseState($pendingPath);
$assert($pendingState['version'] === 0 && $pendingState['history'] === null, 'AUTO_MIGRATE=0 realizó escrituras de migración.');

$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketErrorMessage);
$assert(is_resource($socket), 'No se pudo reservar un puerto temporal para la prueba HTTP.');
$socketAddress = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr((string) $socketAddress, strrpos((string) $socketAddress, ':') + 1);
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/public'],
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $serverPipes,
    dirname(__DIR__),
    $environmentForChild($pendingPath)
);
$assert(is_resource($server), 'No se pudo iniciar PHP para verificar el HTTP 503.');
fclose($serverPipes[0]);
try {
    $httpResult = null;
    $url = 'http://127.0.0.1:' . $port . '/api/migration-check';
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);
        $content = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        if (isset($headers[0])) {
            $httpResult = [
                'status' => preg_match('/\s(\d{3})(?:\s|$)/', $headers[0], $matches) === 1
                    ? (int) $matches[1]
                    : 0,
                'body' => $content === false ? '' : $content,
            ];
            break;
        }
        usleep(100000);
    }
    $assert(is_array($httpResult), 'El servidor HTTP temporal no respondió.');
    $assert($httpResult['status'] === 503, 'AUTO_MIGRATE=0 no respondió HTTP 503 para una base pendiente.');
    $assert(
        str_contains($httpResult['body'], 'Servicio temporalmente no disponible.'),
        'La respuesta HTTP 503 no fue genérica en español.'
    );
} finally {
    proc_terminate($server);
    fclose($serverPipes[1]);
    fclose($serverPipes[2]);
    proc_close($server);
}

$beforeStatus = $readDatabaseState($pendingPath);
$statusResult = $runMigrationCommand($pendingPath, ['--status']);
$assert($statusResult['exit'] === 0, 'scripts/migrate.php --status falló: ' . $statusResult['stderr']);
$assert(str_contains($statusResult['stdout'], 'user_version: 0'), '--status no informó user_version.');
$assert(str_contains($statusResult['stdout'], 'versión máxima: 8'), '--status no informó versión máxima.');
$assert(str_contains($statusResult['stdout'], 'migraciones pendientes: 1, 2, 3, 4, 5, 6, 7, 8'), '--status no informó migraciones pendientes.');
$assert($readDatabaseState($pendingPath) === $beforeStatus, '--status modificó el estado de la base.');

$migrationResult = $runMigrationCommand($pendingPath);
$assert($migrationResult['exit'] === 0, 'scripts/migrate.php no migró con AUTO_MIGRATE=0: ' . $migrationResult['stderr']);
$assert(str_contains($migrationResult['stdout'], 'integrity_check: ok'), 'El CLI no validó la integridad.');
$assert(str_contains($migrationResult['stdout'], 'foreign_key_check: sin errores'), 'El CLI no validó claves foráneas.');
$migratedState = $readDatabaseState($pendingPath);
$assert($migratedState['version'] === 8, 'El CLI no dejó user_version en 8.');
$assert($migratedState['history'] === range(1, 8), 'El CLI no registró las migraciones 1–8.');

$statusAfterMigration = $runMigrationCommand($pendingPath, ['--status']);
$assert($statusAfterMigration['exit'] === 0, '--status sobre base actualizada falló.');
$assert($readDatabaseState($pendingPath) === $migratedState, '--status modificó la base ya migrada.');

putenv('DB_PATH=' . $pendingPath);
putenv('AUTO_MIGRATE=0');
Database::closeConnection();
$pdo = Database::getConnection();
$assert((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 8, 'AUTO_MIGRATE=0 bloqueó una base ya actualizada.');
$assert((int) $pdo->query('SELECT total_changes()')->fetchColumn() === 0, 'La conexión sin migraciones pendientes hizo escrituras.');
$pdo = null;
Database::closeConnection();

$busyPath = $makeTemporaryPath('profego_busy_');
$temporaryPaths[] = $busyPath;
$busyLock = new PDO('sqlite:' . $busyPath);
$busyLock->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$busyLock->exec('PRAGMA journal_mode = WAL');
$busyLock->exec('BEGIN IMMEDIATE');
putenv('DB_PATH=' . $busyPath);
putenv('AUTO_MIGRATE=1');
Database::closeConnection();
$busyRejectedClearly = false;
try {
    Database::getConnection();
} catch (DatabaseBusyException $exception) {
    $busyRejectedClearly = $exception->getMessage() === 'La base de datos está ocupada. Inténtelo de nuevo.'
        && !str_contains($exception->getMessage(), $busyPath);
}
unset($exception);
$busyLock->exec('ROLLBACK');
$busyLock = null;
Database::closeConnection();
$assert($busyRejectedClearly, 'SQLite ocupado no produjo el error claro y seguro esperado.');

ob_start();
\App\Shared\Http\Router::sendServiceUnavailable('/api/test');
$responseBody = ob_get_clean();
$assert(http_response_code() === 503, 'El Router no respondió 503 con AUTO_MIGRATE desactivado.');
$assert(str_contains((string) $responseBody, 'Servicio temporalmente no disponible.'), 'El Router no devolvió el mensaje genérico 503.');

echo json_encode([
    'status' => 'ok',
    'message' => 'Migración sin escrituras, doble conexión, AUTO_MIGRATE y CLI verificados.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
