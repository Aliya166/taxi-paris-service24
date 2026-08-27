<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReservationNotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(TAXI_NOTIFICATION_EMAIL)%')]
        private readonly string $notificationEmail
    ) {
    }

    public function send(Reservation $reservation): void
    {
        $customerName = trim(
            $reservation->getFirstName()
            . ' '
            . $reservation->getLastName()
        );

        $email = (new TemplatedEmail())
            ->from(
                new Address(
                    'noreply@taxi-paris-service24.fr',
                    'Taxi Paris Service24'
                )
            )
            ->to(
                new Address(
                    $this->notificationEmail,
                    'Taxi Paris Service24'
                )
            )
            ->replyTo(
                new Address(
                    $reservation->getEmail(),
                    $customerName
                )
            )
            ->subject(
                'Nouvelle réservation '
                . $reservation->getReference()
            )
            ->htmlTemplate(
                'emails/new_reservation_notification.html.twig'
            )
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }
}