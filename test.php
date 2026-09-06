<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Services\AuthService;

$authService = new AuthService();
$authController = new AuthController($authService);

$email = "estudiante_" . time() . "@universidad.edu.co";

// 1. Prueba de Registro
echo "=== 1. PRUEBA DE REGISTRO EN BASE DE DATOS ===" . PHP_EOL;
$registerResult = $authController->handleRegister([
    'name' => 'Sebastián',
    'email' => $email,
    'password' => 'PasswordSeguro123*',
    'role' => 'tutor'
]);
print_r($registerResult);

// 2. Prueba de Login Correcto
echo PHP_EOL . "=== 2. PRUEBA DE LOGIN CORRECTO ===" . PHP_EOL;
$loginResult = $authController->handleLogin([
    'email' => $email,
    'password' => 'PasswordSeguro123*'
]);
print_r($loginResult);

// 3. Prueba de Login con Contraseña Incorrecta
echo PHP_EOL . "=== 3. PRUEBA DE LOGIN INCORRECTO ===" . PHP_EOL;
$failedLogin = $authController->handleLogin([
    'email' => $email,
    'password' => 'ClaveInvalida'
]);
print_r($failedLogin);