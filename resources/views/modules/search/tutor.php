<?php

declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$name = $escape($tutor->user->name);
$headline = $escape($tutor->headline);
$bio = $escape($tutor->bio);
$city = $escape($tutor->city ?? 'Remoto');
$modality = match ($tutor->modality) {
    'virtual' => 'Virtual',
    'presential' => 'Presencial',
    'hybrid' => 'Híbrida',
    default => $escape($tutor->modality),
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $name ?> | ProfeGo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <?php include __DIR__ . '/../../partials/navigation.php'; ?>

    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <article class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-xl sm:p-10">
            <a href="/catalog" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">← Volver al catálogo</a>
            <p class="mt-8 text-sm font-semibold uppercase tracking-widest text-indigo-600"><?= $headline ?></p>
            <h1 class="mt-3 text-3xl font-black text-slate-900 sm:text-4xl"><?= $name ?></h1>
            <p class="mt-3 text-slate-600"><?= $city ?> · <?= $modality ?></p>
            <p class="mt-6 whitespace-pre-line leading-7 text-slate-700"><?= $bio ?></p>

            <?php if ($tutor->subjects !== []): ?>
                <div class="mt-6 flex flex-wrap gap-2" aria-label="Materias">
                    <?php foreach ($tutor->subjects as $subject): ?>
                        <?php if (is_string($subject) || is_numeric($subject)): ?>
                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
                                <?= $escape((string) $subject) ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                <p class="text-xl font-black text-slate-900">
                    $<?= number_format($tutor->hourlyRate, 0, ',', '.') ?> COP / hora
                </p>
                <a href="/chat" class="profego-button-secondary">Ir al chat</a>
            </div>
            <p class="mt-5 text-sm text-slate-500">Puedes solicitar una sesión desde la tarjeta de este tutor en el catálogo.</p>
        </article>
    </main>

    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
