<?php

declare(strict_types=1);

$redirect = trim((string) ($_GET['redirect'] ?? '/dashboard'));
if ($redirect === '' || !str_starts_with($redirect, '/')) {
    $redirect = '/dashboard';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | ProfeGo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="profego-auth-page">
    <main class="profego-auth-card">
        <a href="/catalog" class="profego-brand" aria-label="Ir al catálogo de ProfeGo">ProfeGo</a>
        <h1 class="profego-page-title">Inicia sesión</h1>
        <p class="profego-page-lead">Accede a tu espacio de aprendizaje.</p>
        <form action="/api/auth/login" method="post" class="profego-auth-form">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
            <label for="login-email">Correo electrónico</label>
            <input id="login-email" name="email" type="email" required autocomplete="email">
            <label for="login-password">Contraseña</label>
            <input id="login-password" name="password" type="password" required autocomplete="current-password">
            <button type="submit" class="profego-button">Iniciar sesión</button>
        </form>
    </main>
</body>
</html>
