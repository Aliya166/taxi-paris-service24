<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
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
}