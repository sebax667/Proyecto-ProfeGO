<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta | ProfeGo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="profego-auth-page">
    <main class="profego-auth-card">
        <a href="/catalog" class="profego-brand" aria-label="Ir al catálogo de ProfeGo">ProfeGo</a>
        <h1 class="profego-page-title">Crear cuenta</h1>
        <p class="profego-page-lead">Únete a ProfeGo como estudiante.</p>
        <form id="register-form" action="/api/auth/register" method="post" class="profego-auth-form">
            <label for="register-name">Nombre</label>
            <input id="register-name" name="name" required autocomplete="name">
            <label for="register-email">Correo electrónico</label>
            <input id="register-email" name="email" type="email" required autocomplete="email">
            <label for="register-password">Contraseña</label>
            <input id="register-password" name="password" type="password" required minlength="8" autocomplete="new-password">
            <button type="submit" class="profego-button">Registrarme</button>
            <p id="register-error" class="profego-form-error" role="alert" hidden></p>
        </form>
        <p class="profego-auth-link">¿Ya tienes cuenta? <a href="/login">Inicia sesión</a></p>
    </main>
    <script>
        document.getElementById('register-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const error = document.getElementById('register-error');
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            error.hidden = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        name: form.elements.name.value,
                        email: form.elements.email.value,
                        password: form.elements.password.value
                    })
                });
                const payload = await response.json();

                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'No se pudo crear la cuenta.');
                }

                window.location.assign('/login');
            } catch (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
