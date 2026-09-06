<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'ProfeGo';
$pageContent = $pageContent ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#304485">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Geist:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="profego-app">
    <div class="profego-shell">
        <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>

        <div class="profego-main">
            <?php require_once __DIR__ . '/../partials/navbar.php'; ?>
            <main class="profego-content">
                <?= $pageContent ?>
            </main>
            <?php require_once __DIR__ . '/../partials/footer.php'; ?>
        </div>
    </div>
</body>
</html>
