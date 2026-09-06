<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

interface VideoConferenceAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createMeeting(array $payload): array;
}
