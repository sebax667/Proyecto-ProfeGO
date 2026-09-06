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
}