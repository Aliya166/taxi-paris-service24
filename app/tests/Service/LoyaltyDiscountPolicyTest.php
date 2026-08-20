<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LoyaltyDiscountPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LoyaltyDiscountPolicyTest extends TestCase
{
    private LoyaltyDiscountPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new LoyaltyDiscountPolicy();
    }

    public function testClientIsNotEligibleBeforeFiveCompletedRides(): void
    {
        self::assertFalse(
            $this->policy->isEligible(4, false)
        );

        self::assertSame(
            0,
            $this->policy->getDiscountPercentage(4, false)
        );
    }

    public function testClientIsEligibleAfterFiveCompletedRides(): void
    {
        self::assertTrue(
            $this->policy->isEligible(5, false)
        );

        self::assertSame(
            10,
            $this->policy->getDiscountPercentage(5, false)
        );
    }

    public function testClientRemainsEligibleAfterMoreThanFiveRides(): void
    {
        self::assertTrue(
            $this->policy->isEligible(8, false)
        );

        self::assertSame(
            10,
            $this->policy->getDiscountPercentage(8, false)
        );
    }

    public function testDiscountCannotBeUsedTwice(): void
    {
        self::assertFalse(
            $this->policy->isEligible(5, true)
        );

        self::assertSame(
            0,
            $this->policy->getDiscountPercentage(5, true)
        );
    }

    public function testNegativeCompletedRidesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The number of completed rides cannot be negative.'
        );

        $this->policy->isEligible(-1, false);
    }
}