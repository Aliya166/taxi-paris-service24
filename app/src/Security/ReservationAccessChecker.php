<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Reservation;
use App\Entity\User;

final class ReservationAccessChecker
{
    public function canManage(
        User $user,
        Reservation $reservation
    ): bool {
        $customer = $reservation->getCustomer();

        if ($customer !== null) {
            return $customer === $user
                || (
                    $customer->getId() !== null
                    && $customer->getId() === $user->getId()
                );
        }

        $reservationEmail = mb_strtolower(
            trim((string) $reservation->getEmail())
        );

        $userEmail = mb_strtolower(
            trim((string) $user->getEmail())
        );

        return $reservationEmail !== ''
            && $userEmail !== ''
            && $reservationEmail === $userEmail;
    }
}