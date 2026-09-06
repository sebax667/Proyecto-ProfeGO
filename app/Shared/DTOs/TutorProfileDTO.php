<?php

declare(strict_types=1);

namespace App\Shared\DTOs;

readonly class TutorProfileDTO
{
    public function __construct(
        public int $id,
        public string $headline,
        public string $bio,
        public float $hourlyRate,
        public float $ratingAvg,
        public int $reviewsCount,
        public string $modality,
        public ?string $city,
        public array $subjects,
        public UserDTO $user,
    ) {}

    public static function fromArray(array $data, UserDTO $user): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            headline: (string) ($data['headline'] ?? ''),
            bio: (string) ($data['bio'] ?? ''),
            hourlyRate: (float) ($data['hourly_rate'] ?? 0),
            ratingAvg: (float) ($data['rating_avg'] ?? 0),
            reviewsCount: (int) ($data['reviews_count'] ?? 0),
            modality: (string) ($data['modality'] ?? ''),
            city: $data['city'] ?? null,
            subjects: is_array($data['subjects'] ?? null) ? $data['subjects'] : [],
            user: $user,
        );
    }
}
