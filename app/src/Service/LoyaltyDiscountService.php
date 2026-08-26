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
    ) {}

    public function getCompletedRidesInCurrentCycleForEmail(
        string $email
    ): int {
        return $this->reservationRepository
            ->countCompletedInCurrentLoyaltyCycleByEmail($email);
    }

    public function getCompletedRidesInCurrentCycleForReservation(
        Reservation $reservation
    ): int {
        $customer = $reservation->getCustomer();

        if ($customer !== null) {
            return $this->reservationRepository
                ->countCompletedInCurrentLoyaltyCycleByCustomer($customer);
        }

        return $this->getCompletedRidesInCurrentCycleForEmail(
            $reservation->getEmail()
        );
    }

    public function getDiscountPercentageForEmail(string $email): int
    {
        $completedRides = $this
            ->getCompletedRidesInCurrentCycleForEmail($email);

        return $this->policy->getDiscountPercentage(
            $completedRides,
            false
        );
    }

    public function getDiscountPercentageForReservation(
        Reservation $reservation
    ): int {
        $completedRides = $this
            ->getCompletedRidesInCurrentCycleForReservation($reservation);

        return $this->policy->getDiscountPercentage(
            $completedRides,
            false
        );
    }

    public function applyTo(Reservation $reservation): bool
    {
        if ($reservation->isLoyaltyDiscountApplied()) {
            return false;
        }

        $discountPercentage = $this
            ->getDiscountPercentageForReservation($reservation);

        if ($discountPercentage === 0) {
            return false;
        }

        $reservation
            ->setDiscountPercentage($discountPercentage)
            ->setLoyaltyDiscountApplied(true);

        return true;
    }
}
