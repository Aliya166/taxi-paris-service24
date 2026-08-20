<?php

namespace App\Enum;

enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function canTransitionTo(self $nextStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array(
                $nextStatus,
                [self::CONFIRMED, self::CANCELLED],
                true
            ),
            self::CONFIRMED => in_array(
                $nextStatus,
                [self::COMPLETED, self::CANCELLED],
                true
            ),
            self::COMPLETED,
            self::CANCELLED => false,
        };
    }
}