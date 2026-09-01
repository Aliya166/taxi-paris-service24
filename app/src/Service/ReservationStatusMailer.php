<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReservationStatusMailer
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function sendConfirmed(
        Reservation $reservation
    ): void {
        $this->send(
            $reservation,
            'Votre réservation est confirmée ',
            'emails/reservation_confirmed.html.twig'
        );
    }

    public function sendCompleted(
        Reservation $reservation
    ): void {
        $this->send(
            $reservation,
            'Votre trajet est terminé ',
            'emails/reservation_completed.html.twig'
        );
    }

    private function send(
        Reservation $reservation,
        string $subjectPrefix,
        string $template
    ): void {
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
                $subjectPrefix
                . $reservation->getReference()
            )
            ->htmlTemplate($template)
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }
}