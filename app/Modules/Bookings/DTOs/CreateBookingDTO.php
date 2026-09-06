<?php

declare(strict_types=1);

namespace App\Modules\Bookings\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

readonly class CreateBookingDTO
{
    public function __construct(
        public int $tutorId,
        public int $studentId,
        public string $startsAt,
        public string $endsAt,
        public float $hourlyRate,
        public ?string $title = null,
        public ?string $notes = null,
        public ?string $meetingType = null,
    ) {}

    public static function fromRequest(array $request): self
    {
        $tutorId = isset($request['tutor_id']) ? (int) $request['tutor_id'] : 0;
        $studentId = isset($request['student_id']) ? (int) $request['student_id'] : 0;
        $startsAt = trim((string) ($request['starts_at'] ?? ''));
        $endsAt = trim((string) ($request['ends_at'] ?? ''));
        $hourlyRate = isset($request['hourly_rate']) ? (float) $request['hourly_rate'] : 0.0;
        $title = isset($request['title']) ? trim((string) $request['title']) : null;
        $notes = isset($request['notes']) ? trim((string) $request['notes']) : null;
        $meetingType = isset($request['meeting_type']) ? trim((string) $request['meeting_type']) : null;

        if ($tutorId <= 0) {
            throw new InvalidArgumentException('El campo tutor_id es obligatorio y debe ser válido.');
        }

        if ($studentId <= 0) {
            throw new InvalidArgumentException('El campo student_id es obligatorio y debe ser válido.');
        }

        if ($startsAt === '' || $endsAt === '') {
            throw new InvalidArgumentException('Los campos starts_at y ends_at son obligatorios.');
        }

        $startDate = new DateTimeImmutable($startsAt);
        $endDate = new DateTimeImmutable($endsAt);

        if ($endDate <= $startDate) {
            throw new InvalidArgumentException('La fecha de fin debe ser mayor que la de inicio.');
        }

        if ($hourlyRate <= 0) {
            throw new InvalidArgumentException('El campo hourly_rate debe ser mayor a cero.');
        }

        return new self(
            tutorId: $tutorId,
            studentId: $studentId,
            startsAt: $startDate->format(DATE_ATOM),
            endsAt: $endDate->format(DATE_ATOM),
            hourlyRate: $hourlyRate,
            title: $title !== '' ? $title : null,
            notes: $notes !== '' ? $notes : null,
            meetingType: $meetingType !== '' ? $meetingType : null,
        );
    }
}
