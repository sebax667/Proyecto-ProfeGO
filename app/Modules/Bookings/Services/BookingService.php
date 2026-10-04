<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Services;

use App\Modules\Bookings\DTOs\CreateBookingDTO;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Events\BookingCreatedEvent;
use App\Modules\Bookings\Exceptions\BookingException;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Integrations\Adapters\MockVideoAdapter;
use App\Modules\Integrations\Contracts\VideoConferenceAdapterInterface;
use App\Shared\Events\EventDispatcher;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

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
        $tutorUserId = $this->bookingRepository->getTutorUserId($dto->tutorId);
        if ($tutorUserId === $dto->studentId) {
            throw new BookingException('No puedes reservarte a ti mismo.');
        }

        if (!$this->bookingRepository->tutorExists($dto->tutorId)) {
            throw new BookingException('El tutor indicado no existe.');
        }

        if (!$this->bookingRepository->studentExists($dto->studentId)) {
            throw new BookingException('El estudiante indicado no existe.');
        }

        $startDate = new DateTimeImmutable($dto->startsAt);
        $endDate = new DateTimeImmutable($dto->endsAt);
        if ($startDate <= new DateTimeImmutable('now')) {
            throw new BookingException('La fecha de la reserva debe ser futura.');
        }

        $durationHours = $this->calculateDurationHours($dto->startsAt, $dto->endsAt);
        if ($durationHours > 4.0) {
            throw new BookingException('La duración máxima por reserva es de 4 horas.');
        }

        $resolvedHourlyRate = $this->bookingRepository->getTutorHourlyRate($dto->tutorId);
        $dto = new CreateBookingDTO(
            tutorId: $dto->tutorId,
            studentId: $dto->studentId,
            startsAt: $dto->startsAt,
            endsAt: $dto->endsAt,
            hourlyRate: $resolvedHourlyRate,
            title: $dto->title,
            notes: $dto->notes,
            meetingType: $dto->meetingType,
        );

        if (!$this->bookingRepository->hasTutorAvailability($dto->tutorId, $dto->startsAt, $dto->endsAt)) {
            throw new BookingException('El tutor no tiene disponibilidad para ese horario.');
        }

        if ($this->bookingRepository->hasActiveOverlap($dto->tutorId, $dto->startsAt, $dto->endsAt)) {
            throw new BookingException('El tutor ya tiene una reserva activa en ese rango horario.');
        }

        $totalPrice = round($durationHours * $resolvedHourlyRate, 2);

        $this->bookingRepository->beginTransaction();

        try {
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

            if ($this->eventDispatcher !== null) {
                try {
                    $event = new BookingCreatedEvent(
                        $bookingId,
                        $dto->tutorId,
                        $dto->studentId,
                        $meetingUrl,
                        [
                            'starts_at' => $dto->startsAt,
                            'ends_at' => $dto->endsAt,
                            'hourly_rate' => $resolvedHourlyRate,
                            'total_price' => $totalPrice,
                            'meeting_id' => $meetingId,
                            'status' => BookingStatus::PENDING->value,
                        ]
                    );
                    $this->eventDispatcher->dispatch($event);
                } catch (Throwable $eventException) {
                    error_log('Booking event dispatch failed: ' . $eventException->getMessage());
                }
            }

            $this->bookingRepository->commit();

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
                    'hourly_rate' => $resolvedHourlyRate,
                    'total_price' => $totalPrice,
                    'meeting_id' => $meetingId,
                    'meeting_url' => $meetingUrl,
                ],
            ];
        } catch (Throwable $exception) {
            $this->bookingRepository->rollBack();
            throw new BookingException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(int $bookingId, ?int $userId = null, ?string $role = null): array
    {
        $booking = $this->bookingRepository->findById($bookingId);
        if ($booking === null) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $isAdmin = $role === 'admin';
        $isOwnerTutor = ((int) ($booking['tutor_user_id'] ?? 0)) === (int) ($userId ?? 0);
        if (($booking['status'] ?? '') !== BookingStatus::PENDING->value || (!$isAdmin && !$isOwnerTutor)) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $updated = $this->bookingRepository->updateStatus($bookingId, BookingStatus::CONFIRMED);

        return [
            'status' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Reserva confirmada correctamente.' : 'La reserva no existe.',
            'booking_id' => $bookingId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(int $bookingId, ?int $userId = null, ?string $role = null): array
    {
        $booking = $this->bookingRepository->findById($bookingId);
        if ($booking === null) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $isAdmin = $role === 'admin';
        $isOwnerTutor = ((int) ($booking['tutor_user_id'] ?? 0)) === (int) ($userId ?? 0);
        if (($booking['status'] ?? '') !== BookingStatus::PENDING->value || (!$isAdmin && !$isOwnerTutor)) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $updated = $this->bookingRepository->updateStatus($bookingId, BookingStatus::REJECTED);

        return [
            'status' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Reserva rechazada correctamente.' : 'La reserva no existe.',
            'booking_id' => $bookingId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $bookingId, ?string $reason = null, ?int $userId = null, ?string $role = null): array
    {
        $booking = $this->bookingRepository->findById($bookingId);

        if ($booking === null) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $isAdmin = $role === 'admin';
        $isOwnerStudent = ((int) ($booking['student_id'] ?? 0)) === (int) ($userId ?? 0);
        $isOwnerTutor = ((int) ($booking['tutor_user_id'] ?? 0)) === (int) ($userId ?? 0);
        if (!in_array($booking['status'] ?? '', [BookingStatus::PENDING->value, BookingStatus::CONFIRMED->value], true) || (!$isAdmin && !$isOwnerStudent && !$isOwnerTutor)) {
            return ['status' => 'error', 'message' => 'La reserva no existe.'];
        }

        $updated = $this->bookingRepository->cancel($bookingId, $reason);

        return [
            'status' => $updated ? 'success' : 'error',
            'message' => $updated ? 'Reserva cancelada correctamente.' : 'La reserva no existe.',
            'booking_id' => $bookingId,
        ];
    }

    private function calculateDurationHours(string $startsAt, string $endsAt): float
    {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);

        $diffInSeconds = $end->getTimestamp() - $start->getTimestamp();

        if ($diffInSeconds <= 0) {
            throw new InvalidArgumentException('La fecha de fin debe ser mayor que la de inicio.');
        }

        return round($diffInSeconds / 3600, 2);
    }
}
