<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Service\ReservationNotificationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ReservationNotificationMailerTest extends TestCase
{
    public function testItSendsReservationNotificationToOwner(): void
    {
        $reservation = (new Reservation())
            ->setFirstName('Client')
            ->setLastName('Test')
            ->setEmail('client@example.com');

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(
                    function (mixed $message) use ($reservation): bool {
                        self::assertInstanceOf(
                            TemplatedEmail::class,
                            $message
                        );

                        self::assertSame(
                            'Nouvelle réservation '
                            . $reservation->getReference(),
                            $message->getSubject()
                        );

                        self::assertSame(
                            'emails/new_reservation_notification.html.twig',
                            $message->getHtmlTemplate()
                        );

                        self::assertSame(
                            $reservation,
                            $message->getContext()['reservation']
                        );

                        self::assertSame(
                            'owner@example.com',
                            $message->getTo()[0]->getAddress()
                        );

                        self::assertSame(
                            'client@example.com',
                            $message->getReplyTo()[0]->getAddress()
                        );

                        self::assertSame(
                            'Client Test',
                            $message->getReplyTo()[0]->getName()
                        );

                        self::assertSame(
                            'noreply@taxi-paris-service24.fr',
                            $message->getFrom()[0]->getAddress()
                        );

                        return true;
                    }
                )
            );

        $service = new ReservationNotificationMailer(
            $mailer,
            'owner@example.com'
        );

        $service->send($reservation);
    }
}