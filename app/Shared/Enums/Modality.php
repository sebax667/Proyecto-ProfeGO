<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum Modality: string
{
    case VIRTUAL = 'virtual';
    case PRESENTIAL = 'presential';
    case HYBRID = 'hybrid';
}