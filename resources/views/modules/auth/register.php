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
<body class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.18),_transparent_32%),linear-gradient(135deg,_#eef2ff_0%,_#f8fafc_42%,_#eef2ff_100%)]">
    <div class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-10">
        <div class="grid w-full overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-[0_35px_80px_-32px_rgba(79,70,229,0.38)] lg:grid-cols-[1.1fr_0.9fr]">
            <div class="relative hidden flex-col justify-between overflow-hidden bg-slate-950 p-8 text-white lg:flex">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(99,102,241,0.50),_transparent_35%)]"></div>

                <div class="relative">
                    <?php include __DIR__ . '/../../components/profego-brand.php'; ?>
                </div>

                <div class="relative">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-indigo-200">
                        Únete a ProfeGo
                    </p>
                    <h1 class="mt-4 text-4xl font-black tracking-tight">
                        Crea tu perfil y conecta con tus próximos tutores.
                    </h1>
                    <p class="mt-4 max-w-md text-base text-slate-300">
                        Organiza tus objetivos de aprendizaje, descubre perfiles alineados y empieza a avanzar con una experiencia clara y acompañada.
                    </p>
                </div>

                <div class="relative rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Qué obtienes</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-200">
                        <li>• Filtros inteligentes por materia y modalidad</li>
                        <li>• Perfiles con reseñas y experiencia verificada</li>
                        <li>• Soporte rápido y seguimiento del progreso</li>
                    </ul>
                </div>
            </div>

            <div class="bg-white p-6 sm:p-8 lg:p-10">
                <div class="mb-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600">
                        Crear cuenta
                    </p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900">
                        Empieza hoy mismo
                    </h2>
                </div>

                <form id="register-form" action="/api/auth/register" method="post" class="space-y-5">
                    <div>
                        <label for="register-name" class="profego-label">Nombre</label>
                        <input
                            id="register-name"
                            name="name"
                            required
                            autocomplete="name"
                            class="profego-input"
                            placeholder="Tu nombre completo"
                        >
                    </div>

                    <div>
                        <label for="register-email" class="profego-label">Correo electrónico</label>
                        <input
                            id="register-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            class="profego-input"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <div>
                        <label for="register-password" class="profego-label">Contraseña</label>
                        <input
                            id="register-password"
                            name="password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="profego-input"
                            placeholder="Mínimo 8 caracteres"
                        >
                    </div>

                    <button type="submit" class="profego-button w-full">
                        Registrarme
                    </button>

                    <p id="register-error" class="profego-form-error" role="alert" hidden></p>
                </form>

                <p class="mt-6 text-center text-sm text-slate-600">
                    ¿Ya tienes cuenta?
                    <a href="/login" class="font-semibold text-indigo-600 hover:text-indigo-500">
                        Inicia sesión
                    </a>
                </p>
            </div>
        </div>
    </div>

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
