<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Service\ReservationStatusMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ReservationStatusMailerTest extends TestCase
{
    public function testItSendsReservationStatusEmails(): void
    {
        $reservation = (new Reservation())
            ->setFirstName('Client')
            ->setLastName('Test')
            ->setEmail('client@example.com');

        $messages = [];

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(
                static function (mixed $message) use (&$messages): void {
                    self::assertInstanceOf(
                        TemplatedEmail::class,
                        $message
                    );

                    $messages[] = $message;
                }
            );

        $service = new ReservationStatusMailer($mailer);

        $service->sendConfirmed($reservation);
        $service->sendCompleted($reservation);

        self::assertCount(2, $messages);

        $confirmedEmail = $messages[0];

        self::assertSame(
            'Votre réservation est confirmée '
            . $reservation->getReference(),
            $confirmedEmail->getSubject()
        );

        self::assertSame(
            'emails/reservation_confirmed.html.twig',
            $confirmedEmail->getHtmlTemplate()
        );

        self::assertSame(
            'client@example.com',
            $confirmedEmail->getTo()[0]->getAddress()
        );

        self::assertSame(
            'Client Test',
            $confirmedEmail->getTo()[0]->getName()
        );

        self::assertSame(
            $reservation,
            $confirmedEmail->getContext()['reservation']
        );

        $completedEmail = $messages[1];

        self::assertSame(
            'Votre trajet est terminé '
            . $reservation->getReference(),
            $completedEmail->getSubject()
        );

        self::assertSame(
            'emails/reservation_completed.html.twig',
            $completedEmail->getHtmlTemplate()
        );

        self::assertSame(
            'client@example.com',
            $completedEmail->getTo()[0]->getAddress()
        );

        self::assertSame(
            'Client Test',
            $completedEmail->getTo()[0]->getName()
        );

        self::assertSame(
            $reservation,
            $completedEmail->getContext()['reservation']
        );
    }
}