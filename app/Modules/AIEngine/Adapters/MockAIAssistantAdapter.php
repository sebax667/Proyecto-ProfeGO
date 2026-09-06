<?php

declare(strict_types=1);

namespace App\Modules\AIEngine\Adapters;

use App\Modules\AIEngine\Contracts\AIAssistantInterface;
use App\Modules\SearchReputation\DTOs\SearchFiltersDTO;
use App\Modules\SearchReputation\Repositories\SearchTutorRepository;
use App\Shared\DTOs\TutorProfileDTO;

final class MockAIAssistantAdapter implements AIAssistantInterface
{
    private const VALID_KEYWORDS = ['cálculo', 'programación', 'algebra', 'geometria', 'fisica'];

    public function __construct(
        private readonly SearchTutorRepository $tutorRepository
    ) {}

    public function getRecommendations(string $query): array
    {
        $normalizedQuery = $this->normalize(trim($query));
        $matchedKeyword = $this->detectKeyword($normalizedQuery);
        $tutors = $this->findMatchingTutors($normalizedQuery, $matchedKeyword);

        return [
            'keyword_matched' => $matchedKeyword,
            'message' => $this->buildMessage($matchedKeyword, count($tutors)),
            'tutors' => array_map(
                fn (TutorProfileDTO $tutor): array => $this->mapTutor($tutor),
                $tutors
            ),
            'count' => count($tutors),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function isValidQuery(string $query): bool
    {
        $length = strlen(trim($query));
        return $length >= 3 && $length <= 500;
    }

    public function getValidKeywords(): array
    {
        return self::VALID_KEYWORDS;
    }

    /**
     * @return array<int, TutorProfileDTO>
     */
    private function findMatchingTutors(string $normalizedQuery, ?string $matchedKeyword): array
    {
        $tutors = $this->tutorRepository->search(new SearchFiltersDTO());
        $terms = array_values(array_filter(
            preg_split('/\s+/', $normalizedQuery) ?: [],
            static fn (string $term): bool => strlen($term) >= 3
        ));

        return array_values(array_filter(
            $tutors,
            function (TutorProfileDTO $tutor) use ($terms, $matchedKeyword): bool {
                $content = $this->normalize(implode(' ', [
                    $tutor->user->name,
                    $tutor->headline,
                    $tutor->bio,
                    implode(' ', $tutor->subjects),
                ]));

                if ($matchedKeyword !== null && str_contains($content, $matchedKeyword)) {
                    return true;
                }

                foreach ($terms as $term) {
                    if (str_contains($content, $term)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    private function detectKeyword(string $normalizedQuery): ?string
    {
        foreach (self::VALID_KEYWORDS as $keyword) {
            if (str_contains($normalizedQuery, $this->normalize($keyword))) {
                return $this->normalize($keyword);
            }
        }

        return null;
    }

    private function buildMessage(?string $keyword, int $count): string
    {
        if ($count === 0) {
            return 'No encontré tutores que coincidan con tu búsqueda. Prueba con otra materia o habilidad.';
        }

        if ($keyword !== null) {
            return sprintf('Encontré %d tutor%s relacionado%s con %s en nuestro catálogo.',
                $count,
                $count === 1 ? '' : 'es',
                $count === 1 ? '' : 's',
                ucfirst($keyword)
            );
        }

        return sprintf('Encontré %d tutor%s que coinciden con tu búsqueda.',
            $count,
            $count === 1 ? '' : 'es'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTutor(TutorProfileDTO $tutor): array
    {
        return [
            'id' => $tutor->id,
            'name' => $tutor->user->name,
            'specialty' => $tutor->headline,
            'rating' => $tutor->ratingAvg,
            'hourly_rate' => $tutor->hourlyRate,
            'city' => $tutor->city,
            'modality' => $tutor->modality,
            'subjects' => $tutor->subjects,
        ];
    }

    private function normalize(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return strtolower($transliterated === false ? $value : $transliterated);
    }
}
