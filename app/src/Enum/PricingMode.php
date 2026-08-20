<?php

namespace App\Enum;

enum PricingMode: string
{
    case DISTANCE_TIME = 'distance_time';
    case FIXED_FARE = 'fixed_fare';
    case MANUAL_QUOTE = 'manual_quote';

    public function label(): string
    {
        return match ($this) {
            self::DISTANCE_TIME => 'Calcul selon la distance et la durée',
            self::FIXED_FARE => 'Forfait fixe',
            self::MANUAL_QUOTE => 'Tarif sur devis',
        };
    }
}