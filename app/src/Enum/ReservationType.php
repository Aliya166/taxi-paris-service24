<?php

namespace App\Enum;

enum ReservationType: string
{
    case STANDARD = 'standard';
    case AIRPORT = 'airport';
    case STATION = 'station';
    case BUSINESS = 'business';
    case LONG_DISTANCE = 'long_distance';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD => 'Course standard',
            self::AIRPORT => 'Transfert aéroport',
            self::STATION => 'Transfert gare',
            self::BUSINESS => 'Trajet professionnel',
            self::LONG_DISTANCE => 'Longue distance',
        };
    }
}