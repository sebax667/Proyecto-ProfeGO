<?php

declare(strict_types=1);

$redirect = (string) ($_GET['redirect'] ?? '/dashboard');
$hasControlCharacters = preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1;
$redirect = trim($redirect);
if (
    $redirect === ''
    || !str_starts_with($redirect, '/')
    || str_starts_with($redirect, '//')
    || str_contains($redirect, '\\')
    || $hasControlCharacters
) {
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
                        <p class="text-lg font-black text-white">Tutorías 1:1</p>
                        <p class="mt-1 text-xs text-slate-300">Clases personalizadas por tema.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                        <p class="text-lg font-black text-white">Plan de estudio</p>
                        <p class="mt-1 text-xs text-slate-300">Seguimiento claro y adaptable.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                        <p class="text-lg font-black text-white">Soporte real</p>
                        <p class="mt-1 text-xs text-slate-300">Respuesta y acompañamiento en tu ritmo.</p>
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

    <script src="/assets/js/login.js" defer></script>
</body>
</html>
