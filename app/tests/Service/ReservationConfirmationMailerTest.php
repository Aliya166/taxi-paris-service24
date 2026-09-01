<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Service\ReservationConfirmationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ReservationConfirmationMailerTest extends TestCase
{
    public function testItSendsReservationConfirmationEmail(): void
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
                            'Demande de réservation enregistrée '
                            . $reservation->getReference(),
                            $message->getSubject()
                        );

                        self::assertSame(
                            'emails/reservation_confirmation.html.twig',
                            $message->getHtmlTemplate()
                        );

                        self::assertSame(
                            $reservation,
                            $message->getContext()['reservation']
                        );

                        self::assertSame(
                            'client@example.com',
                            $message->getTo()[0]->getAddress()
                        );

                        self::assertSame(
                            'Client Test',
                            $message->getTo()[0]->getName()
                        );

                        self::assertSame(
                            'noreply@taxi-paris-service24.fr',
                            $message->getFrom()[0]->getAddress()
                        );

                        return true;
                    }
                )
            );

        $service = new ReservationConfirmationMailer($mailer);

        $service->send($reservation);
    }
}