<?php

namespace App\Enum;

enum VehicleType: string
{
    case ECO = 'eco';
    case BERLINE = 'berline';
    case VAN = 'van';

    public function label(): string
    {
        return match ($this) {
            self::ECO => 'Gamme Éco',
            self::BERLINE => 'Gamme Berline',
            self::VAN => 'Gamme Van',
        };
    }

    public function maxPassengers(): int
    {
        return match ($this) {
            self::ECO,
            self::BERLINE => 4,
            self::VAN => 7,
        };
    }

    public function maxLuggage(): int
    {
        return match ($this) {
            self::ECO => 2,
            self::BERLINE => 3,
            self::VAN => 6,
        };
    }
}