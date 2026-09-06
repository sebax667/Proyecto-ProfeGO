<?php

declare(strict_types=1);

namespace App\Shared\Events;

/**
 * Interface que define el contrato para los listeners de eventos.
 * Utiliza el patrón __invoke para permitir que el listener sea invocable.
 */
interface Listener
{
    /**
     * Invoca el listener cuando el evento es disparado
     */
    public function __invoke(Event $event): void;
}
