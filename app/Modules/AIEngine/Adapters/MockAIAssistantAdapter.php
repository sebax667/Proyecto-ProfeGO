<?php

declare(strict_types=1);

namespace App\Modules\AIEngine\Adapters;

use App\Modules\AIEngine\Contracts\AIAssistantInterface;

/**
 * Mock AI Assistant Adapter
 * 
 * Proporciona respuestas simuladas de IA con recomendaciones basadas en palabras clave.
 * Simula latencia de red con usleep y filtra por temas específicos.
 */
final class MockAIAssistantAdapter implements AIAssistantInterface
{
    /**
     * Palabras clave válidas para procesamiento de recomendaciones
     * 
     * @var array<string>
     */
    private const VALID_KEYWORDS = ['cálculo', 'programación', 'algebra', 'geometria', 'fisica'];

    /**
     * Recomendaciones de tutores por palabra clave
     * 
     * @var array<string, array<string, mixed>>
     */
    private const RECOMMENDATIONS = [
        'cálculo' => [
            'tutors' => [
                [
                    'id' => 1,
                    'name' => 'Dr. Carlos Martínez',
                    'specialty' => 'Cálculo Diferencial e Integral',
                    'rating' => 4.9,
                    'hourly_rate' => 35.00,
                    'experience_years' => 12,
                    'confidence' => 0.95,
                ],
                [
                    'id' => 2,
                    'name' => 'Ing. María González',
                    'specialty' => 'Cálculo Multivariable',
                    'rating' => 4.7,
                    'hourly_rate' => 30.00,
                    'experience_years' => 8,
                    'confidence' => 0.88,
                ],
            ],
            'message' => 'He encontrado tutores especializados en Cálculo. Te recomiendo empezar con ejercicios de límites y derivadas.',
        ],
        'programación' => [
            'tutors' => [
                [
                    'id' => 3,
                    'name' => 'Ing. Juan Pérez',
                    'specialty' => 'Python y Algoritmos',
                    'rating' => 4.8,
                    'hourly_rate' => 32.00,
                    'experience_years' => 10,
                    'confidence' => 0.93,
                ],
                [
                    'id' => 4,
                    'name' => 'Lic. Ana Rodríguez',
                    'specialty' => 'JavaScript y Frontend',
                    'rating' => 4.6,
                    'hourly_rate' => 28.00,
                    'experience_years' => 7,
                    'confidence' => 0.85,
                ],
            ],
            'message' => 'Tengo recomendaciones de expertos en programación. ¿Qué lenguaje te interesa aprender?',
        ],
        'algebra' => [
            'tutors' => [
                [
                    'id' => 5,
                    'name' => 'Prof. David López',
                    'specialty' => 'Álgebra Lineal',
                    'rating' => 4.7,
                    'hourly_rate' => 26.00,
                    'experience_years' => 9,
                    'confidence' => 0.89,
                ],
            ],
            'message' => 'He identificado tutores en Álgebra. Te ayudarán con ecuaciones, matrices y espacios vectoriales.',
        ],
        'geometria' => [
            'tutors' => [
                [
                    'id' => 6,
                    'name' => 'Arq. Patricia Gómez',
                    'specialty' => 'Geometría y Trigonometría',
                    'rating' => 4.5,
                    'hourly_rate' => 24.00,
                    'experience_years' => 6,
                    'confidence' => 0.82,
                ],
            ],
            'message' => 'Tutores especializados en Geometría disponibles. Perfecto para figuras, ángulos y espacios.',
        ],
        'fisica' => [
            'tutors' => [
                [
                    'id' => 7,
                    'name' => 'Dr. Roberto Sánchez',
                    'specialty' => 'Física Clásica y Moderna',
                    'rating' => 4.8,
                    'hourly_rate' => 34.00,
                    'experience_years' => 11,
                    'confidence' => 0.92,
                ],
            ],
            'message' => 'Expertos en Física disponibles. Pueden ayudarte con mecánica, termodinámica y electromagnetismo.',
        ],
    ];

    /**
     * Procesa una consulta y retorna recomendaciones de tutores
     * 
     * Simula latencia y filtra recomendaciones basadas en palabras clave detectadas.
     * 
     * @param string $query Consulta del usuario
     * @return array<string, mixed> Respuesta con recomendaciones
     */
    public function getRecommendations(string $query): array
    {
        // Simular latencia de red (500-1500ms)
        usleep(random_int(500000, 1500000));

        $normalizedQuery = $this->normalize(trim($query));

        // Detectar palabra clave más relevante
        $matchedKeyword = $this->detectKeyword($normalizedQuery);

        if ($matchedKeyword === null) {
            return $this->getDefaultResponse($normalizedQuery);
        }

        $recommendation = self::RECOMMENDATIONS[$matchedKeyword] ?? [];

        return [
            'status' => 'success',
            'keyword_matched' => $matchedKeyword,
            'message' => $recommendation['message'] ?? 'Te puedo ayudar a encontrar tutores.',
            'tutors' => $recommendation['tutors'] ?? [],
            'count' => count($recommendation['tutors'] ?? []),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Detecta la palabra clave más relevante en la consulta
     * 
     * @param string $normalizedQuery Consulta normalizada en minúsculas
     * @return string|null Palabra clave detectada o null si no hay coincidencia
     */
    private function detectKeyword(string $normalizedQuery): ?string
    {
        foreach (self::VALID_KEYWORDS as $keyword) {
            if (stripos($normalizedQuery, $keyword) !== false) {
                return $keyword;
            }

        }

        return null;
    }

    private function normalize(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $transliterated === false ? $value : $transliterated;
        return strtr(strtolower($value), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'Ñ' => 'n',
        ]);
    }

    /**
     * Retorna una respuesta por defecto cuando no se detecta palabra clave
     * 
     * @param string $query Consulta original
     * @return array<string, mixed> Respuesta genérica
     */
    private function getDefaultResponse(string $query): array
    {
        return [
            'status' => 'success',
            'keyword_matched' => null,
            'message' => 'Tu consulta no coincide con nuestras especialidades. '
                . 'Intenta preguntar por: cálculo, programación, algebra, geometria o fisica.',
            'tutors' => [],
            'count' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Valida si una consulta es válida
     * 
     * @param string $query
     * @return bool
     */
    public function isValidQuery(string $query): bool
    {
        $trimmed = trim($query);
        return !empty($trimmed) && strlen($trimmed) >= 3 && strlen($trimmed) <= 500;
    }

    /**
     * Retorna la lista de palabras clave válidas
     * 
     * @return array<string>
     */
    public function getValidKeywords(): array
    {
        return self::VALID_KEYWORDS;
    }
}
