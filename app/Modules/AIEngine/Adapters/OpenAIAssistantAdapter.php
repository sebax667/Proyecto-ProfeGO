<?php

declare(strict_types=1);

namespace App\Modules\AIEngine\Adapters;

use App\Modules\AIEngine\Contracts\AIAssistantInterface;
use App\Modules\SearchReputation\DTOs\SearchFiltersDTO;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;
use App\Shared\DTOs\TutorProfileDTO;
use RuntimeException;

final class OpenAIAssistantAdapter implements AIAssistantInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly int $timeoutSeconds = 30,
        private readonly ?SearchTutorRepository $tutorRepository = null
    ) {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY no está configurada.');
        }
    }

    public function isValidQuery(string $query): bool
    {
        $length = strlen(trim($query));
        return $length >= 3 && $length <= 500;
    }

    public function getRecommendations(string $query): array
    {
        $payload = json_encode([
            'model' => $this->model,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres ProfeGo SmartMatch. Responde en español, de forma breve y útil. '
                        . 'Ayuda a identificar la materia, modalidad y necesidades del estudiante. '
                        . 'No inventes tutores ni datos de disponibilidad.',
                ],
                ['role' => 'user', 'content' => trim($query)],
            ],
        ], JSON_THROW_ON_ERROR);

        $curl = curl_init(self::ENDPOINT);
        if ($curl === false) {
            throw new RuntimeException('No se pudo inicializar el cliente HTTP de OpenAI.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $rawResponse = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            throw new RuntimeException('No se pudo conectar con OpenAI: ' . $curlError);
        }

        $response = json_decode($rawResponse, true);
        if (!is_array($response)) {
            throw new RuntimeException('OpenAI devolvió una respuesta JSON inválida.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = $response['error']['message'] ?? 'Error desconocido de OpenAI.';
            throw new RuntimeException('OpenAI respondió HTTP ' . $statusCode . ': ' . $message);
        }

        $message = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            throw new RuntimeException('OpenAI no devolvió contenido para la consulta.');
        }

        $tutors = $this->tutorRepository?->search(
            new SearchFiltersDTO(query: trim($query))
        ) ?? [];

        return [
            'keyword_matched' => null,
            'message' => trim($message),
            'tutors' => array_map(
                fn (TutorProfileDTO $tutor): array => [
                    'id' => $tutor->id,
                    'name' => $tutor->user->name,
                    'specialty' => $tutor->headline,
                    'rating' => $tutor->ratingAvg,
                    'hourly_rate' => $tutor->hourlyRate,
                    'city' => $tutor->city,
                    'modality' => $tutor->modality,
                    'subjects' => $tutor->subjects,
                ],
                $tutors
            ),
            'count' => count($tutors),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getValidKeywords(): array
    {
        return ['cálculo', 'programación', 'álgebra', 'geometría', 'física'];
    }
}
