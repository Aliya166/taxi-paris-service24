<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ReservationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $this->entityManager
            ->getConnection()
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (
            $this->entityManager !== null
            && $this->entityManager
                ->getConnection()
                ->isTransactionActive()
        ) {
            $this->entityManager
                ->getConnection()
                ->rollBack();
        }

        $this->entityManager = null;

        parent::tearDown();
    }

    public function testSubmittedPriceIsIgnored(): void
    {
        $email = sprintf(
            'pricing-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $this->client->request(
            'POST',
            '/api/reservations',
            [
                'name' => 'Client Test',
                'email' => $email,
                'phone' => '0612345678',
                'pickupAddress' =>
                    'Aéroport de Paris-Orly, Paray-Vieille-Poste, France',
                'dropoffAddress' =>
                    'Aéroport Paris Beauvais, Tillé, France',
                'date' => '2026-09-20',
                'heure' => '14:30',
                'vehicle' => 'eco',
                'reservation_type' => 'airport',
                'passengers' => '1',
                'luggage' => '1',
                'distanceKm' => '117.8 km',
                'durationMinutes' => '87 min',

                // Попытка клиента подменить цену.
                'estimatedPrice' => '1.00',
            ]
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_CREATED
        );

        $reservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->findOneBy(
                ['email' => $email],
                ['id' => 'DESC']
            );

        self::assertInstanceOf(
            Reservation::class,
            $reservation
        );

        self::assertSame(
            '241.36',
            $reservation->getBasePrice()
        );

        self::assertSame(
            '241.36',
            $reservation->getFinalPrice()
        );
    }

    public function testRouteMustBeCalculatedBeforeReservation(): void
    {
        $email = sprintf(
            'missing-route-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $this->client->request(
            'POST',
            '/api/reservations',
            [
                'name' => 'Client Test',
                'email' => $email,
                'phone' => '0612345678',
                'pickupAddress' => 'Colombes, France',
                'dropoffAddress' => 'Paris, France',
                'date' => '2026-09-20',
                'heure' => '15:00',
                'vehicle' => 'eco',
                'reservation_type' => 'standard',
                'passengers' => '1',
                'luggage' => '0',
                'distanceKm' => '',
                'durationMinutes' => '',
                'estimatedPrice' => '1.00',
            ]
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $reservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->findOneBy(['email' => $email]);

        self::assertNull($reservation);
    }
}