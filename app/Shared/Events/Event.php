<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Interface para todos los eventos en la aplicación.
 * Define el contrato que debe cumplir cualquier evento.
 */
interface Event
{
    /**
     * Retorna el nombre único del evento
     */
    public function getEventName(): string;

    /**
     * Retorna los datos asociados al evento
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array;
}
