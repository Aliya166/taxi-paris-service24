<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use App\Service\RouteCalculationService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AccountControllerTest extends WebTestCase
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

    public function testOwnerCanOpenPendingReservationEditPage(): void
    {
        $email = sprintf(
            'account-owner-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $reservation = $this->createPendingReservation($email);

        $user = $this->createUser($email);

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            sprintf(
                '/mon-compte/reservations/%d/modifier',
                $reservation->getId()
            )
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            'form'
        );
    }

    private function createStandardReservation(
        User $user
    ): Reservation {
        $reservation = (new Reservation())
            ->setCustomer($user)
            ->setFirstName('Client')
            ->setLastName('Test')
            ->setEmail($user->getEmail())
            ->setPhone('0612345678')
            ->setPickupAddress('Paris, France')
            ->setDropoffAddress('Versailles, France')
            ->setScheduledAt(
                new \DateTimeImmutable('2026-09-27 14:30')
            )
            ->setVehicleType(VehicleType::ECO)
            ->setPassengers(2)
            ->setLuggage(1)
            ->setDistanceKm('30.00')
            ->setDurationMinutes(40)
            ->setBasePrice('78.00')
            ->setFinalPrice('78.00')
            ->setDiscountAmount('0.00');

        $this->entityManager?->persist($reservation);
        $this->entityManager?->flush();

        self::assertNotNull(
            $reservation->getId()
        );

        return $reservation;
    }

    public function testAnotherUserCannotEditReservation(): void
    {
        $reservation = $this->createPendingReservation(
            sprintf(
                'reservation-owner-%s@example.com',
                bin2hex(random_bytes(6))
            )
        );

        $otherUser = $this->createUser(
            sprintf(
                'other-user-%s@example.com',
                bin2hex(random_bytes(6))
            )
        );

        $this->client->loginUser($otherUser);

        $this->client->request(
            'GET',
            sprintf(
                '/mon-compte/reservations/%d/modifier',
                $reservation->getId()
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN
        );
    }

    public function testNonPendingReservationCannotBeEdited(): void
    {
        $email = sprintf(
            'status-test-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $user = $this->createUser($email);

        foreach (
            [
                ReservationStatus::CONFIRMED,
                ReservationStatus::COMPLETED,
                ReservationStatus::CANCELLED,
            ] as $status
        ) {
            $reservation = $this->createPendingReservation(
                $email
            );

            if ($status === ReservationStatus::CONFIRMED) {
                $reservation->confirm();
            }

            if ($status === ReservationStatus::COMPLETED) {
                $reservation->confirm();
                $reservation->complete();
            }

            if ($status === ReservationStatus::CANCELLED) {
                $reservation->cancel(
                    'Annulation test.'
                );
            }

            $this->entityManager?->flush();

            $this->client->loginUser($user);

            $this->client->request(
                'GET',
                sprintf(
                    '/mon-compte/reservations/%d/modifier',
                    $reservation->getId()
                )
            );

            self::assertResponseRedirects('/mon-compte');
        }
    }

    public function testOwnerCanEditPendingReservation(): void
    {
        $email = sprintf(
            'edit-owner-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $user = $this->createUser($email);

        $reservation = $this->createPendingReservation(
            $email
        );

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            sprintf(
                '/mon-compte/reservations/%d/modifier',
                $reservation->getId()
            )
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->filter('form')
            ->form([
                'reservation_edit_form[pickupAddress]' =>
                'Paris Gare de Lyon, Paris',

                'reservation_edit_form[dropoffAddress]' =>
                'Lyon Part-Dieu, Lyon',

                'reservation_edit_form[scheduledAt]' =>
                '2026-09-26T10:15',

                'reservation_edit_form[vehicleType]' =>
                'van',

                'reservation_edit_form[passengers]' =>
                '4',

                'reservation_edit_form[luggage]' =>
                '3',
            ]);

        $childSeatField = $form['reservation_edit_form[childSeatRequested]'];

        self::assertInstanceOf(
            ChoiceFormField::class,
            $childSeatField
        );

        $childSeatField->tick();

        $this->client->submit($form);

        self::assertResponseRedirects('/mon-compte');

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $updatedReservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertInstanceOf(
            Reservation::class,
            $updatedReservation
        );

        self::assertSame(
            'Paris Gare de Lyon, Paris',
            $updatedReservation->getPickupAddress()
        );

        self::assertSame(
            'Lyon Part-Dieu, Lyon',
            $updatedReservation->getDropoffAddress()
        );

        self::assertSame(
            '2026-09-26 10:15',
            $updatedReservation
                ->getScheduledAt()
                ->format('Y-m-d H:i')
        );

        self::assertSame(
            'van',
            $updatedReservation->getVehicleType()->value
        );

        self::assertSame(
            4,
            $updatedReservation->getPassengers()
        );

        self::assertSame(
            3,
            $updatedReservation->getLuggage()
        );

        self::assertTrue(
            $updatedReservation->isChildSeatRequested()
        );
    }

    public function testChangingVehicleRecalculatesPrice(): void
    {
        $email = sprintf(
            'vehicle-price-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $user = $this->createUser($email);

        $reservation = $this->createStandardReservation(
            $user
        );

        self::assertSame(
            '78.00',
            $reservation->getBasePrice()
        );

        self::assertSame(
            '78.00',
            $reservation->getFinalPrice()
        );

        self::assertSame(
            VehicleType::ECO,
            $reservation->getVehicleType()
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            sprintf(
                '/mon-compte/reservations/%d/modifier',
                $reservationId
            )
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->filter('form')
            ->form([
                'reservation_edit_form[pickupAddress]' =>
                'Paris, France',

                'reservation_edit_form[dropoffAddress]' =>
                'Versailles, France',

                'reservation_edit_form[scheduledAt]' =>
                '2026-09-27T14:30',

                'reservation_edit_form[vehicleType]' =>
                'van',

                'reservation_edit_form[passengers]' =>
                '2',

                'reservation_edit_form[luggage]' =>
                '1',
            ]);

        $this->client->submit($form);

        self::assertResponseRedirects(
            '/mon-compte'
        );

        $updatedReservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertInstanceOf(
            Reservation::class,
            $updatedReservation
        );

        self::assertSame(
            VehicleType::VAN,
            $updatedReservation->getVehicleType()
        );

        self::assertSame(
            '30.00',
            $updatedReservation->getDistanceKm()
        );

        self::assertSame(
            40,
            $updatedReservation->getDurationMinutes()
        );

        self::assertSame(
            '102.00',
            $updatedReservation->getBasePrice()
        );

        self::assertSame(
            '102.00',
            $updatedReservation->getFinalPrice()
        );
    }

    public function testChangingRouteRecalculatesDistanceDurationAndPrice(): void
    {
        $email = sprintf(
            'route-price-%s@example.com',
            bin2hex(random_bytes(6))
        );

        $user = $this->createUser($email);

        $reservation = $this->createStandardReservation(
            $user
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $routeResponse = new MockResponse(
            json_encode(
                [
                    'features' => [
                        [
                            'properties' => [
                                'summary' => [
                                    'distance' => 50000,
                                    'duration' => 3600,
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

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            sprintf(
                '/mon-compte/reservations/%d/modifier',
                $reservationId
            )
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->filter('form')
            ->form([
                'reservation_edit_form[pickupAddress]' =>
                'Paris Gare de Lyon, Paris',

                'reservation_edit_form[dropoffAddress]' =>
                'Aéroport Paris-Orly, France',

                'reservation_edit_form[scheduledAt]' =>
                '2026-09-27T14:30',

                'reservation_edit_form[vehicleType]' =>
                'eco',

                'reservation_edit_form[passengers]' =>
                '2',

                'reservation_edit_form[luggage]' =>
                '1',

                'reservation_edit_form[pickupLongitude]' =>
                '2.3730',

                'reservation_edit_form[pickupLatitude]' =>
                '48.8443',

                'reservation_edit_form[dropoffLongitude]' =>
                '2.3652',

                'reservation_edit_form[dropoffLatitude]' =>
                '48.7262',
            ]);

        $this->client->submit($form);

        self::assertResponseRedirects(
            '/mon-compte'
        );

        $updatedReservation = $this->entityManager
            ?->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertInstanceOf(
            Reservation::class,
            $updatedReservation
        );

        self::assertSame(
            '50.00',
            $updatedReservation->getDistanceKm()
        );

        self::assertSame(
            60,
            $updatedReservation->getDurationMinutes()
        );

        self::assertSame(
            'Paris Gare de Lyon, Paris',
            $updatedReservation->getPickupAddress()
        );

        self::assertSame(
            'Aéroport Paris-Orly, France',
            $updatedReservation->getDropoffAddress()
        );

        self::assertNotSame(
            '78.00',
            $updatedReservation->getBasePrice()
        );

        self::assertSame(
            $updatedReservation->getBasePrice(),
            $updatedReservation->getFinalPrice()
        );
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPassword('test-password')
            ->setFirstName('Client')
            ->setLastName('Test')
            ->setPhone('0612345678')
            ->setIsVerified(true);

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        self::assertNotNull($user->getId());

        return $user;
    }

    private function createPendingReservation(
        string $email
    ): Reservation {
        $this->client->request(
            'POST',
            '/api/reservations',
            [
                'name' => 'Client Test',
                'email' => $email,
                'phone' => '0612345678',
                'pickupAddress' => 'Paris, France',
                'dropoffAddress' => 'Lyon, France',
                'date' => '2026-09-25',
                'heure' => '14:30',
                'vehicle' => 'berline',
                'reservation_type' => 'long_distance',
                'passengers' => '2',
                'luggage' => '1',
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
            ReservationStatus::PENDING,
            $reservation->getStatus()
        );

        return $reservation;
    }
}
