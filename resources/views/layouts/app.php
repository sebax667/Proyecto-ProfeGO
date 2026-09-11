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
    <meta name="theme-color" content="#4f46e5">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="profego-shell">
    <div class="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col lg:flex-row">
        <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>

        <div class="flex min-w-0 flex-1 flex-col">
            <?php require_once __DIR__ . '/../partials/navbar.php'; ?>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <?= $pageContent ?>
                </div>
            </main>

            <?php require_once __DIR__ . '/../partials/footer.php'; ?>
        </div>
    </div>
</body>
</html>
