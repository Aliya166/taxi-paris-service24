<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\LoyaltyDiscountPolicy;
use App\Service\LoyaltyDiscountService;
use PHPUnit\Framework\TestCase;

final class LoyaltyDiscountServiceTest extends TestCase
{
    public function testDiscountIsAppliedAfterFiveCompletedRides(): void
    {
        $repository = $this->createMock(
            ReservationRepository::class
        );

        $repository
            ->expects(self::once())
            ->method('countCompletedByEmail')
            ->with('client@example.com')
            ->willReturn(5);

        $repository
            ->expects(self::once())
            ->method('hasActiveOrUsedLoyaltyDiscountByEmail')
            ->with('client@example.com')
            ->willReturn(false);

        $service = new LoyaltyDiscountService(
            $repository,
            new LoyaltyDiscountPolicy()
        );

        $reservation = (new Reservation())
            ->setEmail('client@example.com');

        self::assertTrue($service->applyTo($reservation));
        self::assertSame(
            10,
            $reservation->getDiscountPercentage()
        );
        self::assertTrue(
            $reservation->isLoyaltyDiscountApplied()
        );
    }

    public function testDiscountIsNotAppliedBeforeFiveRides(): void
    {
        $repository = $this->createMock(
            ReservationRepository::class
        );

        $repository
            ->expects(self::once())
            ->method('countCompletedByEmail')
            ->willReturn(4);

        $repository
            ->expects(self::once())
            ->method('hasActiveOrUsedLoyaltyDiscountByEmail')
            ->willReturn(false);

        $service = new LoyaltyDiscountService(
            $repository,
            new LoyaltyDiscountPolicy()
        );

        $reservation = (new Reservation())
            ->setEmail('client@example.com');

        self::assertFalse($service->applyTo($reservation));
        self::assertSame(
            0,
            $reservation->getDiscountPercentage()
        );
        self::assertFalse(
            $reservation->isLoyaltyDiscountApplied()
        );
    }

    public function testDiscountIsNotAppliedWhenAlreadyUsed(): void
    {
        $repository = $this->createMock(
            ReservationRepository::class
        );

        $repository
            ->expects(self::once())
            ->method('countCompletedByEmail')
            ->willReturn(5);

        $repository
            ->expects(self::once())
            ->method('hasActiveOrUsedLoyaltyDiscountByEmail')
            ->willReturn(true);

        $service = new LoyaltyDiscountService(
            $repository,
            new LoyaltyDiscountPolicy()
        );

        $reservation = (new Reservation())
            ->setEmail('client@example.com');

        self::assertFalse($service->applyTo($reservation));
        self::assertSame(
            0,
            $reservation->getDiscountPercentage()
        );
        self::assertFalse(
            $reservation->isLoyaltyDiscountApplied()
        );
    }

    public function testSameReservationCannotReceiveDiscountTwice(): void
    {
        $repository = $this->createMock(
            ReservationRepository::class
        );

        $repository
            ->expects(self::never())
            ->method('countCompletedByEmail');

        $repository
            ->expects(self::never())
            ->method('hasActiveOrUsedLoyaltyDiscountByEmail');

        $service = new LoyaltyDiscountService(
            $repository,
            new LoyaltyDiscountPolicy()
        );

        $reservation = (new Reservation())
            ->setEmail('client@example.com')
            ->setDiscountPercentage(10)
            ->setLoyaltyDiscountApplied(true);

        self::assertFalse($service->applyTo($reservation));
        self::assertSame(
            10,
            $reservation->getDiscountPercentage()
        );
    }
}