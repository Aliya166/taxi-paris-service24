<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReservationConfirmationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer
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
                    $reservation->getEmail(),
                    $customerName
                )
            )
            ->subject(
                'Demande de réservation enregistrée '
                . $reservation->getReference()
            )
            ->htmlTemplate(
                'emails/reservation_confirmation.html.twig'
            )
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }
}