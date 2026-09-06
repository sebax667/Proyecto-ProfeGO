<?php

declare(strict_types=1);

$pageTitle = 'Dashboard | ProfeGo';
$pageContent = <<<'HTML'
<section class="profego-section">
    <p class="profego-navbar__eyebrow">Panel del tutor</p>
    <h1 class="profego-page-title">Gestiona tus tutorías</h1>
    <p class="profego-page-lead">Consulta tus sesiones y mantén disponible tu agenda para nuevos estudiantes.</p>
    <a class="profego-button" href="/settings">Configurar disponibilidad</a>
</section>
HTML;

require_once __DIR__ . '/../../layouts/app.php';
