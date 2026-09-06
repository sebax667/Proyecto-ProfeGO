<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Listeners;

use App\Modules\Bookings\Events\BookingCreatedEvent;
use App\Shared\Events\Event;
use App\Shared\Events\Listener;

/**
 * Listener que se ejecuta cuando se crea una nueva reserva.
 * Simula el envío de notificaciones por email a tutores y estudiantes.
 */
final class SendEmailNotificationListener implements Listener
{
    /**
     * Invoca el listener cuando se dispara BookingCreatedEvent
     * Simula el envío de notificaciones con error_log
     */
    public function __invoke(Event $event): void
    {
        if (!$event instanceof BookingCreatedEvent) {
            return;
        }

        $bookingId = $event->getBookingId();
        $tutorId = $event->getTutorId();
        $studentId = $event->getStudentId();
        $meetingLink = $event->getMeetingLink();
        $bookingData = $event->getBookingData();

        // Simular notificación por email usando error_log
        $logMessage = sprintf(
            '[BOOKING_CREATED_EMAIL] BookingID: %d | TutorID: %d | StudentID: %d | Meeting: %s | Time: %s - %s',
            $bookingId,
            $tutorId,
            $studentId,
            $meetingLink,
            $bookingData['starts_at'] ?? 'N/A',
            $bookingData['ends_at'] ?? 'N/A'
        );

        error_log($logMessage);

        // En un escenario real, aquí se enviarían emails reales usando Swift Mailer, PHPMailer, etc.
        // Por ahora, solo registramos en los logs para demostrar la funcionalidad del evento
        error_log('[BOOKING_CREATED_EMAIL] Notificación enviada correctamente para reserva #' . $bookingId);
    }
}
