<?php

declare(strict_types=1);

use App\Shared\Database\Database;

require_once __DIR__ . '/../vendor/autoload.php';

$dbPath = sys_get_temp_dir() . '/profego_seed_' . bin2hex(random_bytes(8)) . '.sqlite';
putenv('DB_PATH=' . $dbPath);
putenv('APP_ENV=testing');
register_shutdown_function(static function () use ($dbPath): void {
    unset($GLOBALS['pdo']);
    Database::closeConnection();
    gc_collect_cycles();
    $failures = [];
    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
        if (is_file($file) && !unlink($file)) {
            $failures[] = $file;
        }
    }
    if ($failures !== []) {
        fwrite(STDERR, 'No se pudieron eliminar las bases temporales: ' . implode(', ', $failures) . PHP_EOL);
        exit(1);
    }
});

$runSeed = static function (array $arguments = []): string {
    $command = [PHP_BINARY, __DIR__ . '/../database/seed_tutors.php', ...$arguments];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el proceso de seed.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('El seed falló: ' . $stderr . $stdout);
    }

    return $stdout . $stderr;
};

$runSeed();
$runSeed();
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assert((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3, 'El seed duplicó usuarios al repetirse.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM tutor_profiles')->fetchColumn() === 3, 'El seed duplicó perfiles al repetirse.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM tutor_subjects')->fetchColumn() === 10, 'El seed no escribió las relaciones del catálogo.');

putenv('APP_ENV=production');
$productionOutput = $runSeed();
$assert(str_contains($productionOutput, 'Seed omitido en production'), 'El seed no se protegió en production sin --force.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM tutor_profiles')->fetchColumn() === 3, 'El seed alteró tutores en production sin --force.');

$pdo = null;
Database::closeConnection();
echo json_encode([
    'status' => 'ok',
    'message' => 'Seed idempotente y bloqueado en production sin --force.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
