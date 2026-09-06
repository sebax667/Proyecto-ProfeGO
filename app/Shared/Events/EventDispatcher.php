<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Dispatcher centralizado para gestionar eventos y sus listeners.
 * Implementa el patrón Observer permitiendo suscribirse a eventos y ejecutar listeners.
 */
final class EventDispatcher
{
    /**
     * Almacena los listeners registrados por evento
     *
     * @var array<string, array<Listener>>
     */
    private array $listeners = [];

    /**
     * Registra un listener para un evento específico
     *
     * @param class-string $eventClass Clase del evento (ej: BookingCreatedEvent::class)
     * @param Listener $listener Listener a ejecutar cuando se dispare el evento
     */
    public function subscribe(string $eventClass, Listener $listener): void
    {
        $eventName = $this->getEventName($eventClass);
        
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Dispara un evento y ejecuta todos los listeners suscriptores
     *
     * @param Event $event Evento a disparar
     */
    public function dispatch(Event $event): void
    {
        $eventName = $event->getEventName();

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            $listener($event);
        }
    }

    /**
     * Extrae el nombre del evento a partir de la clase
     *
     * @param class-string $eventClass
     */
    private function getEventName(string $eventClass): string
    {
        return $eventClass;
    }
}
