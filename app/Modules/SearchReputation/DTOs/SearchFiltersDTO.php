<?php

declare(strict_types=1);

namespace App\Modules\SearchReputation\DTOs;

use App\Shared\Enums\Modality;
use InvalidArgumentException;

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
        public ?int $subjectId = null,
        public ?int $levelId = null,
    ) {}

    public static function fromRequest(array $params): self
    {
        if (isset($params['subject']) && !is_string($params['subject'])) {
            throw new InvalidArgumentException('El filtro subject debe ser texto.');
        }
        $subject = isset($params['subject']) ? trim($params['subject']) : null;
        if ($subject === '') {
            $subject = null;
        }
        if ($subject !== null && mb_strlen($subject, 'UTF-8') > 100) {
            throw new InvalidArgumentException('El filtro subject debe contener entre 1 y 100 caracteres.');
        }

        return new self(
            query: isset($params['q']) ? trim((string) $params['q']) : null,
            subject: $subject,
            minPrice: isset($params['min_price']) && $params['min_price'] !== '' ? (float)$params['min_price'] : null,
            maxPrice: isset($params['max_price']) && $params['max_price'] !== '' ? (float)$params['max_price'] : null,
            modality: !empty($params['modality']) ? Modality::tryFrom($params['modality']) : null,
            city: isset($params['city']) ? trim((string) $params['city']) : null,
            minRating: isset($params['min_rating']) && $params['min_rating'] !== '' ? (float)$params['min_rating'] : null,
            sortBy: isset($params['sort_by']) && is_string($params['sort_by']) ? $params['sort_by'] : 'rating',
            subjectId: self::optionalPositiveId($params, 'subject_id'),
            levelId: self::optionalPositiveId($params, 'level_id'),
        );
    }

    private static function optionalPositiveId(array $params, string $key): ?int
    {
        if (!array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
            return null;
        }
        if (filter_var($params[$key], FILTER_VALIDATE_INT) === false || (int) $params[$key] <= 0) {
            throw new InvalidArgumentException('El filtro ' . $key . ' debe ser un entero positivo.');
        }

        return (int) $params[$key];
    }
}