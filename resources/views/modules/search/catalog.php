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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen font-sans text-gray-800">

<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Encabezado -->
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Encuentra a tu Tutor Ideal</h1>
            <p class="mt-2 text-base text-gray-600">Explora perfiles, filtra por tus necesidades y conecta con los mejores profesionales.</p>
        </div>

        <div class="flex flex-col md:flex-row gap-8">
            
            <!-- BARRA LATERAL (Filtros) -->
            <aside class="w-full md:w-1/4">
                <form action="/catalog" method="GET" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 md:sticky md:top-6">
                    <h2 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        Filtros
                    </h2>

                    <!-- Búsqueda -->
                    <div class="mb-5">
                        <label for="q" class="block text-sm font-medium text-gray-700 mb-1">Palabra clave</label>
                        <input type="text" name="q" id="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ej. Álgebra, Python..." 
                               class="w-full rounded-lg border border-gray-300 p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <!-- Modalidad -->
                    <div class="mb-5">
                        <label for="modality" class="block text-sm font-medium text-gray-700 mb-1">Modalidad</label>
                        <select name="modality" id="modality" class="w-full rounded-lg border border-gray-300 p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Cualquiera</option>
                            <option value="virtual" <?= $modality === 'virtual' ? 'selected' : '' ?>>Virtual</option>
                            <option value="presential" <?= $modality === 'presential' ? 'selected' : '' ?>>Presencial</option>
                            <option value="hybrid" <?= $modality === 'hybrid' ? 'selected' : '' ?>>Híbrido</option>
                        </select>
                    </div>

                    <!-- Precio -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio por hora (COP)</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" placeholder="Min" value="<?= htmlspecialchars($minPrice) ?>" class="w-full border border-gray-300 rounded-lg p-2 text-sm">
                            <span class="text-gray-400">-</span>
                            <input type="number" name="max_price" placeholder="Max" value="<?= htmlspecialchars($maxPrice) ?>" class="w-full border border-gray-300 rounded-lg p-2 text-sm">
                        </div>
                    </div>

                    <!-- Ordenar -->
                    <div class="mb-7">
                        <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Ordenar por</label>
                        <select name="sort_by" id="sort_by" class="w-full rounded-lg border border-gray-300 p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Mejor calificados</option>
                            <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Menor precio</option>
                            <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Mayor precio</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-all">
                        Aplicar Filtros
                    </button>
                    
                    <?php if ($hasFilters): ?>
                        <a href="/catalog" class="mt-3 block text-center w-full py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl text-sm font-medium transition-all">
                            Limpiar búsqueda
                        </a>
                    <?php endif; ?>
                </form>
            </aside>

            <!-- ZONA PRINCIPAL (Grid de Resultados) -->
            <main class="w-full md:w-3/4">
                <?php if (!empty($tutors)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($tutors as $tutor): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center gap-4 mb-4">
                                        <img src="<?= htmlspecialchars($tutor->user->avatarUrl ?? 'https://ui-avatars.com/api/?name=' . urlencode($tutor->user->name)) ?>" 
                                             alt="<?= htmlspecialchars($tutor->user->name) ?>" class="w-12 h-12 rounded-full object-cover">
                                        <div>
                                            <h3 class="font-bold text-gray-900"><?= htmlspecialchars($tutor->user->name) ?></h3>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($tutor->city ?? 'Remoto') ?></p>
                                        </div>
                                    </div>
                                    <h4 class="text-sm font-semibold text-indigo-600 mb-2"><?= htmlspecialchars($tutor->headline) ?></h4>
                                    <p class="text-xs text-gray-600 mb-4 line-clamp-3"><?= htmlspecialchars($tutor->bio) ?></p>
                                    
                                    <?php if (!empty($tutor->subjects)): ?>
                                        <div class="flex flex-wrap gap-1 mb-4">
                                            <?php foreach ($tutor->subjects as $subject): ?>
                                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-medium rounded-md">
                                                    <?= htmlspecialchars($subject) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="border-t border-gray-100 pt-4 mt-2 flex items-center justify-between">
                                    <div>
                                        <span class="text-xs text-gray-400">Por hora</span>
                                        <p class="text-base font-bold text-gray-900">$<?= number_format($tutor->hourlyRate, 0, ',', '.') ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold text-amber-500">★ <?= number_format($tutor->ratingAvg, 1) ?></span>
                                        <p class="text-[10px] text-gray-400">(<?= $tutor->reviewsCount ?> reseñas)</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-gray-100 p-16 text-center shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">No encontramos coincidencias</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">No hay tutores que cumplan con los filtros actuales.</p>
                        <a href="/catalog" class="mt-6 inline-block px-5 py-2.5 bg-indigo-100 text-indigo-700 rounded-xl text-sm font-medium hover:bg-indigo-200 transition-all">
                            Ver todos los tutores
                        </a>
                    </div>
                <?php endif; ?>
            </main>

        </div>
    </div>
</div>

</body>
</html>