<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Controllers;

use App\Modules\Bookings\DTOs\CreateBookingDTO;
use App\Modules\Bookings\Exceptions\BookingException;
use App\Modules\Bookings\Repositories\BookingRepository;
use App\Modules\Bookings\Services\BookingService;
use App\Modules\Integrations\Adapters\MockVideoAdapter;
use App\Shared\Database\Database;
use InvalidArgumentException;

class BookingController
{
    private readonly BookingService $bookingService;

    public function __construct(?BookingService $bookingService = null)
    {
        $this->bookingService = $bookingService ?? new BookingService(
            new BookingRepository(Database::getConnection()),
            new MockVideoAdapter()
        );
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $authUser
     * @return array<string, mixed>
     */
    public function store(array $request, ?array $authUser = null): array
    {
        $studentId = $authUser['user_id'] ?? $authUser['id'] ?? null;
        if ($studentId === null) {
            return [
                'status' => 'error',
                'message' => 'No se pudo identificar al estudiante autenticado.',
            ];
        }

        $payload = [
            ...$request,
            'student_id' => (int) $studentId,
            'hourly_rate' => 1.0,
            'total_price' => 0.0,
        ];

        unset($payload['total_price']);

        try {
            $dto = CreateBookingDTO::fromRequest($payload);
            return $this->bookingService->create($dto);
        } catch (BookingException | InvalidArgumentException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $authUser
     * @return array<string, mixed>
     */
    public function confirm(array $request, ?array $authUser = null): array
    {
        $bookingId = isset($request['booking_id']) ? (int) $request['booking_id'] : 0;
        if ($bookingId <= 0) {
            return [
                'status' => 'error',
                'message' => 'Debe enviar un booking_id válido.',
            ];
        }

        $userId = isset($authUser['user_id']) ? (int) $authUser['user_id'] : (isset($authUser['id']) ? (int) $authUser['id'] : 0);
        $role = isset($authUser['role']) ? (string) $authUser['role'] : null;

        return $this->bookingService->confirm($bookingId, $userId, $role);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $authUser
     * @return array<string, mixed>
     */
    public function reject(array $request, ?array $authUser = null): array
    {
        $bookingId = isset($request['booking_id']) ? (int) $request['booking_id'] : 0;
        if ($bookingId <= 0) {
            return [
                'status' => 'error',
                'message' => 'Debe enviar un booking_id válido.',
            ];
        }

        $userId = isset($authUser['user_id']) ? (int) $authUser['user_id'] : (isset($authUser['id']) ? (int) $authUser['id'] : 0);
        $role = isset($authUser['role']) ? (string) $authUser['role'] : null;

        return $this->bookingService->reject($bookingId, $userId, $role);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $authUser
     * @return array<string, mixed>
     */
    public function cancel(array $request, ?array $authUser = null): array
    {
        $bookingId = isset($request['booking_id']) ? (int) $request['booking_id'] : 0;

        if ($bookingId <= 0) {
            return [
                'status' => 'error',
                'message' => 'Debe enviar un booking_id válido.',
            ];
        }

        $reason = isset($request['reason']) ? trim((string) $request['reason']) : null;
        $userId = isset($authUser['user_id']) ? (int) $authUser['user_id'] : (isset($authUser['id']) ? (int) $authUser['id'] : 0);
        $role = isset($authUser['role']) ? (string) $authUser['role'] : null;

        return $this->bookingService->cancel($bookingId, $reason !== '' ? $reason : null, $userId, $role);
    }
}
