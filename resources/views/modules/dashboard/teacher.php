<?php

declare(strict_types=1);

$pageTitle = 'Dashboard | ProfeGo';
$pageContent = <<<'HTML'
<section class="space-y-8">
    <div class="rounded-[32px] border border-slate-200 bg-gradient-to-r from-indigo-600 to-blue-500 p-6 text-white shadow-[0_28px_64px_-38px_rgba(79,70,229,0.9)] sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-100">
                    Panel del tutor
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Gestiona tus tutorías con claridad
                </h1>
                <p class="mt-3 max-w-2xl text-base text-indigo-100">
                    Consulta sesiones, ajusta tu disponibilidad y mantén tu perfil listo para nuevas oportunidades.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="/settings" class="profego-button-secondary border-white/30 bg-white/10 text-white hover:bg-white/20">
                    Configurar disponibilidad
                </a>
                <a href="/catalog" class="profego-button-secondary border-white/30 bg-white text-indigo-700 hover:bg-indigo-50">
                    Ver catálogo
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="profego-kpi">
            <p class="text-sm text-slate-500">Sesiones este mes</p>
            <p class="mt-3 text-3xl font-black text-slate-900">32</p>
            <p class="mt-2 text-sm text-emerald-600">+18% respecto al mes pasado</p>
        </div>

        <div class="profego-kpi">
            <p class="text-sm text-slate-500">Estudiantes activos</p>
            <p class="mt-3 text-3xl font-black text-slate-900">18</p>
            <p class="mt-2 text-sm text-indigo-600">6 nuevos esta semana</p>
        </div>

        <div class="profego-kpi">
            <p class="text-sm text-slate-500">Calificación promedio</p>
            <p class="mt-3 text-3xl font-black text-slate-900">4.9</p>
            <p class="mt-2 text-sm text-amber-500">★ Excelente reputación</p>
        </div>

        <div class="profego-kpi">
            <p class="text-sm text-slate-500">Ingresos estimados</p>
            <p class="mt-3 text-3xl font-black text-slate-900">$1.240K</p>
            <p class="mt-2 text-sm text-slate-500">COP este mes</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
        <div class="profego-card p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Próximas sesiones</p>
                    <h2 class="mt-2 text-xl font-black text-slate-900">Agenda del tutor</h2>
                </div>

                <a href="/settings" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                    Ajustar horario
                </a>
            </div>

            <div class="mt-6 space-y-3">
                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <p class="font-semibold text-slate-900">Matemáticas - Álgebra</p>
                        <p class="text-sm text-slate-500">Hoy • 16:00 - 17:00 • Virtual</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        Confirmada
                    </span>
                </div>

                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <p class="font-semibold text-slate-900">Programación - PHP</p>
                        <p class="text-sm text-slate-500">Mañana • 10:00 - 11:30 • Presencial</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                        Pendiente
                    </span>
                </div>

                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <p class="font-semibold text-slate-900">Biología - Repaso general</p>
                        <p class="text-sm text-slate-500">Viernes • 14:00 - 15:00 • Virtual</p>
                    </div>
                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                        En revisión
                    </span>
                </div>
            </div>
        </div>

        <div class="profego-card p-6">
            <p class="text-sm font-semibold text-slate-500">Acciones rápidas</p>

            <div class="mt-6 space-y-3">
                <a href="/settings" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-slate-100">
                    <span class="font-medium text-slate-900">Actualizar disponibilidad</span>
                    <span class="text-slate-400">→</span>
                </a>

                <a href="/catalog" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-slate-100">
                    <span class="font-medium text-slate-900">Explorar estudiantes</span>
                    <span class="text-slate-400">→</span>
                </a>

                <a href="#ai-chat-widget" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-slate-100">
                    <span class="font-medium text-slate-900">Abrir asistente IA</span>
                    <span class="text-slate-400">→</span>
                </a>
            </div>
        </div>
    </div>
</section>
HTML;

require_once __DIR__ . '/../../layouts/app.php';
