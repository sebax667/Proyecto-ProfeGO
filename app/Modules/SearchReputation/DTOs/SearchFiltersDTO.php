<?php

declare(strict_types=1);

namespace App\Modules\SearchReputation\DTOs;

use App\Shared\Enums\Modality;

readonly class SearchFiltersDTO
{
    public function __construct(
        public ?string $query = null,
        public ?string $subject = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?Modality $modality = null,
        public ?string $city = null,
        public ?float $minRating = null,
        public string $sortBy = 'rating',
    ) {}

    public static function fromRequest(array $params): self
    {
        return new self(
            query: isset($params['q']) ? trim((string) $params['q']) : null,
            subject: isset($params['subject']) ? trim((string) $params['subject']) : null,
            minPrice: isset($params['min_price']) && $params['min_price'] !== '' ? (float)$params['min_price'] : null,
            maxPrice: isset($params['max_price']) && $params['max_price'] !== '' ? (float)$params['max_price'] : null,
            modality: !empty($params['modality']) ? Modality::tryFrom($params['modality']) : null,
            city: isset($params['city']) ? trim((string) $params['city']) : null,
            minRating: isset($params['min_rating']) && $params['min_rating'] !== '' ? (float)$params['min_rating'] : null,
            sortBy: $params['sort_by'] ?? 'rating',
        );
    }
}