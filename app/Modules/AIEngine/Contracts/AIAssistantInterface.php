<?php

declare(strict_types=1);

namespace App\Modules\AIEngine\Contracts;

interface AIAssistantInterface
{
    public function isValidQuery(string $query): bool;

    /**
     * @return array<string, mixed>
     */
    public function getRecommendations(string $query): array;

    /**
     * @return array<int, string>
     */
    public function getValidKeywords(): array;
}
