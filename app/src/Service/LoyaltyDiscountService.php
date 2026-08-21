<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;

final class LoyaltyDiscountService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly LoyaltyDiscountPolicy $policy
    ) {
    }

    public function getDiscountPercentageForEmail(string $email): int
    {
        $completedRides = $this->reservationRepository
            ->countCompletedByEmail($email);

        $discountAlreadyUsed = $this->reservationRepository
            ->hasActiveOrUsedLoyaltyDiscountByEmail($email);

        return $this->policy->getDiscountPercentage(
            $completedRides,
            $discountAlreadyUsed
        );
    }

    public function applyTo(Reservation $reservation): bool
    {
        if ($reservation->isLoyaltyDiscountApplied()) {
            return false;
        }

        $discountPercentage = $this->getDiscountPercentageForEmail(
            $reservation->getEmail()
        );

        if ($discountPercentage === 0) {
            return false;
        }

        $reservation
            ->setDiscountPercentage($discountPercentage)
            ->setLoyaltyDiscountApplied(true);

        return true;
    }
}