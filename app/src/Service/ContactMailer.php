<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(TAXI_NOTIFICATION_EMAIL)%')]
        private readonly string $notificationEmail
    ) {
    }

    /**
     * @param array{name: string, email: string, phone: string, subject: string, message: string} $data
     */
    public function sendToOwner(array $data): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(
                'noreply@taxi-paris-service24.fr',
                'Taxi Paris Service 24'
            ))
            ->to(new Address(
                $this->notificationEmail,
                'Taxi Paris Service 24'
            ))
            ->replyTo(new Address($data['email'], $data['name']))
            ->subject('Nouveau message depuis le site')
            ->htmlTemplate('emails/contact_notification.html.twig')
            ->context(['contact' => $data]);

        $this->mailer->send($email);
    }

    /**
     * @param array{name: string, email: string, phone: string, subject: string, message: string} $data
     */
    public function sendAcknowledgement(array $data): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(
                'noreply@taxi-paris-service24.fr',
                'Taxi Paris Service 24'
            ))
            ->to(new Address($data['email'], $data['name']))
            ->replyTo($this->notificationEmail)
            ->subject('Nous avons bien reçu votre message')
            ->htmlTemplate('emails/contact_acknowledgement.html.twig')
            ->context(['contact' => $data]);

        $this->mailer->send($email);
    }
}
