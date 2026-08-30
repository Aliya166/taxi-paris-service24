<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ReservationCancellationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(TAXI_NOTIFICATION_EMAIL)%')]
        private readonly string $notificationEmail
    ) {
    }

    public function sendToCustomer(
        Reservation $reservation
    ): void {
        $customerName = $this->getCustomerName($reservation);

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
                'Annulation de votre réservation '
                . $reservation->getReference()
            )
            ->htmlTemplate(
                'emails/reservation_cancellation.html.twig'
            )
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }

    public function sendToOwner(
        Reservation $reservation
    ): void {
        $customerName = $this->getCustomerName($reservation);

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
                'Réservation annulée '
                . $reservation->getReference()
            )
            ->htmlTemplate(
                'emails/reservation_cancellation_notification.html.twig'
            )
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }

    private function getCustomerName(
        Reservation $reservation
    ): string {
        return trim(
            $reservation->getFirstName()
            . ' '
            . $reservation->getLastName()
        );
    }
}