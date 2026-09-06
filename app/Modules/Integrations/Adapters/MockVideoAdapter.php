<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Adapters;

use App\Modules\Integrations\Contracts\VideoConferenceAdapterInterface;

final class MockVideoAdapter implements VideoConferenceAdapterInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createMeeting(array $payload): array
    {
        $meetingId = 'mock-' . bin2hex(random_bytes(8));
        $joinUrl = 'https://mock-video.example/meetings/' . rawurlencode((string) $meetingId);

        return [
            'provider' => 'mock',
            'meeting_id' => $meetingId,
            'join_url' => $joinUrl,
            'status' => 'created',
            'payload' => $payload,
        ];
    }
}
