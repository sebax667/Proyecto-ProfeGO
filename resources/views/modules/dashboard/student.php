<?php

declare(strict_types=1);

$pageTitle = 'Dashboard | ProfeGo';
$pageContent = <<<'HTML'
<section class="profego-section">
    <p class="profego-navbar__eyebrow">Panel del estudiante</p>
    <h1 class="profego-page-title">Tu aprendizaje en un solo lugar</h1>
    <p class="profego-page-lead">Explora tutorías, revisa tus sesiones y encuentra apoyo para tus próximos objetivos.</p>
    <a class="profego-button" href="/catalog">Agendar Tutoría</a>
</section>
HTML;

require_once __DIR__ . '/../../layouts/app.php';
