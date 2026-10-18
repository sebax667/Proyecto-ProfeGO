<?php

declare(strict_types=1);

$user = $user ?? ['name' => 'Usuario'];
$escapedUserName = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
$bookings = $bookings ?? [];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Dashboard | ProfeGo';
$pageContent = <<<HTML
<section class="profego-section">
    <p class="profego-navbar__eyebrow">Panel del estudiante</p>
    <h1 class="profego-page-title">Hola, {$escapedUserName}</h1>
    <p class="profego-page-lead">Explora tutorías, revisa tus sesiones y encuentra apoyo para tus próximos objetivos.</p>
    <a class="profego-button" href="/catalog">Agendar Tutoría</a>
    <section class="profego-card mt-8 p-6" aria-labelledby="student-bookings-title">
        <h2 id="student-bookings-title" class="text-xl font-black text-slate-900">Mis sesiones</h2>
        <div class="mt-4 space-y-3">
HTML;
if ($bookings === []) {
    $pageContent .= '<p class="text-sm text-slate-500">Aún no tienes sesiones agendadas.</p>';
}
foreach ($bookings as $booking) {
    $tutorName = $escape((string) ($booking['tutor_name'] ?? 'Tutor'));
    $title = $escape((string) ($booking['title'] ?? 'Sesión de tutoría'));
    $startsAt = $escape((string) ($booking['starts_at'] ?? ''));
    $status = $escape((string) ($booking['status'] ?? ''));
    $pageContent .= <<<HTML
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="font-semibold text-slate-900">{$title}</h3>
                <p class="text-sm text-slate-600">Tutor: {$tutorName}</p>
                <p class="text-sm text-slate-500">{$startsAt} · {$status}</p>
            </article>
HTML;
}
$pageContent .= <<<'HTML'
        </div>
    </section>
</section>
HTML;

require_once __DIR__ . '/../../layouts/app.php';
