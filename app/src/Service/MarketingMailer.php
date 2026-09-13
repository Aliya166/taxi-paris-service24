<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarketingMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MarketingUnsubscribeTokenService $tokenService,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function sendWelcomeEmail(User $user): bool
    {
        if (!$this->canReceiveMarketing($user)) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from(
                new Address(
                    'noreply@taxi-paris-service24.fr',
                    'Taxi Paris Service 24'
                )
            )
            ->to(
                new Address(
                    (string) $user->getEmail(),
                    $this->getCustomerName($user)
                )
            )
            ->subject(
                'Bienvenue chez Taxi Paris Service 24'
            )
            ->htmlTemplate(
                'emails/marketing_welcome.html.twig'
            )
            ->context(
                $this->createContext($user)
            );

        $this->mailer->send($email);

        return true;
    }

    public function sendOfferEmail(User $user): bool
    {
        if (!$this->canReceiveMarketing($user)) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from(
                new Address(
                    'noreply@taxi-paris-service24.fr',
                    'Taxi Paris Service 24'
                )
            )
            ->to(
                new Address(
                    (string) $user->getEmail(),
                    $this->getCustomerName($user)
                )
            )
            ->subject(
                'Vos avantages Taxi Paris Service 24'
            )
            ->htmlTemplate(
                'emails/marketing_offer.html.twig'
            )
            ->context(
                $this->createContext($user)
            );

        $this->mailer->send($email);

        return true;
    }

    private function canReceiveMarketing(User $user): bool
    {
        return $user->hasEmailMarketingConsent()
            && $user->getEmail() !== null
            && $user->getId() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createContext(User $user): array
    {
        $token = $this->tokenService->generate($user);

        return [
            'user' => $user,
            'unsubscribeUrl' => $this->urlGenerator->generate(
                'app_marketing_unsubscribe',
                [
                    'id' => $user->getId(),
                    'token' => $token,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }

    private function getCustomerName(User $user): string
    {
        $name = trim(
            sprintf(
                '%s %s',
                $user->getFirstName() ?? '',
                $user->getLastName() ?? ''
            )
        );

        return $name !== ''
            ? $name
            : (string) $user->getEmail();
    }
}