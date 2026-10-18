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

                <form id="register-form" action="/api/auth/register" method="post" class="space-y-5" novalidate>
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
                        <p id="name-msg" class="mt-1 text-xs text-red-600" aria-live="polite"></p>
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
                        <p id="email-msg" class="mt-1 text-xs text-red-600" aria-live="polite"></p>
                    </div>

                    <div>
                        <label for="register-password" class="profego-label">Contraseña</label>
                        <input
                            id="register-password"
                            name="password"
                            type="password"
                            required
                            minlength="10"
                            autocomplete="new-password"
                            class="profego-input"
                            placeholder="Mínimo 10 caracteres"
                        >
                        <p id="password-msg" class="mt-1 text-xs text-red-600" aria-live="polite"></p>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" name="terms" required>
                            <span>
                                Acepto los <a href="#" id="open-terms" class="font-semibold text-indigo-600 hover:text-indigo-500">términos y condiciones</a>
                            </span>
                        </label>
                        <p id="terms-msg" class="text-xs text-red-600" aria-live="polite"></p>
                    </div>

                    <button type="submit" class="profego-button w-full">
                        Registrarme
                    </button>

                    <p id="register-error" class="profego-form-error" role="alert" hidden></p>
                </form>

                <dialog id="terms-dialog" class="max-w-lg rounded-3xl p-0 shadow-2xl">
                    <div class="p-6">
                        <h2 class="text-2xl font-black text-slate-900">Términos y condiciones</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            Al crear una cuenta en ProfeGo aceptas usar la plataforma conforme a nuestra política de privacidad,
                            la protección de datos del usuario y el uso responsable de la información personal compartida con tutores.
                            Tu registro se usa únicamente para gestionar tus sesiones, la seguridad del acceso y la comunicación dentro
                            de la plataforma. Nos reservamos el derecho a bloquear actividades sospechosas o fraudulentas.
                        </p>
                        <button type="button" id="close-terms" class="profego-button-secondary mt-6 w-full">
                            Cerrar
                        </button>
                    </div>
                </dialog>

                <p class="mt-6 text-center text-sm text-slate-600">
                    ¿Ya tienes cuenta?
                    <a href="/login" class="font-semibold text-indigo-600 hover:text-indigo-500">
                        Inicia sesión
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="/assets/js/register.js" defer></script>
</body>
</html>
