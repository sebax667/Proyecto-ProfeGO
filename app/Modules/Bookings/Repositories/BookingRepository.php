<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Repositories;

use App\Modules\Bookings\DTOs\CreateBookingDTO;
use App\Modules\Bookings\Enums\BookingStatus;
use PDO;

final class BookingRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function tutorExists(int $tutorId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tutor_profiles WHERE id = :tutor_id LIMIT 1');
        $stmt->execute([':tutor_id' => $tutorId]);

        return $stmt->fetchColumn() !== false;
    }

    public function studentExists(int $studentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :student_id LIMIT 1');
        $stmt->execute([':student_id' => $studentId]);

        return $stmt->fetchColumn() !== false;
    }

    public function hasTutorAvailability(int $tutorId, string $startsAt, string $endsAt): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM tutor_availabilities
             WHERE tutor_id = :tutor_id
               AND start_at <= :starts_at
               AND end_at >= :ends_at
             LIMIT 1'
        );

        $stmt->execute([
            ':tutor_id' => $tutorId,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function hasActiveOverlap(int $tutorId, string $startsAt, string $endsAt): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id
             FROM bookings
             WHERE tutor_id = :tutor_id
               AND status IN (:pending, :confirmed)
               AND starts_at < :ends_at
               AND ends_at > :starts_at
             LIMIT 1'
        );

        $stmt->execute([
            ':tutor_id' => $tutorId,
            ':pending' => BookingStatus::PENDING->value,
            ':confirmed' => BookingStatus::CONFIRMED->value,
            ':starts_at' => $startsAt,
            ':ends_at' => $endsAt,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(CreateBookingDTO $dto, float $totalPrice, string $meetingId, string $meetingUrl): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bookings (
                tutor_id,
                student_id,
                starts_at,
                ends_at,
                status,
                hourly_rate,
                total_price,
                title,
                notes,
                meeting_type,
                meeting_id,
                meeting_url,
                created_at
            ) VALUES (
                :tutor_id,
                :student_id,
                :starts_at,
                :ends_at,
                :status,
                :hourly_rate,
                :total_price,
                :title,
                :notes,
                :meeting_type,
                :meeting_id,
                :meeting_url,
                datetime("now")
            )'
        );

        $stmt->execute([
            ':tutor_id' => $dto->tutorId,
            ':student_id' => $dto->studentId,
            ':starts_at' => $dto->startsAt,
            ':ends_at' => $dto->endsAt,
            ':status' => BookingStatus::PENDING->value,
            ':hourly_rate' => $dto->hourlyRate,
            ':total_price' => $totalPrice,
            ':title' => $dto->title,
            ':notes' => $dto->notes,
            ':meeting_type' => $dto->meetingType ?? 'video',
            ':meeting_id' => $meetingId,
            ':meeting_url' => $meetingUrl,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $bookingId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bookings WHERE id = :booking_id LIMIT 1'
        );
        $stmt->execute([':booking_id' => $bookingId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateStatus(int $bookingId, BookingStatus $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE bookings SET status = :status WHERE id = :booking_id'
        );

        return $stmt->execute([
            ':status' => $status->value,
            ':booking_id' => $bookingId,
        ]);
    }

    public function cancel(int $bookingId, ?string $reason = null): bool
    {
        $notes = $reason ?? 'Reserva cancelada por el usuario';

        $stmt = $this->pdo->prepare(
            'UPDATE bookings SET status = :status, notes = :notes WHERE id = :booking_id'
        );

        return $stmt->execute([
            ':status' => BookingStatus::CANCELLED->value,
            ':notes' => $notes,
            ':booking_id' => $bookingId,
        ]);
    }
}

