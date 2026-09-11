<?php
/** @var App\Shared\DTOs\TutorProfileDTO[] $tutors */
$q = $_GET['q'] ?? '';
$modality = $_GET['modality'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'rating';
$hasFilters = !empty($_GET);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Tutores | ProfeGo</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
    <?php include __DIR__ . '/../../partials/navigation.php'; ?>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="mb-8 rounded-[32px] border border-slate-200 bg-gradient-to-r from-indigo-600 via-indigo-500 to-blue-500 p-6 text-white shadow-[0_28px_64px_-38px_rgba(79,70,229,0.9)] sm:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-100">
                        Encuentra tu tutor ideal
                    </p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Aprende con personas que te acompañen de verdad.
                    </h1>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium">
                        +1200 tutores
                    </span>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium">
                        4.9 promedio
                    </span>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium">
                        Respuestas rápidas
                    </span>
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-8 lg:flex-row">
            <aside class="w-full lg:w-80">
                <form action="/catalog" method="GET" class="sticky top-6 rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_22px_48px_-32px_rgba(15,23,42,0.45)]">
                    <div class="mb-5 flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">⚙</span>
                        <h2 class="text-lg font-black text-slate-900">Filtros</h2>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label for="q" class="profego-label">Palabra clave</label>
                            <input
                                type="text"
                                name="q"
                                id="q"
                                value="<?= htmlspecialchars($q) ?>"
                                placeholder="Ej. Álgebra, Python, programación..."
                                class="profego-input"
                            >
                        </div>

                        <div>
                            <label for="modality" class="profego-label">Modalidad</label>
                            <select name="modality" id="modality" class="profego-input">
                                <option value="">Cualquiera</option>
                                <option value="virtual" <?= $modality === 'virtual' ? 'selected' : '' ?>>Virtual</option>
                                <option value="presential" <?= $modality === 'presential' ? 'selected' : '' ?>>Presencial</option>
                                <option value="hybrid" <?= $modality === 'hybrid' ? 'selected' : '' ?>>Híbrido</option>
                            </select>
                        </div>

                        <div>
                            <label class="profego-label">Precio por hora (COP)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    type="number"
                                    name="min_price"
                                    placeholder="Min"
                                    value="<?= htmlspecialchars($minPrice) ?>"
                                    class="profego-input"
                                >
                                <input
                                    type="number"
                                    name="max_price"
                                    placeholder="Max"
                                    value="<?= htmlspecialchars($maxPrice) ?>"
                                    class="profego-input"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="sort_by" class="profego-label">Ordenar por</label>
                            <select name="sort_by" id="sort_by" class="profego-input">
                                <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Mejor calificados</option>
                                <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Menor precio</option>
                                <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Mayor precio</option>
                            </select>
                        </div>

                        <button type="submit" class="profego-button w-full">
                            Aplicar filtros
                        </button>

                        <?php if ($hasFilters): ?>
                            <a href="/catalog" class="profego-button-secondary w-full">
                                Limpiar búsqueda
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </aside>

            <main class="flex-1">
                <?php if (!empty($tutors)): ?>
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($tutors as $tutor): ?>
                            <article class="group flex h-full flex-col justify-between rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_22px_48px_-32px_rgba(15,23,42,0.45)] transition hover:-translate-y-0.5 hover:shadow-[0_28px_60px_-30px_rgba(79,70,229,0.35)]">
                                <div>
                                    <div class="flex items-start gap-3">
                                        <img
                                            src="<?= htmlspecialchars($tutor->user->avatarUrl ?? 'https://ui-avatars.com/api/?name=' . urlencode($tutor->user->name)) ?>"
                                            alt="<?= htmlspecialchars($tutor->user->name) ?>"
                                            class="h-14 w-14 rounded-full object-cover ring-4 ring-indigo-50"
                                        >
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-black text-slate-900">
                                                <?= htmlspecialchars($tutor->user->name) ?>
                                            </h3>
                                            <p class="mt-1 text-sm text-slate-500">
                                                <?= htmlspecialchars($tutor->city ?? 'Remoto') ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-sm font-semibold text-indigo-600">
                                            <?= htmlspecialchars($tutor->headline) ?>
                                        </p>
                                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                                            <?= htmlspecialchars($tutor->bio) ?>
                                        </p>
                                    </div>

                                    <?php if (!empty($tutor->subjects)): ?>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <?php foreach ($tutor->subjects as $subject): ?>
                                                <span class="rounded-full bg-indigo-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                                    <?= htmlspecialchars($subject) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-5 border-t border-slate-200 pt-4">
                                    <div class="flex items-end justify-between">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                                Por hora
                                            </p>
                                            <p class="mt-1 text-xl font-black text-slate-900">
                                                $<?= number_format($tutor->hourlyRate, 0, ',', '.') ?>
                                            </p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-bold text-amber-500">
                                                ★ <?= number_format($tutor->ratingAvg, 1) ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500">
                                                (<?= $tutor->reviewsCount ?> reseñas)
                                            </p>
                                        </div>
                                    </div>

                                    <button type="button" class="profego-button mt-4 w-full">
                                        Ver perfil
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-[28px] border border-slate-200 bg-white p-16 text-center shadow-[0_22px_48px_-32px_rgba(15,23,42,0.45)]">
                        <h3 class="text-xl font-black text-slate-900">No encontramos coincidencias</h3>
                        <p class="mt-3 text-sm text-slate-500">
                            Prueba con otros filtros o vuelve a ver todos los tutores disponibles.
                        </p>
                        <a href="/catalog" class="profego-button mt-6">
                            Ver todos los tutores
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </main>

    <?php include __DIR__ . '/../../components/ai-widget.php'; ?>
    <?php include __DIR__ . '/../../partials/footer.php'; ?>
</body>
</html>
