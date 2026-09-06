<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Services;

use App\Modules\Bookings\DTOs\CreateBookingDTO;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Events\BookingCreatedEvent;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Integrations\Adapters\MockVideoAdapter;
use App\Modules\Integrations\Contracts\VideoConferenceAdapterInterface;
use App\Shared\Events\EventDispatcher;
use DateTimeImmutable;
use RuntimeException;

class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly VideoConferenceAdapterInterface $videoAdapter = new MockVideoAdapter(),
        private readonly ?EventDispatcher $eventDispatcher = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(CreateBookingDTO $dto): array
    {
        if (!$this->bookingRepository->tutorExists($dto->tutorId)) {
            throw new RuntimeException('El tutor indicado no existe.');
        }

        if (!$this->bookingRepository->studentExists($dto->studentId)) {
            throw new RuntimeException('El estudiante indicado no existe.');
        }

        if (!$this->bookingRepository->hasTutorAvailability($dto->tutorId, $dto->startsAt, $dto->endsAt)) {
            throw new RuntimeException('El tutor no tiene disponibilidad para ese horario.');
        }

        if ($this->bookingRepository->hasActiveOverlap($dto->tutorId, $dto->startsAt, $dto->endsAt)) {
            throw new RuntimeException('El tutor ya tiene una reserva activa en ese rango horario.');
        }

        $durationHours = $this->calculateDurationHours($dto->startsAt, $dto->endsAt);
        $totalPrice = round($durationHours * $dto->hourlyRate, 2);

        $meeting = $this->videoAdapter->createMeeting([
            'tutor_id' => $dto->tutorId,
            'student_id' => $dto->studentId,
            'starts_at' => $dto->startsAt,
            'ends_at' => $dto->endsAt,
            'meeting_type' => $dto->meetingType ?? 'video',
        ]);

        $meetingId = (string) ($meeting['meeting_id'] ?? '');
        $meetingUrl = (string) ($meeting['join_url'] ?? '');

        $bookingId = $this->bookingRepository->create(
            $dto,
            $totalPrice,
            $meetingId,
            $meetingUrl
        );

        // Dispara el evento BookingCreatedEvent si el dispatcher está disponible
        if ($this->eventDispatcher !== null) {
            $event = new BookingCreatedEvent(
                $bookingId,
                $dto->tutorId,
                $dto->studentId,
                $meetingUrl,
                [
                    'starts_at' => $dto->startsAt,
                    'ends_at' => $dto->endsAt,
                    'hourly_rate' => $dto->hourlyRate,
                    'total_price' => $totalPrice,
                    'meeting_id' => $meetingId,
                    'status' => BookingStatus::PENDING->value,
                ]
            );
            $this->eventDispatcher->dispatch($event);
        }

        return [
            'status' => 'success',
            'created' => true,
            'message' => 'Reserva creada correctamente.',
            'booking' => [
                'id' => $bookingId,
                'tutor_id' => $dto->tutorId,
                'student_id' => $dto->studentId,
                'starts_at' => $dto->startsAt,
                'ends_at' => $dto->endsAt,
                'status' => BookingStatus::PENDING->value,
                'hourly_rate' => $dto->hourlyRate,
                'total_price' => $totalPrice,
                'meeting_id' => $meetingId,
                'meeting_url' => $meetingUrl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(int $bookingId): array
    {
        $booking = $this->bookingRepository->findById($bookingId);

        if ($booking === null) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        if ($booking['status'] === BookingStatus::CANCELLED->value) {
            return ['status' => 'error', 'message' => 'No se puede confirmar una reserva cancelada.'];
        }

        $updated = $this->bookingRepository->updateStatus($bookingId, BookingStatus::CONFIRMED);

        return [
            'status' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Reserva confirmada correctamente.' : 'No se pudo confirmar la reserva.',
            'booking_id' => $bookingId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $bookingId, ?string $reason = null): array
    {
        $booking = $this->bookingRepository->findById($bookingId);

        if ($booking === null) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        if ($booking['status'] === BookingStatus::CANCELLED->value) {
            return ['status' => 'error', 'message' => 'La reserva ya está cancelada.'];
        }

        $updated = $this->bookingRepository->cancel($bookingId, $reason);

        return [
            'status' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Reserva cancelada correctamente.' : 'No se pudo cancelar la reserva.',
            'booking_id' => $bookingId,
        ];
    }

    private function calculateDurationHours(string $startsAt, string $endsAt): float
    {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);

        $diffInSeconds = $end->getTimestamp() - $start->getTimestamp();

        if ($diffInSeconds <= 0) {
            throw new RuntimeException('La duración de la sesión debe ser mayor a cero.');
        }

        return round($diffInSeconds / 3600, 2);
    }
}
