<?php

declare(strict_types=1);

$redirect = trim((string) ($_GET['redirect'] ?? '/dashboard'));
if ($redirect === '' || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
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
<body class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.18),_transparent_32%),linear-gradient(135deg,_#eef2ff_0%,_#f8fafc_42%,_#eef2ff_100%)]">
    <div class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-10">
        <div class="grid w-full overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-[0_35px_80px_-32px_rgba(79,70,229,0.38)] lg:grid-cols-[1.15fr_0.85fr]">
            <div class="relative hidden flex-col justify-between overflow-hidden bg-slate-950 p-8 text-white lg:flex">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(99,102,241,0.50),_transparent_35%)]"></div>

                <div class="relative">
                    <?php include __DIR__ . '/../../components/profego-brand.php'; ?>
                </div>

                <div class="relative">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-indigo-200">
                        Bienvenido de nuevo
                    </p>
                    <h1 class="mt-4 text-4xl font-black tracking-tight">
                        Aprende con tutores que realmente entienden tu ritmo.
                    </h1>
                    <p class="mt-4 max-w-md text-base text-slate-300">
                        Accede a sesiones personalizadas, planes de estudio y apoyo constante para avanzar con confianza.
                    </p>
                </div>

                <div class="relative grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                        <p class="text-2xl font-black text-white">1.2k+</p>
                        <p class="mt-1 text-xs text-slate-300">estudiantes activos</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                        <p class="text-2xl font-black text-white">4.9/5</p>
                        <p class="mt-1 text-xs text-slate-300">calificación media</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                        <p class="text-2xl font-black text-white">24/7</p>
                        <p class="mt-1 text-xs text-slate-300">soporte en línea</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 sm:p-8 lg:p-10">
                <div class="mb-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600">
                        Iniciar sesión
                    </p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">
                        Accede a tu cuenta
                    </h2>
                </div>

                <form id="login-form" action="/api/auth/login" method="post" class="space-y-5">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

                    <div>
                        <label for="login-email" class="profego-label">Correo electrónico</label>
                        <input
                            id="login-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            class="profego-input"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <div>
                        <label for="login-password" class="profego-label">Contraseña</label>
                        <input
                            id="login-password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="profego-input"
                            placeholder="••••••••"
                        >
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="inline-flex items-center gap-2 text-slate-600">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Recordarme
                        </label>
                        <a href="/recover" class="font-semibold text-indigo-600 hover:text-indigo-500">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <button type="submit" class="profego-button w-full">
                        Iniciar sesión
                    </button>

                    <p id="login-error" class="profego-form-error" role="alert" hidden></p>
                </form>

                <p class="mt-6 text-center text-sm text-slate-600">
                    ¿No tienes cuenta?
                    <a href="/register" class="font-semibold text-indigo-600 hover:text-indigo-500">
                        Regístrate aquí
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const error = document.getElementById('login-error');
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            error.hidden = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        email: form.elements.email.value,
                        password: form.elements.password.value
                    })
                });

                const payload = await response.json();

                if (!response.ok || payload.status !== 'success') {
                    throw new Error(payload.message || 'No se pudo iniciar sesión.');
                }

                window.location.assign(form.elements.redirect.value);
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
