<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SendReservationRemindersCommand;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\ReservationReminderMailer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\MailerInterface;

final class SendReservationRemindersCommandTest extends TestCase
{
    public function testReminderIsSentOnlyOnce(): void
    {
        $reservation = (new Reservation())
            ->setFirstName('Client')
            ->setLastName('Rappel')
            ->setEmail('client@example.com');

        $repository = $this->createMock(
            ReservationRepository::class
        );

        $repository
            ->expects(self::exactly(2))
            ->method('findReservationsAwaitingReminder')
            ->willReturnCallback(
                function (
                    DateTimeImmutable $from,
                    DateTimeImmutable $until
                ) use ($reservation): array {
                    self::assertSame(
                        '2026-08-30 00:00:00',
                        $from->format('Y-m-d H:i:s')
                    );

                    self::assertSame(
                        '2026-08-31 00:00:00',
                        $until->format('Y-m-d H:i:s')
                    );

                    return $reservation->getReminderSentAt() === null
                        ? [$reservation]
                        : [];
                }
            );

        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(
                    function (mixed $message) use ($reservation): bool {
                        self::assertInstanceOf(
                            TemplatedEmail::class,
                            $message
                        );

                        self::assertSame(
                            'Rappel de votre réservation '
                            . $reservation->getReference(),
                            $message->getSubject()
                        );

                        return true;
                    }
                )
            );

        $entityManager = $this->createMock(
            EntityManagerInterface::class
        );

        $entityManager
            ->expects(self::once())
            ->method('flush');

        $command = new SendReservationRemindersCommand(
            $repository,
            new ReservationReminderMailer($mailer),
            $entityManager,
            new NullLogger()
        );

        $commandTester = new CommandTester($command);

        $firstStatus = $commandTester->execute([
            '--date' => '2026-08-27',
        ]);

        self::assertSame(Command::SUCCESS, $firstStatus);
        self::assertNotNull($reservation->getReminderSentAt());
        self::assertStringContainsString(
            '1 rappel(s) ajouté(s) à la file.',
            $commandTester->getDisplay()
        );

        $secondStatus = $commandTester->execute([
            '--date' => '2026-08-27',
        ]);

        self::assertSame(Command::SUCCESS, $secondStatus);
        self::assertStringContainsString(
            'Aucun rappel à envoyer',
            $commandTester->getDisplay()
        );
    }
}