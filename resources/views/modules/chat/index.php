<?php

declare(strict_types=1);

$pageTitle = 'Chat | ProfeGo';
$pageContent = <<<'HTML'
<section class="profego-section">
    <p class="profego-navbar__eyebrow">SmartMatch</p>
    <h1 class="profego-page-title">Chat con tu asistente</h1>
    <p class="profego-page-lead">Describe lo que necesitas aprender y recibe recomendaciones de tutores.</p>
    <a class="profego-button" href="/catalog#ai-chat-widget">Abrir SmartMatch</a>
</section>
HTML;

require_once __DIR__ . '/../../layouts/app.php';
