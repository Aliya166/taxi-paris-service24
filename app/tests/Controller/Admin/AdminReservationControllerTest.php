<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminReservationControllerTest extends WebTestCase
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

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->client->request(
            'GET',
            '/admin/reservations'
        );

        self::assertResponseRedirects('/login');
    }

    public function testAdminCanConfirmReservationWithValidCsrfToken(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $reservation = $this->createReservation(
            'confirm-action@example.com',
            new DateTimeImmutable('2026-09-20 14:00')
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reservations'
        );

        $form = $crawler
            ->filter(sprintf(
                'form[action$="/admin/reservations/%d/statut/confirm"]',
                $reservation->getId()
            ))
            ->form();

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/reservations');

        $storedReservation = $this->entityManager?->find(
            Reservation::class,
            $reservation->getId()
        );

        self::assertInstanceOf(
            Reservation::class,
            $storedReservation
        );

        self::assertSame(
            ReservationStatus::CONFIRMED,
            $storedReservation->getStatus()
        );
    }

    public function testInvalidCsrfTokenCannotChangeReservationStatus(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $reservation = $this->createReservation(
            'invalid-csrf@example.com',
            new DateTimeImmutable('2026-09-20 15:00')
        );

        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            sprintf(
                '/admin/reservations/%d/statut/confirm',
                $reservation->getId()
            ),
            [
                '_token' => 'invalid-token',
            ]
        );

        self::assertResponseStatusCodeSame(403);

        $storedReservation = $this->entityManager?->find(
            Reservation::class,
            $reservation->getId()
        );

        self::assertInstanceOf(
            Reservation::class,
            $storedReservation
        );

        self::assertSame(
            ReservationStatus::PENDING,
            $storedReservation->getStatus()
        );
    }

    public function testRegularUserCannotAccessAdminReservations(): void
    {
        $user = $this->createUser(['ROLE_USER']);

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/admin/reservations'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessReservationList(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/reservations'
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'h1',
            'Gestion des réservations'
        );
    }

    public function testUnknownReservationReturnsNotFound(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/reservations/999999999'
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminCanSearchReservationsByEmail(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $this->createReservation(
            'search-target@example.com',
            new DateTimeImmutable('2026-09-15 10:00')
        );

        $this->createReservation(
            'search-hidden@example.com',
            new DateTimeImmutable('2026-09-16 10:00')
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reservations?q=search-target'
        );

        self::assertResponseIsSuccessful();

        $pageContent = $crawler->filter('body')->text();

        self::assertStringContainsString(
            'search-target@example.com',
            $pageContent
        );

        self::assertStringNotContainsString(
            'search-hidden@example.com',
            $pageContent
        );
    }

    public function testAdminCanFilterReservationsByStatus(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $this->createReservation(
            'pending-filter@example.com',
            new DateTimeImmutable('2026-09-15 11:00')
        );

        $this->createReservation(
            'confirmed-filter@example.com',
            new DateTimeImmutable('2026-09-15 12:00'),
            true
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reservations?status=confirmed'
        );

        self::assertResponseIsSuccessful();

        $pageContent = $crawler->filter('body')->text();

        self::assertStringContainsString(
            'confirmed-filter@example.com',
            $pageContent
        );

        self::assertStringNotContainsString(
            'pending-filter@example.com',
            $pageContent
        );
    }

    public function testAdminCanFilterReservationsByDate(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN']);

        $this->createReservation(
            'inside-date-filter@example.com',
            new DateTimeImmutable('2026-09-15 18:30')
        );

        $this->createReservation(
            'outside-date-filter@example.com',
            new DateTimeImmutable('2026-10-01 10:00')
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reservations'
                . '?date_from=2026-09-10'
                . '&date_to=2026-09-20'
        );

        self::assertResponseIsSuccessful();

        $pageContent = $crawler->filter('body')->text();

        self::assertStringContainsString(
            'inside-date-filter@example.com',
            $pageContent
        );

        self::assertStringNotContainsString(
            'outside-date-filter@example.com',
            $pageContent
        );
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(array $roles): User
    {
        $user = (new User())
            ->setEmail(
                sprintf(
                    'admin-test-%s@example.com',
                    bin2hex(random_bytes(6))
                )
            )
            ->setPassword('password-not-used')
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setPhone('0612345678')
            ->setRoles($roles)
            ->setIsVerified(true);

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createReservation(
        string $email,
        DateTimeImmutable $scheduledAt,
        bool $confirmed = false
    ): Reservation {
        $reservation = (new Reservation())
            ->setFirstName('Client')
            ->setLastName('Filtre')
            ->setEmail($email)
            ->setPhone('0612345678')
            ->setPickupAddress('10 rue de Rivoli, Paris')
            ->setDropoffAddress('Aéroport Paris-Orly')
            ->setScheduledAt($scheduledAt)
            ->setPassengers(1)
            ->setLuggage(0);

        if ($confirmed) {
            $reservation->confirm();
        }

        $this->entityManager?->persist($reservation);
        $this->entityManager?->flush();

        return $reservation;
    }
}
