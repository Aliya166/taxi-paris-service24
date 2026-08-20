<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

final class LoyaltyDiscountPolicy
{
    public const REQUIRED_COMPLETED_RIDES = 5;
    public const DISCOUNT_PERCENTAGE = 10;

    public function isEligible(
        int $completedRides,
        bool $discountAlreadyUsed
    ): bool {
        if ($completedRides < 0) {
            throw new InvalidArgumentException(
                'The number of completed rides cannot be negative.'
            );
        }

        return $completedRides >= self::REQUIRED_COMPLETED_RIDES
            && $discountAlreadyUsed === false;
    }

    public function getDiscountPercentage(
        int $completedRides,
        bool $discountAlreadyUsed
    ): int {
        if (!$this->isEligible($completedRides, $discountAlreadyUsed)) {
            return 0;
        }

        return self::DISCOUNT_PERCENTAGE;
    }
}