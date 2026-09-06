<?php

declare(strict_types=1);

namespace App\Modules\AIEngine\Controllers;

use App\Modules\AIEngine\Adapters\MockAIAssistantAdapter;
use App\Modules\AIEngine\Contracts\AIAssistantInterface;
use InvalidArgumentException;

class AIController
{
    private readonly AIAssistantInterface $aiAdapter;

    public function __construct(?AIAssistantInterface $aiAdapter = null)
    {
        $this->aiAdapter = $aiAdapter ?? new MockAIAssistantAdapter();
    }

    /**
     * Maneja las consultas de IA y retorna recomendaciones
     * 
     * @param array<string, mixed> $request Parámetros de la petición
     * @param array<string, mixed>|null $authUser Usuario autenticado (opcional)
     * @return array<string, mixed> Respuesta JSON
     */
    public function chat(array $request, ?array $authUser = null): array
    {
        $query = trim($request['query'] ?? '');

        if (empty($query)) {
            return [
                'status' => 'error',
                'message' => 'La consulta no puede estar vacía.',
            ];
        }

        // Validar consulta
        if (!$this->aiAdapter->isValidQuery($query)) {
            return [
                'status' => 'error',
                'message' => 'La consulta debe tener entre 3 y 500 caracteres.',
            ];
        }

        try {
            // Procesar consulta con el adapter
            $recommendations = $this->aiAdapter->getRecommendations($query);

            return [
                'status' => 'success',
                'data' => $recommendations,
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'error',
                'message' => 'Error al procesar la consulta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retorna la lista de palabras clave válidas
     * 
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $authUser
     * @return array<string, mixed>
     */
    public function getKeywords(array $request, ?array $authUser = null): array
    {
        return [
            'status' => 'success',
            'keywords' => $this->aiAdapter->getValidKeywords(),
        ];
    }
}
