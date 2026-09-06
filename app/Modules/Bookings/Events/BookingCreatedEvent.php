<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Events;

use App\Shared\Events\Event;

/**
 * Evento disparado cuando se crea una nueva reserva de tutoría.
 * Contiene la información esencial de la reserva para que los listeners puedan actuar.
 */
final class BookingCreatedEvent implements Event
{
    /**
     * @param int $bookingId ID de la reserva creada
     * @param int $tutorId ID del tutor
     * @param int $studentId ID del estudiante
     * @param string $meetingLink URL/Link de la reunión
     * @param array<string, mixed> $bookingData Datos completos de la reserva
     */
    public function __construct(
        private readonly int $bookingId,
        private readonly int $tutorId,
        private readonly int $studentId,
        private readonly string $meetingLink,
        private readonly array $bookingData = []
    ) {}

    public function getEventName(): string
    {
        return self::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'tutor_id' => $this->tutorId,
            'student_id' => $this->studentId,
            'meeting_link' => $this->meetingLink,
            'booking_data' => $this->bookingData,
        ];
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getTutorId(): int
    {
        return $this->tutorId;
    }

    public function getStudentId(): int
    {
        return $this->studentId;
    }

    public function getMeetingLink(): string
    {
        return $this->meetingLink;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBookingData(): array
    {
        return $this->bookingData;
    }
}
