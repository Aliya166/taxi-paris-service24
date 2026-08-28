<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Reservation;
use App\Entity\User;
use App\Security\ReservationAccessChecker;
use PHPUnit\Framework\TestCase;

final class ReservationAccessCheckerTest extends TestCase
{
    public function testItChecksReservationOwnership(): void
    {
        $checker = new ReservationAccessChecker();

        $user = (new User())
            ->setEmail('client@example.com');

        $ownReservation = (new Reservation())
            ->setCustomer($user)
            ->setEmail('contact@example.com');

        self::assertTrue(
            $checker->canManage($user, $ownReservation)
        );

        $otherUser = (new User())
            ->setEmail('other@example.com');

        $foreignReservation = (new Reservation())
            ->setCustomer($otherUser)
            ->setEmail('client@example.com');

        self::assertFalse(
            $checker->canManage($user, $foreignReservation)
        );

        $legacyGuestReservation = (new Reservation())
            ->setEmail(' CLIENT@EXAMPLE.COM ');

        self::assertTrue(
            $checker->canManage(
                $user,
                $legacyGuestReservation
            )
        );

        $unrelatedGuestReservation = (new Reservation())
            ->setEmail('unknown@example.com');

        self::assertFalse(
            $checker->canManage(
                $user,
                $unrelatedGuestReservation
            )
        );
    }
}