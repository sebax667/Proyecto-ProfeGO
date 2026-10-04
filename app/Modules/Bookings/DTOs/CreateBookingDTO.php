<?php

declare(strict_types=1);

namespace App\Modules\Bookings\DTOs;

use DateTimeImmutable;
use DateTimeZone;
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

        $startDate = self::normalizeToUtc($startsAt);
        $endDate = self::normalizeToUtc($endsAt);

        if ($endDate <= $startDate) {
            throw new InvalidArgumentException('La fecha de fin debe ser mayor que la de inicio.');
        }

        $durationHours = round(($endDate->getTimestamp() - $startDate->getTimestamp()) / 3600, 2);
        if ($durationHours > 4.0) {
            throw new InvalidArgumentException('La duración máxima por reserva es de 4 horas.');
        }

        if ($hourlyRate <= 0) {
            throw new InvalidArgumentException('El campo hourly_rate debe ser mayor a cero.');
        }

        return new self(
            tutorId: $tutorId,
            studentId: $studentId,
            startsAt: $startDate->format('Y-m-d\TH:i:s\Z'),
            endsAt: $endDate->format('Y-m-d\TH:i:s\Z'),
            hourlyRate: $hourlyRate,
            title: $title !== '' ? $title : null,
            notes: $notes !== '' ? $notes : null,
            meetingType: $meetingType !== '' ? $meetingType : null,
        );
    }

    private static function normalizeToUtc(string $value): DateTimeImmutable
    {
        $date = new DateTimeImmutable($value);
        $date = $date->setTimezone(new DateTimeZone('UTC'));

        return $date;
    }
}
