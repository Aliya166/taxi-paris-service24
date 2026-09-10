<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Enum\PricingMode;
use App\Enum\ReservationType;
use App\Service\RouteCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

        $routeResponse = new MockResponse(
            json_encode(
                [
                    'features' => [
                        [
                            'properties' => [
                                'summary' => [
                                    'distance' => 117800,
                                    'duration' => 5220,
                                ],
                            ],
                        ],
                    ],
                ],
                JSON_THROW_ON_ERROR
            ),
            [
                'http_code' => 200,
                'response_headers' => [
                    'content-type: application/geo+json',
                ],
            ]
        );

        $routeCalculationService = new RouteCalculationService(
            new MockHttpClient($routeResponse),
            'test-api-key'
        );

        static::getContainer()->set(
            RouteCalculationService::class,
            $routeCalculationService
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
                'pickupLongitude' => '2.3652',
                'pickupLatitude' => '48.7262',
                'dropoffLongitude' => '2.1128',
                'dropoffLatitude' => '49.4544',
                'Aéroport Paris Beauvais, Tillé, France',
                'date' => '2026-09-20',
                'heure' => '14:30',
                'vehicle' => 'eco',
                'reservation_type' => 'standard',
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

    public function testSpecializedReservationDoesNotRequireRouteCalculation(): void
    {
        $email = sprintf(
            'long-distance-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $this->client->request(
            'POST',
            '/api/reservations',
            [
                'name' => 'Client Longue Distance',
                'email' => $email,
                'phone' => '0612345678',
                'pickupAddress' => 'Paris, France',
                'dropoffAddress' => 'Lyon, France',
                'date' => '2026-09-21',
                'heure' => '09:30',
                'vehicle' => 'berline',
                'reservation_type' => 'long_distance',
                'passengers' => '2',
                'luggage' => '2',
                'child_seat' => '1',
            ]
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $reservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame(
            ReservationType::LONG_DISTANCE,
            $reservation->getType()
        );
        self::assertSame(
            PricingMode::MANUAL_QUOTE,
            $reservation->getPricingMode()
        );
        self::assertNull($reservation->getDistanceKm());
        self::assertNull($reservation->getDurationMinutes());
        self::assertNull($reservation->getBasePrice());
        self::assertNull($reservation->getFinalPrice());
        self::assertTrue($reservation->isChildSeatRequested());
    }

    #[DataProvider('specializedPageProvider')]
    public function testSpecializedReservationPageIsAvailable(
        string $url,
        string $expectedHeading
    ): void {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $expectedHeading);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function specializedPageProvider(): iterable
    {
        yield 'airport' => [
            '/reservation/aeroport',
            'Votre transfert',
        ];
        yield 'station' => [
            '/reservation/gare',
            'Votre gare',
        ];
        yield 'business' => [
            '/reservation/professionnelle',
            'Vos rendez-vous',
        ];
        yield 'long distance' => [
            '/reservation/longue-distance',
            'Voyagez loin',
        ];
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
