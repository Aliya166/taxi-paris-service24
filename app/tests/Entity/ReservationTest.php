<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase
{
    public function testNewReservationHasPendingStatusAndReference(): void
    {
        $reservation = new Reservation();

        self::assertSame(
            ReservationStatus::PENDING,
            $reservation->getStatus()
        );

        self::assertMatchesRegularExpression(
            '/^TPS24-\d{8}-[A-F0-9]{8}$/',
            $reservation->getReference()
        );

        self::assertInstanceOf(
            DateTimeImmutable::class,
            $reservation->getCreatedAt()
        );
    }

    public function testReservationCanBeConfirmedThenCompleted(): void
    {
        $reservation = new Reservation();

        $reservation->confirm();

        self::assertSame(
            ReservationStatus::CONFIRMED,
            $reservation->getStatus()
        );

        self::assertInstanceOf(
            DateTimeImmutable::class,
            $reservation->getConfirmedAt()
        );

        $reservation->complete();

        self::assertSame(
            ReservationStatus::COMPLETED,
            $reservation->getStatus()
        );

        self::assertInstanceOf(
            DateTimeImmutable::class,
            $reservation->getCompletedAt()
        );
    }

    public function testPendingReservationCannotBeCompletedDirectly(): void
    {
        $reservation = new Reservation();

        $this->expectException(DomainException::class);

        $reservation->complete();
    }

    public function testReservationCanBeCancelledWithReason(): void
    {
        $reservation = new Reservation();

        $reservation->cancel('Client request');

        self::assertSame(
            ReservationStatus::CANCELLED,
            $reservation->getStatus()
        );

        self::assertSame(
            'Client request',
            $reservation->getCancellationReason()
        );

        self::assertInstanceOf(
            DateTimeImmutable::class,
            $reservation->getCancelledAt()
        );
    }

    public function testLoyaltyDiscountIsDisabledByDefaultAndCanBeApplied(): void
    {
        $reservation = new Reservation();

        self::assertFalse(
            $reservation->isLoyaltyDiscountApplied()
        );

        $reservation->setLoyaltyDiscountApplied(true);

        self::assertTrue(
            $reservation->isLoyaltyDiscountApplied()
        );
    }
}
