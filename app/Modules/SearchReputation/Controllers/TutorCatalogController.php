<?php

declare(strict_types=1);

namespace App\Modules\SearchReputation\Controllers;

use App\Modules\SearchReputation\DTOs\SearchFiltersDTO;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;

class TutorCatalogController
{
    public function __construct(
        private SearchTutorRepository $repository
    ) {}

    public function index(): string
    {
        $filters = SearchFiltersDTO::fromRequest($_GET);
        $tutors = $this->repository->search($filters);

        // Renderizado PHP Nativo (limpio y sin dependencias externas)
        ob_start();
        $viewPath = __DIR__ . '/../../../../resources/views/modules/search/catalog.php';
        require $viewPath;
        return ob_get_clean();
    }

    public function show(array $request): string
    {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) {
            return $this->notFound();
        }

        $tutor = $this->repository->findById($id);
        if ($tutor === null) {
            return $this->notFound();
        }

        ob_start();
        $viewPath = __DIR__ . '/../../../../resources/views/modules/search/tutor.php';
        require $viewPath;
        return (string) ob_get_clean();
    }

    private function notFound(): string
    {
        http_response_code(404);
        return '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>404 | ProfeGo</title><h1>El perfil de tutor no está disponible.</h1>';
    }
}