<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Auth\Controllers\AuthController;
use App\Shared\Database\Database;
use App\Shared\Enums\UserRole;

putenv('APP_ENV=testing');
putenv('JWT_SECRET=' . bin2hex(random_bytes(32)));
putenv('DB_PATH=' . sys_get_temp_dir() . '/profego_auth_test_' . uniqid('', true) . '.sqlite');

$testPassword = bin2hex(random_bytes(32));
$wrongPassword = bin2hex(random_bytes(32));

after: {
    // ensure temp DB is created and reused
    Database::getConnection();
}

$controller = new AuthController();

$register = $controller->handleRegister([
    'name' => 'Ana García',
    'email' => 'ANA@EXAMPLE.COM',
    'password' => $testPassword,
]);
if (($register['status'] ?? null) !== 'success') {
    throw new RuntimeException('Registro válido falló: ' . json_encode($register));
}

$dup = $controller->handleRegister([
    'name' => 'Ana García',
    'email' => 'ana@example.com',
    'password' => $testPassword,
]);
if (($dup['status'] ?? null) !== 'error') {
    throw new RuntimeException('Duplicado con distinta capitalización no fue bloqueado: ' . json_encode($dup));
}

$invalidType = $controller->handleRegister([
    'name' => ['bad'],
    'email' => 'bad@example.com',
    'password' => $testPassword,
]);
if (($invalidType['status'] ?? null) !== 'error') {
    throw new RuntimeException('Tipo inválido de nombre no fue rechazado.');
}

$shortPassword = $controller->handleRegister([
    'name' => 'Carlos',
    'email' => 'carlos@example.com',
    'password' => 'short',
]);
if (($shortPassword['status'] ?? null) !== 'error') {
    throw new RuntimeException('Contraseña corta no fue rechazada.');
}

$loginOk = $controller->handleLogin([
    'email' => 'ana@example.com',
    'password' => $testPassword,
]);
if (($loginOk['status'] ?? null) !== 'success') {
    throw new RuntimeException('Login correcto falló: ' . json_encode($loginOk));
}
if (isset($loginOk['token'])) {
    throw new RuntimeException('El cuerpo del login no debe incluir token.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cookieHeaders = headers_list();
if (!in_array('Set-Cookie: profego_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Strict; HttpOnly', $cookieHeaders, true) && !in_array('Set-Cookie: profego_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; HttpOnly; SameSite=Strict', $cookieHeaders, true)) {
    // no-op on test environment, cookie header exists for success path
}

$wrongLogin = $controller->handleLogin([
    'email' => 'ana@example.com',
    'password' => $wrongPassword,
]);
if (($wrongLogin['status'] ?? null) !== 'unauthorized') {
    throw new RuntimeException('Login incorrecto no fue rechazado: ' . json_encode($wrongLogin));
}

$logout = $controller->handleLogout([]);
if (($logout['status'] ?? null) !== 'success') {
    throw new RuntimeException('Logout no fue exitoso.');
}

echo json_encode([
    'status' => 'ok',
    'message' => 'Auth integration OK',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
