<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TelegramReservationNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $telegramBotToken,
        private readonly string $telegramChatId
    ) {
    }

    public function sendReservationCreated(
        Reservation $reservation
    ): void {
        $message = $this->buildReservationMessage(
            $reservation
        );

        $this->sendMessage($message);
    }

    public function sendTestMessage(): void
    {
        $this->sendMessage(
            "✅ Test Telegram\nTaxi Paris Service 24\nConnexion réussie."
        );
    }

    private function sendMessage(string $message): void
    {
        $this->httpClient->request(
            'POST',
            sprintf(
                'https://api.telegram.org/bot%s/sendMessage',
                $this->telegramBotToken
            ),
            [
                'json' => [
                    'chat_id' => $this->telegramChatId,
                    'text' => $message,
                ],
            ]
        )->getContent();
    }

    private function buildReservationMessage(
        Reservation $reservation
    ): string {
        $price = $reservation->getFinalPrice();

        return sprintf(
            "🚕 NOUVELLE RÉSERVATION\n\n"
            . "Référence : %s\n"
            . "Client : %s %s\n"
            . "Téléphone : %s\n"
            . "Email : %s\n\n"
            . "📍 Départ :\n%s\n\n"
            . "🏁 Arrivée :\n%s\n\n"
            . "📅 Date : %s\n"
            . "🚘 Véhicule : %s\n"
            . "👥 Passagers : %d\n"
            . "🧳 Bagages : %d\n\n"
            . "💶 Prix estimé : %s",
            $reservation->getReference(),
            $reservation->getFirstName(),
            $reservation->getLastName(),
            $reservation->getPhone(),
            $reservation->getEmail(),
            $reservation->getPickupAddress(),
            $reservation->getDropoffAddress(),
            $reservation
                ->getScheduledAt()
                ?->format('d/m/Y à H:i') ?? 'Non renseignée',
            $reservation->getVehicleType()->value,
            $reservation->getPassengers(),
            $reservation->getLuggage(),
            $price !== null
                ? $price . ' €'
                : 'Sur devis'
        );
    }
}