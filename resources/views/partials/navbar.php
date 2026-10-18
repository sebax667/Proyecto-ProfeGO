<?php

declare(strict_types=1);

$user = $user ?? ['name' => 'Usuario'];
$userName = (string) ($user['name'] ?? 'Usuario');
$escapedUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
$nameParts = preg_split('/\s+/u', trim($userName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $initials .= mb_strtoupper(mb_substr($namePart, 0, 1, 'UTF-8'), 'UTF-8');
}
$initials = $initials !== '' ? $initials : 'U';
?>
<header class="border-b border-slate-200 bg-white/90 backdrop-blur-sm">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                Espacio de aprendizaje
            </p>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900">
                Hola, <?= $escapedUserName ?>
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" disabled title="Notificaciones próximamente" class="flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400" aria-label="Notificaciones próximamente">
                🔔
            </button>

            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white shadow-sm shadow-indigo-500/20" aria-label="Perfil de <?= $escapedUserName ?>">
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</header>
