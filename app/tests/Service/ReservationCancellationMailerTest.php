<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Service\ReservationCancellationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ReservationCancellationMailerTest extends TestCase
{
    public function testItSendsCancellationEmails(): void
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

        $service = new ReservationCancellationMailer(
            $mailer,
            'owner@example.com'
        );

        $service->sendToCustomer($reservation);
        $service->sendToOwner($reservation);

        self::assertCount(2, $messages);

        $customerEmail = $messages[0];

        self::assertSame(
            'Annulation de votre réservation '
            . $reservation->getReference(),
            $customerEmail->getSubject()
        );

        self::assertSame(
            'emails/reservation_cancellation.html.twig',
            $customerEmail->getHtmlTemplate()
        );

        self::assertSame(
            'client@example.com',
            $customerEmail->getTo()[0]->getAddress()
        );

        self::assertSame(
            'Client Test',
            $customerEmail->getTo()[0]->getName()
        );

        self::assertSame(
            $reservation,
            $customerEmail->getContext()['reservation']
        );

        $ownerEmail = $messages[1];

        self::assertSame(
            'Réservation annulée '
            . $reservation->getReference(),
            $ownerEmail->getSubject()
        );

        self::assertSame(
            'emails/reservation_cancellation_notification.html.twig',
            $ownerEmail->getHtmlTemplate()
        );

        self::assertSame(
            'owner@example.com',
            $ownerEmail->getTo()[0]->getAddress()
        );

        self::assertSame(
            'client@example.com',
            $ownerEmail->getReplyTo()[0]->getAddress()
        );

        self::assertSame(
            $reservation,
            $ownerEmail->getContext()['reservation']
        );
    }
}